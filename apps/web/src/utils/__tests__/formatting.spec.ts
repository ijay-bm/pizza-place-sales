import { describe, expect, it } from 'vitest';
import { formatMonths } from '../formatting';

describe('formatMonths', () => {
  it('names each month by its short name when every month falls in one year', () => {
    expect(formatMonths(['2015-01', '2015-02', '2015-12'])).toEqual(['Jan', 'Feb', 'Dec']);
  });

  it('adds the year to every month when the months span more than one year', () => {
    expect(formatMonths(['2015-12', '2016-01'])).toEqual(['Dec 2015', 'Jan 2016']);
  });
});
