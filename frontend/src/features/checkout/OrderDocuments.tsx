import { useMutation, useQuery } from '@tanstack/react-query'
import { Download, LoaderCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { Order, Ticket, TicketStatus } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { formatDateTime } from '../../lib/dates'
import { downloadTicketPdf, fetchOrderTickets, ticketsKey } from '../tickets/ticketsApi'
import { downloadInvoice } from './checkoutApi'

/** The element "Przejdź do biletów" scrolls to. */
export const ORDER_DOCUMENTS_ID = 'order-documents'

/** Tickets and their PDFs are issued in the background, so the list is polled for about a minute. */
const TICKETS_POLL_INTERVAL_MS = 3000
const TICKETS_POLL_LIMIT = 20

const TICKET_STATUS_LABELS: Record<TicketStatus, string> = {
  VALID: 'Ważny',
  USED: 'Wykorzystany',
  VOID: 'Unieważniony',
}

const TICKET_STATUS_VARIANTS = {
  VALID: 'default',
  USED: 'secondary',
  VOID: 'destructive',
} as const satisfies Record<TicketStatus, string>

function isWaitingForTickets(tickets: Ticket[] | undefined): boolean {
  return !tickets || tickets.length === 0 || tickets.some((ticket) => !ticket.pdfReady)
}

function DownloadButton({ busy, onClick, label }: { busy: boolean; onClick: () => void; label: string }) {
  return (
    <Button variant="outline" size="sm" type="button" disabled={busy} onClick={onClick}>
      {busy ? <LoaderCircle className="animate-spin" /> : <Download />}
      {label}
    </Button>
  )
}

/** The tickets and the invoice of a paid order. */
export function OrderDocuments({ order }: { order: Order }) {
  const { data, error } = useQuery({
    queryKey: [...ticketsKey, 'order', order.id],
    queryFn: () => fetchOrderTickets(order.id),
    refetchInterval: (query) =>
      isWaitingForTickets(query.state.data?.data) && query.state.dataUpdateCount < TICKETS_POLL_LIMIT
        ? TICKETS_POLL_INTERVAL_MS
        : false,
  })

  const ticketMutation = useMutation({ mutationFn: downloadTicketPdf })
  const invoiceMutation = useMutation({ mutationFn: () => downloadInvoice(order) })
  const tickets = data?.data ?? []

  return (
    <Card id={ORDER_DOCUMENTS_ID} className="scroll-mt-4">
      <CardHeader>
        <CardTitle>
          <h2>Bilety i faktura</h2>
        </CardTitle>
        <CardDescription>Bilet w PDF pokaż przy wejściu na wydarzenie.</CardDescription>
      </CardHeader>

      <CardContent className="grid gap-4">
        <ErrorBanner error={error} />
        <ErrorBanner error={ticketMutation.error} />

        {data && tickets.length === 0 && (
          <p className="flex items-center gap-2 text-sm text-muted-foreground" role="status">
            <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
            Wystawiamy bilety. Pojawią się tu za chwilę.
          </p>
        )}

        {tickets.length > 0 && (
          <ul className="grid gap-3">
            {tickets.map((ticket) => (
              <li
                key={ticket.id}
                className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-lg border p-3"
              >
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2 font-medium">
                    {ticket.ticketType.name} #{ticket.position}
                    <Badge variant={TICKET_STATUS_VARIANTS[ticket.status]}>{TICKET_STATUS_LABELS[ticket.status]}</Badge>
                  </p>
                  <p className="font-mono text-sm break-all text-muted-foreground">{ticket.code}</p>
                </div>

                {ticket.pdfReady ? (
                  <DownloadButton
                    label="Pobierz PDF"
                    busy={ticketMutation.isPending && ticketMutation.variables.id === ticket.id}
                    onClick={() => ticketMutation.mutate(ticket)}
                  />
                ) : (
                  <span className="text-sm text-muted-foreground">Przygotowujemy PDF…</span>
                )}
              </li>
            ))}
          </ul>
        )}

        <Separator />

        <ErrorBanner error={invoiceMutation.error} />

        {order.invoice ? (
          <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div>
              <p className="font-medium">Faktura {order.invoice.number}</p>
              <p className="text-sm text-muted-foreground">Wystawiona {formatDateTime(order.invoice.issuedAt)}</p>
            </div>

            <DownloadButton
              label="Pobierz fakturę"
              busy={invoiceMutation.isPending}
              onClick={() => invoiceMutation.mutate()}
            />
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">Faktura zostanie wystawiona po zaksięgowaniu płatności.</p>
        )}
      </CardContent>
    </Card>
  )
}
