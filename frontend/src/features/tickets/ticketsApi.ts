import { api, downloadFile } from '../../api/client'
import type { Paginated, Ticket } from '../../api/types'

export const ticketsKey = ['tickets']

/** An order holds at most a handful of tickets, so one page of the API's maximum covers it. */
const ORDER_TICKETS_LIMIT = 100

export function fetchOrderTickets(orderId: number): Promise<Paginated<Ticket>> {
  return api.get<Paginated<Ticket>>('/api/tickets', { orderId, limit: ORDER_TICKETS_LIMIT })
}

/** The API hands out the PDF of a valid or a used ticket, but not of a void one. */
export function isPdfAvailable(ticket: Ticket): boolean {
  return ticket.status !== 'VOID' && ticket.pdfReady
}

/** The PDF is still being generated; a void ticket gets none. */
export function isPdfPending(ticket: Ticket): boolean {
  return ticket.status !== 'VOID' && !ticket.pdfReady
}

export function downloadTicketPdf(ticket: Ticket): Promise<void> {
  return downloadFile(`/api/tickets/${ticket.id}/pdf`, `bilet-${ticket.code}.pdf`)
}

const TICKETS_PAGE_SIZE = 20

export function fetchTickets(page: number): Promise<Paginated<Ticket>> {
  return api.get<Paginated<Ticket>>('/api/tickets', { page, limit: TICKETS_PAGE_SIZE })
}
