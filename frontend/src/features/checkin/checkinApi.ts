import { api } from '../../api/client'
import type { CheckedInTicket, ManagedEvent, Paginated } from '../../api/types'

export const checkinKey = ['check-in']

/** An organizer has far fewer open events than this, so one page covers the picker. */
const EVENTS_LIMIT = 100

export function fetchCheckInEvents(): Promise<Paginated<ManagedEvent>> {
  return api.get<Paginated<ManagedEvent>>('/api/organizer/events', { status: 'PUBLISHED', limit: EVENTS_LIMIT })
}

/** 404 TICKET_NOT_FOUND, 409 TICKET_ALREADY_USED, TICKET_VOID or CHECK_IN_CLOSED. */
export function checkInTicket(eventId: number, code: string): Promise<{ ticket: CheckedInTicket }> {
  return api.post<{ ticket: CheckedInTicket }>(`/api/organizer/events/${eventId}/check-in`, { code })
}
