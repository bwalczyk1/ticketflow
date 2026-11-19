import { api, downloadFile } from '../../api/client'
import type { Paginated, Ticket } from '../../api/types'

export const ticketsKey = ['tickets']

/** An order holds at most a handful of tickets, so one page of the API's maximum covers it. */
const ORDER_TICKETS_LIMIT = 100

export function fetchOrderTickets(orderId: number): Promise<Paginated<Ticket>> {
  return api.get<Paginated<Ticket>>('/api/tickets', { orderId, limit: ORDER_TICKETS_LIMIT })
}

export function downloadTicketPdf(ticket: Ticket): Promise<void> {
  return downloadFile(`/api/tickets/${ticket.id}/pdf`, `bilet-${ticket.code}.pdf`)
}

export const TICKETS_PAGE_SIZE = 20

export function fetchTickets(page: number): Promise<Paginated<Ticket>> {
  return api.get<Paginated<Ticket>>('/api/tickets', { page, limit: TICKETS_PAGE_SIZE })
}
