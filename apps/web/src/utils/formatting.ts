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
