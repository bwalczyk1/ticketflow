import { api } from '../../api/client'
import type { Order, Paginated, WaitlistEntry, WaitlistStatus } from '../../api/types'

export const waitlistKey = ['waitlist']

export const WAITLIST_PAGE_SIZE = 10

/** The most entries one request returns; an attendee has far fewer, so one page covers them. */
const ALL_ENTRIES_LIMIT = 100

export function fetchWaitlist(status: WaitlistStatus | undefined, page: number): Promise<Paginated<WaitlistEntry>> {
  return api.get<Paginated<WaitlistEntry>>('/api/waitlist', { status, page, limit: WAITLIST_PAGE_SIZE })
}

/** All entries of the attendee, to tell which ticket types they already wait for. */
export function fetchAllEntries(): Promise<Paginated<WaitlistEntry>> {
  return api.get<Paginated<WaitlistEntry>>('/api/waitlist', { limit: ALL_ENTRIES_LIMIT })
}

/** 409 WAITLIST_NOT_NEEDED, ALREADY_ON_WAITLIST, SALES_CLOSED or TICKET_LIMIT_EXCEEDED. */
export function joinWaitlist(ticketTypeId: number, quantity: number): Promise<WaitlistEntry> {
  return api.post<WaitlistEntry>(`/api/ticket-types/${ticketTypeId}/waitlist`, { quantity })
}

export function leaveWaitlist(ticketTypeId: number): Promise<void> {
  return api.delete<void>(`/api/ticket-types/${ticketTypeId}/waitlist`)
}

/** Turns an offer into a PENDING order with the usual 15 minute hold. 410 when the offer ran out. */
export function acceptOffer(entryId: number): Promise<Order> {
  return api.post<Order>(`/api/waitlist/${entryId}/accept`)
}
