import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PAGE_TITLE } from '@/lib/styles'
import type { WaitlistEntry, WaitlistStatus } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { formatPrice } from '../../lib/money'
import { eventsKey } from '../catalog/catalogApi'
import { ordersKey } from '../checkout/checkoutApi'
import { formatCountdown, useNow } from '../checkout/useCountdown'
import { acceptOffer, fetchWaitlist, leaveWaitlist, waitlistKey } from './waitlistApi'
import { WaitlistStatusBadge } from './WaitlistStatusBadge'

const STATUS_FILTERS: { value: WaitlistStatus | ''; label: string }[] = [
  { value: '', label: 'Wszystkie' },
  { value: 'WAITING', label: 'Oczekujące' },
  { value: 'OFFERED', label: 'Oferty' },
]

const ACCEPT_ERRORS = {
  SALES_CLOSED: 'Sprzedaż biletów na to wydarzenie jest już zamknięta.',
}

interface EntryActionsProps {
  entry: WaitlistEntry
  now: number
  busy: boolean
  onAccept: (entry: WaitlistEntry) => void
  onLeave: (entry: WaitlistEntry) => void
}

function EntryActions({ entry, now, busy, onAccept, onLeave }: EntryActionsProps) {
  if (entry.status === 'OFFERED' && entry.offeredUntil) {
    const remaining = Date.parse(entry.offeredUntil) - now

    if (remaining <= 0) {
      return <span className="text-sm text-muted-foreground">Oferta wygasła</span>
    }

    return (
      <div className="flex flex-wrap items-center gap-2 md:justify-end">
        <span className="text-sm tabular-nums text-muted-foreground" aria-label="Czas na zakup">
          Zostało {formatCountdown(remaining)}
        </span>

        <Button size="sm" type="button" disabled={busy} onClick={() => onAccept(entry)}>
          Kup teraz
        </Button>
      </div>
    )
  }

  if (entry.status === 'WAITING') {
    return (
      <Button variant="outline" size="sm" type="button" disabled={busy} onClick={() => onLeave(entry)}>
        Wypisz się
      </Button>
    )
  }

  if (entry.status === 'CONVERTED' && entry.orderId) {
    return (
      <Button variant="ghost" size="sm" asChild>
        <Link to={`/orders/${entry.orderId}`}>Zamówienie #{entry.orderId}</Link>
      </Button>
    )
  }

  return null
}

function positionLabel(entry: WaitlistEntry): string {
  return entry.status === 'WAITING' && entry.position !== null ? `Pozycja ${entry.position}` : '—'
}

export function WaitlistPage() {
  const now = useNow()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const status = STATUS_FILTERS.find(
    (filter) => filter.value !== '' && filter.value === params.get('status'),
  )?.value as WaitlistStatus | undefined
  const page = Number(params.get('page')) || 1

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...waitlistKey, 'list', status, page],
    queryFn: () => fetchWaitlist(status, page),
    staleTime: 0,
    placeholderData: (previous) => previous,
  })

  const acceptMutation = useMutation({
    mutationFn: (entry: WaitlistEntry) => acceptOffer(entry.id),
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ordersKey })
      navigate(`/orders/${order.id}`)
    },
    // The offer may have run out meanwhile, so the list is fetched again either way.
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: waitlistKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ]),
  })

  const leaveMutation = useMutation({
    mutationFn: (entry: WaitlistEntry) => leaveWaitlist(entry.ticketType.id),
    onSuccess: (_, entry) => toast.success(`Wypisano z listy oczekujących: ${entry.ticketType.name}.`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: waitlistKey }),
  })

  const busy = acceptMutation.isPending || leaveMutation.isPending

  function changeStatus(value: string) {
    setParams(value ? { status: value } : {})
  }

  function changePage(next: number) {
    const nextParams = new URLSearchParams(params)

    nextParams.set('page', String(next))
    setParams(nextParams)
  }

  function accept(entry: WaitlistEntry) {
    leaveMutation.reset()
    acceptMutation.mutate(entry)
  }

  function leave(entry: WaitlistEntry) {
    acceptMutation.reset()
    leaveMutation.mutate(entry)
  }

  function renderActions(entry: WaitlistEntry) {
    return <EntryActions entry={entry} now={now} busy={busy} onAccept={accept} onLeave={leave} />
  }

  return (
    <section>
      <h1 className={`${PAGE_TITLE} mb-6`}>Lista oczekujących</h1>

      <Tabs className="mb-4 max-w-full overflow-x-auto" value={status ?? ''} onValueChange={changeStatus}>
        <TabsList aria-label="Status">
          {STATUS_FILTERS.map((filter) => (
            <TabsTrigger key={filter.value} value={filter.value}>
              {filter.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <ErrorBanner error={error} />
      <ErrorBanner error={acceptMutation.error} messages={ACCEPT_ERRORS} />
      <ErrorBanner error={leaveMutation.error} />

      {isPending && !error && <LoadingPage />}

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && (
          <p className="text-muted-foreground">
            Nie czekasz na żadne bilety. Gdy pula będzie wyprzedana, zapiszesz się na listę na stronie{' '}
            <Link className="underline" to="/">
              wydarzenia
            </Link>
            .
          </p>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="hidden rounded-xl border md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-4">Wydarzenie</TableHead>
                    <TableHead>Pula</TableHead>
                    <TableHead>Liczba</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Miejsce w kolejce</TableHead>
                    <TableHead className="pr-4 text-right">Akcje</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {data.data.map((entry) => (
                    <TableRow key={entry.id}>
                      <TableCell className="pl-4 whitespace-normal">
                        <Link className="font-medium hover:underline" to={`/events/${entry.event.id}`}>
                          {entry.event.title}
                        </Link>
                        <p className="text-sm text-muted-foreground">{formatDateTime(entry.event.startsAt)}</p>
                      </TableCell>
                      <TableCell className="whitespace-normal">
                        {entry.ticketType.name}
                        <p className="text-sm text-muted-foreground">{formatPrice(entry.ticketType.price)}</p>
                      </TableCell>
                      <TableCell>{entry.quantity}</TableCell>
                      <TableCell>
                        <WaitlistStatusBadge status={entry.status} />
                      </TableCell>
                      <TableCell>{positionLabel(entry)}</TableCell>
                      <TableCell className="pr-4 text-right">{renderActions(entry)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <ul className="grid gap-3 md:hidden">
              {data.data.map((entry) => (
                <li key={entry.id}>
                  <Card className="py-4">
                    <CardContent className="grid gap-3 px-4">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <Link className="min-w-0 font-medium break-words hover:underline" to={`/events/${entry.event.id}`}>
                          {entry.event.title}
                        </Link>
                        <WaitlistStatusBadge status={entry.status} />
                      </div>

                      <p className="text-sm text-muted-foreground">
                        {entry.ticketType.name} · {entry.quantity} szt. · {formatPrice(entry.ticketType.price)}
                        <br />
                        {formatDateTime(entry.event.startsAt)} · {positionLabel(entry)}
                      </p>

                      {renderActions(entry)}
                    </CardContent>
                  </Card>
                </li>
              ))}
            </ul>

            <Pagination meta={data.meta} onPageChange={changePage} />
          </>
        )}
      </RefreshingArea>
    </section>
  )
}
