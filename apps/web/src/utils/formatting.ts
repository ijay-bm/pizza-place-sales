/**
 * The dataset's amounts are in US dollars.
 */
const CURRENCY = new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' });

const DATE_AND_TIME = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short'
});

export function formatMoney(amount: string): string {
  return CURRENCY.format(Number(amount));
}

/**
 * Formats a moment the API sends without an offset, such as 2015-01-01T11:38:36, as the same wall-clock time: the
 * browser reads a date and time with no offset as local and formats it in local time, so nothing is converted.
 */
export function formatDateAndTime(dateAndTime: string): string {
  return DATE_AND_TIME.format(new Date(dateAndTime));
}

/**
 * Shortens "The Mediterranean Pizza" to "Mediterranean", for a pizza named in a row beside others.
 */
export function shortPizzaName(name: string): string {
  return name.replace(/^The /, '').replace(/ Pizza$/, '');
}

export function capitalize(word: string): string {
  return word.charAt(0).toUpperCase() + word.slice(1);
}

const MONTH = new Intl.DateTimeFormat(undefined, { month: 'short' });
const MONTH_AND_YEAR = new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' });
const WEEKDAY = new Intl.DateTimeFormat(undefined, { weekday: 'short' });

/**
 * Formats the months the API sends as 2015-01 by their short names, Jan, or with their years, Jan 2016, when they span
 * more than one year, so two Januaries never share a label.
 */
export function formatMonths(months: string[]): string[] {
  const spansYears = new Set(months.map((month) => month.slice(0, 4))).size > 1;
  const format = spansYears ? MONTH_AND_YEAR : MONTH;

  return months.map((month) => format.format(new Date(`${month}-01T00:00:00`)));
}

/**
 * Formats an ISO weekday, 1 for Monday to 7 for Sunday, by its short name; 1 to 7 January 2024 ran Monday to Sunday.
 */
export function formatWeekday(weekday: number): string {
  return WEEKDAY.format(new Date(2024, 0, weekday));
}

export function formatHour(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`;
}

/**
 * Formats a whole amount for a chart axis, $40,000, where cents would only crowd it.
 */
export function formatWholeMoney(amount: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0
  }).format(amount);
}
