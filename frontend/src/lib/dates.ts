const MINUTE = 60_000
const DAY = 24 * 60 * MINUTE

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

function getZoneParts(date: Date, timeZone: string): Record<string, string> {
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hourCycle: 'h23',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
  const parts: Record<string, string> = {}

  for (const part of formatter.formatToParts(date)) {
    parts[part.type] = part.value
  }

  return parts
}

/** How far the zone is ahead of UTC at the given instant, in milliseconds. */
function getZoneOffset(instant: number, timeZone: string): number {
  const parts = getZoneParts(new Date(instant), timeZone)
  const wallClockAsUtc = Date.UTC(
    Number(parts.year),
    Number(parts.month) - 1,
    Number(parts.day),
    Number(parts.hour),
    Number(parts.minute),
  )

  return wallClockAsUtc - Math.floor(instant / MINUTE) * MINUTE
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
 * `timeZone` into UTC ISO with `Z`. Returns null when that time does not exist in the
 * zone (the hour skipped by the spring clock change). For the repeated hour in autumn
 * it picks the earlier moment.
 */
export function zonedLocalToUtcIso(local: string, timeZone: string): string | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(local)

  if (!match) {
    return null
  }

  const [year, month, day, hour, minute] = match.slice(1).map(Number)
  const wallClockAsUtc = Date.UTC(year, month - 1, day, hour, minute)

  // The offset depends on the instant we are looking for, so try the offsets from a day
  // before and a day after, and keep only candidates that map back to the same wall clock.
  const candidates = [
    getZoneOffset(wallClockAsUtc - DAY, timeZone),
    getZoneOffset(wallClockAsUtc + DAY, timeZone),
  ]
    .map((offset) => wallClockAsUtc - offset)
    .filter((instant) => getZoneOffset(instant, timeZone) === wallClockAsUtc - instant)
    .sort((a, b) => a - b)

  if (candidates.length === 0) {
    return null
  }

  return new Date(candidates[0]).toISOString().replace('.000Z', 'Z')
}

/** The reverse: a UTC ISO date as a `datetime-local` value, wall-clock time in `timeZone`. */
export function utcIsoToZonedLocal(isoDate: string, timeZone: string): string {
  const parts = getZoneParts(new Date(isoDate), timeZone)

  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}
