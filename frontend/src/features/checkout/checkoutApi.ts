import { api, downloadFile } from '../../api/client'
import type { Order, Paginated } from '../../api/types'

export const ordersKey = ['orders']

export const ORDERS_PAGE_SIZE = 10

export interface OrderItemInput {
  ticketTypeId: number
  quantity: number
}

export function createOrder(eventId: number, items: OrderItemInput[]): Promise<Order> {
  return api.post<Order>('/api/orders', { eventId, items })
}

export function fetchOrders(page: number): Promise<Paginated<Order>> {
  return api.get<Paginated<Order>>('/api/orders', { page, limit: ORDERS_PAGE_SIZE })
}

export function fetchOrder(id: string): Promise<Order> {
  return api.get<Order>(`/api/orders/${id}`)
}

export function payOrder(id: number): Promise<Order> {
  return api.post<Order>(`/api/orders/${id}/pay`)
}

/** 404 INVOICE_NOT_FOUND until the invoice is issued. */
export function downloadInvoice(order: Order): Promise<void> {
  const name = order.invoice ? `faktura-${order.invoice.number.replaceAll('/', '-')}.pdf` : 'faktura.pdf'

  return downloadFile(`/api/orders/${order.id}/invoice`, name)
}

export function cancelOrder(id: number): Promise<Order> {
  return api.post<Order>(`/api/orders/${id}/cancel`)
}

/** 409 ORDER_NOT_REFUNDABLE or REFUND_WINDOW_CLOSED. The refund itself finishes in the background. */
export function refundOrder(id: number, reason: string): Promise<Order> {
  return api.post<Order>(`/api/orders/${id}/refund`, reason ? { reason } : {})
}

/** 404 INVOICE_NOT_FOUND until the correction invoice is issued. */
export function downloadCorrectionInvoice(order: Order): Promise<void> {
  const name = order.correctionInvoice
    ? `faktura-korygujaca-${order.correctionInvoice.number.replaceAll('/', '-')}.pdf`
    : 'faktura-korygujaca.pdf'

  return downloadFile(`/api/orders/${order.id}/correction-invoice`, name)
}
