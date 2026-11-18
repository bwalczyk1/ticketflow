import { api } from '../../api/client'
import type { EventStatus, ManagedEvent, ManagedTicketType, Paginated } from '../../api/types'

export const PAGE_SIZE = 10

export const organizerKey = ['organizer']

export interface EventPayload {
  title: string
  description: string
  venue: string
  city: string
  category: string
  timezone: string
  startsAt: string
  endsAt: string
}

export interface TicketTypePayload {
  name: string
  /** Grosze. */
  price: number
  quantity: number
}

const BASE = '/api/organizer/events'

export function fetchMyEvents(
  status: EventStatus | undefined,
  page: number,
): Promise<Paginated<ManagedEvent>> {
  return api.get<Paginated<ManagedEvent>>(BASE, { status, page, limit: PAGE_SIZE })
}

/** Includes the ticket types with their sales counters. */
export function fetchMyEvent(id: string): Promise<ManagedEvent> {
  return api.get<ManagedEvent>(`${BASE}/${id}`)
}

export function createEvent(payload: EventPayload): Promise<ManagedEvent> {
  return api.post<ManagedEvent>(BASE, payload)
}

export function updateEvent(id: string, payload: EventPayload): Promise<ManagedEvent> {
  return api.patch<ManagedEvent>(`${BASE}/${id}`, payload)
}

export function deleteEvent(id: number): Promise<void> {
  return api.delete<void>(`${BASE}/${id}`)
}

export function publishEvent(id: number): Promise<ManagedEvent> {
  return api.post<ManagedEvent>(`${BASE}/${id}/publish`)
}

export function cancelEvent(id: number): Promise<ManagedEvent> {
  return api.post<ManagedEvent>(`${BASE}/${id}/cancel`)
}

export function createTicketType(eventId: string, payload: TicketTypePayload): Promise<ManagedTicketType> {
  return api.post<ManagedTicketType>(`${BASE}/${eventId}/ticket-types`, payload)
}

export function updateTicketType(
  eventId: string,
  ticketTypeId: number,
  payload: TicketTypePayload,
): Promise<ManagedTicketType> {
  return api.patch<ManagedTicketType>(`${BASE}/${eventId}/ticket-types/${ticketTypeId}`, payload)
}

export function deleteTicketType(eventId: string, ticketTypeId: number): Promise<void> {
  return api.delete<void>(`${BASE}/${eventId}/ticket-types/${ticketTypeId}`)
}
