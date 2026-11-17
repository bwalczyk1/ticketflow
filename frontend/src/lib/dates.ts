import { Temporal } from 'temporal-polyfill'

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

/** Whether the name is an IANA zone the browser knows, e.g. Europe/Warsaw. */
export function isValidTimeZone(timeZone: string): boolean {
  try {
    new Intl.DateTimeFormat('en-US', { timeZone })

    return true
  } catch {
    return false
  }
}

/**
 * Converts a `datetime-local` value ("2026-12-01T19:00") read as wall-clock time in
 * `timeZone` into UTC ISO with `Z`. Returns null for an empty value and for a time that
 * does not exist in the zone (the hour skipped by the spring clock change). For the
 * repeated hour in autumn it picks the earlier moment.
 */
export function zonedLocalToUtcIso(local: string, timeZone: string): string | null {
  try {
    const wallClock = Temporal.PlainDateTime.from(local)
    const zoned = wallClock.toZonedDateTime(timeZone, { disambiguation: 'earlier' })

    // In the skipped hour Temporal moves the time, so it no longer matches what was typed.
    return zoned.toPlainDateTime().equals(wallClock) ? zoned.toInstant().toString() : null
  } catch {
    return null
  }
}

/** The reverse: a UTC ISO date as a `datetime-local` value, wall-clock time in `timeZone`. */
export function utcIsoToZonedLocal(isoDate: string, timeZone: string): string {
  return Temporal.Instant.from(isoDate)
    .toZonedDateTimeISO(timeZone)
    .toPlainDateTime()
    .toString({ smallestUnit: 'minute' })
}
