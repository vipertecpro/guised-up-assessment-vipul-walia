import { timeAgo } from './timeAgo';

const now = Date.parse('2026-07-13T12:00:00.000Z');

describe('timeAgo', () => {
  it.each([
    ['2026-07-13T11:59:40.000Z', 'just now'],
    ['2026-07-13T11:56:00.000Z', '4m ago'],
    ['2026-07-13T10:00:00.000Z', '2h ago'],
    ['2026-07-10T12:00:00.000Z', '3d ago'],
    ['2026-06-29T12:00:00.000Z', '2w ago'],
  ])('formats %s as %s', (timestamp, expected) => {
    expect(timeAgo(timestamp, now)).toBe(expected);
  });

  it('handles future and invalid timestamps safely', () => {
    expect(timeAgo('2026-07-14T12:00:00.000Z', now)).toBe('just now');
    expect(timeAgo('not-a-date', now)).toBe('recently');
    expect(timeAgo(null, now)).toBe('recently');
  });
});
