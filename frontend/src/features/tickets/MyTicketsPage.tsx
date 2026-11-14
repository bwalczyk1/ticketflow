import { useMutation, useQuery } from '@tanstack/react-query'
import { Download, LoaderCircle } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { PAGE_TITLE } from '@/lib/styles'
import type { Ticket } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { downloadTicketPdf, fetchTickets, ticketsKey } from './ticketsApi'
import { TicketStatusBadge } from './TicketStatusBadge'

interface EventGroup {
  event: Ticket['event']
  tickets: Ticket[]
}

/** Keeps the order of the list, so the event of the newest ticket comes first. */
function groupByEvent(tickets: Ticket[]): EventGroup[] {
  const groups = new Map<number, EventGroup>()

  for (const ticket of tickets) {
    const group = groups.get(ticket.event.id)

    if (group) {
      group.tickets.push(ticket)
    } else {
      groups.set(ticket.event.id, { event: ticket.event, tickets: [ticket] })
    }
  }

  return [...groups.values()]
}

interface TicketActionsProps {
  ticket: Ticket
  busy: boolean
  onDownload: (ticket: Ticket) => void
}

function TicketActions({ ticket, busy, onDownload }: TicketActionsProps) {
  return (
    <div className="flex flex-wrap items-center gap-2 md:justify-end">
      {ticket.status === 'VALID' && ticket.pdfReady && (
        <Button variant="outline" size="sm" type="button" disabled={busy} onClick={() => onDownload(ticket)}>
          {busy ? <LoaderCircle className="animate-spin" /> : <Download />}
          Pobierz PDF
        </Button>
      )}

      {ticket.status === 'VALID' && !ticket.pdfReady && (
        <span className="text-sm text-muted-foreground">Przygotowujemy PDF…</span>
      )}

      <Button variant="ghost" size="sm" asChild>
        <Link to={`/orders/${ticket.orderId}`}>Zamówienie #{ticket.orderId}</Link>
      </Button>
    </div>
  )
}

export function MyTicketsPage() {
  const [params, setParams] = useSearchParams()
  const page = Number(params.get('page')) || 1

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...ticketsKey, 'list', page],
    queryFn: () => fetchTickets(page),
    staleTime: 0,
    placeholderData: (previous) => previous,
  })

  const downloadMutation = useMutation({ mutationFn: downloadTicketPdf })

  function changePage(next: number) {
    setParams({ page: String(next) })
  }

  function isBusy(ticket: Ticket) {
    return downloadMutation.isPending && downloadMutation.variables.id === ticket.id
  }

  return (
    <section>
      <h1 className={`${PAGE_TITLE} mb-6`}>Moje bilety</h1>

      <ErrorBanner error={error} />
      <ErrorBanner error={downloadMutation.error} />

      {isPending && !error && <LoadingPage />}

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && (
          <p className="text-muted-foreground">
            Nie masz jeszcze biletów.{' '}
            <Link className="underline" to="/">
              Wybierz wydarzenie
            </Link>
          </p>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="grid gap-6">
              {groupByEvent(data.data).map(({ event, tickets }) => (
                <Card key={event.id}>
                  <CardHeader>
                    <CardTitle>
                      <h2 className="break-words">
                        <Link className="hover:underline" to={`/events/${event.id}`}>
                          {event.title}
                        </Link>
                      </h2>
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                      {formatDateTime(event.startsAt, event.timezone)} · {event.venue}, {event.city}
                    </p>
                  </CardHeader>

                  <CardContent>
                    <div className="hidden rounded-xl border md:block">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead className="pl-4">Bilet</TableHead>
                            <TableHead>Kod</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead className="pr-4 text-right">Akcje</TableHead>
                          </TableRow>
                        </TableHeader>

                        <TableBody>
                          {tickets.map((ticket) => (
                            <TableRow key={ticket.id}>
                              <TableCell className="pl-4 font-medium">
                                {ticket.ticketType.name} #{ticket.position}
                              </TableCell>
                              <TableCell className="font-mono text-sm">{ticket.code}</TableCell>
                              <TableCell>
                                <TicketStatusBadge status={ticket.status} />
                              </TableCell>
                              <TableCell className="pr-4">
                                <TicketActions ticket={ticket} busy={isBusy(ticket)} onDownload={downloadMutation.mutate} />
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>

                    <ul className="grid gap-3 md:hidden">
                      {tickets.map((ticket) => (
                        <li key={ticket.id} className="grid gap-2 rounded-lg border p-3">
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-medium">
                              {ticket.ticketType.name} #{ticket.position}
                            </span>
                            <TicketStatusBadge status={ticket.status} />
                          </div>

                          <p className="font-mono text-sm break-all text-muted-foreground">{ticket.code}</p>

                          <TicketActions ticket={ticket} busy={isBusy(ticket)} onDownload={downloadMutation.mutate} />
                        </li>
                      ))}
                    </ul>
                  </CardContent>
                </Card>
              ))}
            </div>

            <Pagination meta={data.meta} onPageChange={changePage} />
          </>
        )}
      </RefreshingArea>
    </section>
  )
}
