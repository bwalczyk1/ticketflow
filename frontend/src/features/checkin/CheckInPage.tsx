import { useMutation, useQuery } from '@tanstack/react-query'
import { CircleAlert, CircleCheck } from 'lucide-react'
import { useRef, useState, type FormEvent } from 'react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError, getErrorMessage } from '../../api/errors'
import type { CheckedInTicket, ManagedEvent } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { checkInTicket, checkinKey, fetchCheckInEvents } from './checkinApi'

/** How many scans the session list keeps. */
const HISTORY_LIMIT = 10

type ScanOutcome =
  | { ok: true; ticket: CheckedInTicket }
  | { ok: false; message: string }

interface Scan {
  code: string
  at: number
  outcome: ScanOutcome
}

interface ScanRequest {
  code: string
  at: number
}

function localDay(isoDate: string, timeZone: string): string {
  return new Date(isoDate).toLocaleDateString('sv-SE', { timeZone })
}

/** Today's events first, then the rest from the nearest. */
function sortForCheckIn(events: ManagedEvent[], now: number): ManagedEvent[] {
  function isToday(event: ManagedEvent): boolean {
    return localDay(event.startsAt, event.timezone) === localDay(new Date(now).toISOString(), event.timezone)
  }

  return [...events].sort((a, b) => {
    if (isToday(a) !== isToday(b)) {
      return isToday(a) ? -1 : 1
    }

    return Date.parse(a.startsAt) - Date.parse(b.startsAt)
  })
}

/** The API names the moment of the first scan in the details of TICKET_ALREADY_USED. */
function describeFailure(error: unknown, timeZone: string): string {
  if (error instanceof ApiError && error.code === 'TICKET_ALREADY_USED') {
    const usedAt = error.details.find((detail) => detail.field === 'checkedInAt')?.message

    if (usedAt && !Number.isNaN(Date.parse(usedAt))) {
      return `Ten bilet został już wykorzystany: ${formatDateTime(usedAt, timeZone)}.`
    }
  }

  return getErrorMessage(error)
}

export function CheckInPage() {
  const inputRef = useRef<HTMLInputElement>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [code, setCode] = useState('')
  const [scans, setScans] = useState<Scan[]>([])
  // Read once: "today" matters when the page opens, not as the clock ticks.
  const [now] = useState(() => Date.now())

  const { data, error, isPending } = useQuery({
    queryKey: [...checkinKey, 'events'],
    queryFn: fetchCheckInEvents,
  })

  const events = sortForCheckIn(data?.data ?? [], now)
  const event = events.find((candidate) => String(candidate.id) === selectedId) ?? events[0]
  const lastScan = scans[0]

  const scanMutation = useMutation({
    mutationFn: ({ code: scannedCode }: ScanRequest) => checkInTicket(event.id, scannedCode),
    onSettled: (result, scanError, request) => {
      const outcome: ScanOutcome = result
        ? { ok: true, ticket: result.ticket }
        : { ok: false, message: describeFailure(scanError, event.timezone) }
      const scan: Scan = { ...request, outcome }

      setScans((current) => [scan, ...current].slice(0, HISTORY_LIMIT))
      setCode('')
      inputRef.current?.focus()
    },
  })

  function handleSubmit(submitEvent: FormEvent) {
    submitEvent.preventDefault()

    const trimmed = code.trim()

    if (!event || !trimmed || scanMutation.isPending) {
      return
    }

    scanMutation.mutate({ code: trimmed, at: Date.now() })
  }

  if (error) {
    return <ErrorBanner error={error} />
  }

  if (isPending) {
    return <LoadingPage />
  }

  return (
    <section className="grid max-w-2xl gap-6">
      <h1 className={PAGE_TITLE}>Check-in</h1>

      {events.length === 0 && (
        <p className="text-muted-foreground">Nie masz opublikowanych wydarzeń, na które można odprawiać gości.</p>
      )}

      {event && (
        <>
          <div className="grid gap-2">
            <Label htmlFor="check-in-event">Wydarzenie</Label>

            <Select value={String(event.id)} onValueChange={setSelectedId}>
              <SelectTrigger id="check-in-event" className="w-full">
                <SelectValue />
              </SelectTrigger>

              <SelectContent>
                {events.map((candidate) => (
                  <SelectItem key={candidate.id} value={String(candidate.id)}>
                    {candidate.title} · {formatDateTime(candidate.startsAt, candidate.timezone)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <form className="grid gap-2" noValidate onSubmit={handleSubmit}>
            <Label htmlFor="check-in-code">Kod biletu</Label>

            <div className="flex gap-2">
              <Input
                id="check-in-code"
                ref={inputRef}
                autoFocus
                autoComplete="off"
                autoCapitalize="off"
                spellCheck={false}
                className="font-mono"
                placeholder="Zeskanuj lub wpisz kod i naciśnij Enter"
                value={code}
                onChange={(changeEvent) => setCode(changeEvent.target.value)}
              />

              <Button type="submit" disabled={scanMutation.isPending || code.trim() === ''}>
                Sprawdź
              </Button>
            </div>
          </form>

          {lastScan?.outcome.ok && (
            <Alert role="status" className="border-green-600/50 bg-green-50 text-green-900 dark:bg-green-950 dark:text-green-100">
              <CircleCheck />
              <AlertTitle className="line-clamp-none">Wejście zatwierdzone</AlertTitle>
              <AlertDescription className="text-current">
                {lastScan.outcome.ticket.ticketTypeName} · {lastScan.outcome.ticket.holderName}
                <br />
                {formatDateTime(lastScan.outcome.ticket.checkedInAt, event.timezone)}
              </AlertDescription>
            </Alert>
          )}

          {lastScan && !lastScan.outcome.ok && (
            <Alert role="alert" variant="destructive">
              <CircleAlert />
              <AlertTitle className="line-clamp-none">Odrzucono</AlertTitle>
              <AlertDescription>{lastScan.outcome.message}</AlertDescription>
            </Alert>
          )}

          {scans.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>
                  <h2>Ostatnie skany</h2>
                </CardTitle>
              </CardHeader>

              <CardContent>
                <ul className="grid gap-2">
                  {scans.map((scan) => (
                    <li
                      key={scan.at}
                      className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 rounded-lg border p-3 text-sm"
                    >
                      <div className="min-w-0">
                        <p className="font-mono break-all">{scan.code}</p>
                        <p className="text-muted-foreground">
                          {scan.outcome.ok
                            ? `${scan.outcome.ticket.ticketTypeName} · ${scan.outcome.ticket.holderName}`
                            : scan.outcome.message}
                        </p>
                      </div>

                      <span className={scan.outcome.ok ? 'font-medium text-green-700 dark:text-green-400' : 'font-medium text-destructive'}>
                        {scan.outcome.ok ? 'OK' : 'Odrzucono'} · {formatDateTime(new Date(scan.at).toISOString())}
                      </span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </section>
  )
}
