function getBrowserTimeZone(): string {
  return new Intl.DateTimeFormat().resolvedOptions().timeZone
}

/**
 * Formats an ISO date in the given zone, usually `event.timezone`.
 * The zone name is added only when it differs from the browser zone.
 */
export function formatDateTime(isoDate: string, timeZone = getBrowserTimeZone()): string {
  const formatter = new Intl.DateTimeFormat('pl-PL', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone,
    timeZoneName: timeZone === getBrowserTimeZone() ? undefined : 'short',
  })

  return formatter.format(new Date(isoDate))
}
