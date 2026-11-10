import { api } from '../../api/client'
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

export function cancelOrder(id: number): Promise<Order> {
  return api.post<Order>(`/api/orders/${id}/cancel`)
}
