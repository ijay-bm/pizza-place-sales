<?php

namespace App\Imports;

use RuntimeException;

/**
 * One or more of the four files cannot be imported; each message is keyed by the upload field its file came in.
 */
class InvalidSalesDataException extends RuntimeException
{
    /**
     * @param  array<string, string>  $messagesByField
     */
    public function __construct(public readonly array $messagesByField)
    {
        parent::__construct(implode(' ', $messagesByField));
    }
}
