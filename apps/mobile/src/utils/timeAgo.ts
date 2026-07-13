/** Format an API timestamp as a compact relative time label. */
export function timeAgo(timestamp: string | null, now = Date.now()): string {
  if (!timestamp) {
    return 'recently';
  }

  const parsed = Date.parse(timestamp);

  if (!Number.isFinite(parsed)) {
    return 'recently';
  }

  const seconds = Math.max(0, Math.floor((now - parsed) / 1000));

  if (seconds < 60) {
    return 'just now';
  }

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) {
    return `${minutes}m ago`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours}h ago`;
  }

  const days = Math.floor(hours / 24);
  if (days < 7) {
    return `${days}d ago`;
  }

  return `${Math.floor(days / 7)}w ago`;
}
