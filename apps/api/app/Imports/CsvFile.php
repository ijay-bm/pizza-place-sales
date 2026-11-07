<?php

namespace App\Imports;

use Generator;

/**
 * One CSV file whose header must hold exactly the expected columns, read a row at a time.
 */
class CsvFile
{
    /**
     * @param  string  $name  the upload field the file came in, which names it in an error
     * @param  list<string>  $columns
     */
    public function __construct(
        private readonly string $path,
        public readonly string $name,
        private readonly array $columns,
    ) {}

    /**
     * Returns why the header does not hold exactly the expected columns, or null when it does.
     */
    public function headerError(): ?string
    {
        $handle = $this->open();

        try {
            return fgetcsv($handle, escape: '') === $this->columns ? null : $this->columnsMessage();
        } finally {
            fclose($handle);
        }
    }

    /**
     * Yields each row keyed by its column names, with its line number as the key.
     *
     * A field that is not valid UTF-8 is read as Windows-1252, the encoding pizza_types.csv is saved in.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(): Generator
    {
        $handle = $this->open();

        try {
            if (fgetcsv($handle, escape: '') !== $this->columns) {
                throw new InvalidSalesDataException([$this->name => $this->columnsMessage()]);
            }

            $line = 1;

            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($fields === [null]) {
                    continue;
                }

                if (count($fields) !== count($this->columns)) {
                    throw $this->invalid(
                        $line,
                        sprintf('expected %d fields, found %d.', count($this->columns), count($fields)),
                    );
                }

                yield $line => array_combine($this->columns, array_map($this->toUtf8(...), $fields));
            }

            // fgetcsv() also returns false on a read error, which would end the loop as though the file were done.
            if (! feof($handle)) {
                throw $this->invalid($line + 1, 'the file could not be read to the end.');
            }
        } finally {
            fclose($handle);
        }
    }

    public function invalid(int $line, string $reason): InvalidSalesDataException
    {
        return new InvalidSalesDataException([$this->name => "Line {$line}: {$reason}"]);
    }

    /**
     * @return resource
     */
    private function open()
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw $this->invalid(0, 'the file cannot be opened.');
        }

        return $handle;
    }

    private function columnsMessage(): string
    {
        return 'Line 1: the columns must be ' . implode(', ', $this->columns) . '.';
    }

    private function toUtf8(string $field): string
    {
        return mb_check_encoding($field, 'UTF-8') ? $field : mb_convert_encoding($field, 'UTF-8', 'Windows-1252');
    }
}
