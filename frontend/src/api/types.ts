/**
 * Short names for the types generated from docs/openapi.json (`npm run api:types`).
 * Regenerate them after the API changes; the compiler then shows where the UI no longer fits.
 */
import type { components } from './schema'

type Schemas = components['schemas']

export type Role = Schemas['Role']
export type User = Schemas['UserResource']

/** The list envelope every paginated endpoint returns. */
export interface Paginated<T> {
  data: T[]
  meta: {
    page: number
    limit: number
    total: number
    totalPages: number
  }
}

/** One invalid field of a VALIDATION_ERROR. */
export type FieldError = Schemas['ErrorDetail']

/** The body of every error response: `{ error: { code, message, details?, requestId } }`. */
export type ApiErrorBody = Schemas['ErrorResponse']

export type EventStatus = Schemas['EventStatus']

/** A ticket type as the public catalog shows it. Prices are integer grosze. */
export type TicketType = Schemas['TicketTypeResource']

/** A ticket type with the sales counters only its organizer sees. */
export type ManagedTicketType = Schemas['TicketTypeManagementResource']

export type CatalogEvent = Schemas['EventResource']

/** GET /api/events/{id}: the catalog event with the signed-in attendee's purchase limit. */
export type EventDetails = Schemas['EventDetailsResource']

/** How many tickets the attendee may hold for one event and how many they already have. */
export type PurchaseLimit = NonNullable<EventDetails['purchaseLimit']>

export type ManagedEvent = Schemas['EventManagementResource']

export type OrderStatus = Schemas['OrderStatus']
export type Order = Schemas['OrderResource']
export type RefundStatus = Schemas['RefundStatus']

export type TicketStatus = Schemas['TicketStatus']
export type Ticket = Schemas['TicketResource']

export type WaitlistStatus = Schemas['WaitlistStatus']
export type WaitlistEntry = Schemas['WaitlistEntryResource']

export type CheckedInTicket = Schemas['CheckedInTicketResource']

export type SalesSummary = Schemas['TicketSalesResource']

export type AdminEvent = Schemas['AdminEventResource']

// Request bodies.
export type LoginPayload = Schemas['LoginRequest']
export type RegisterPayload = Schemas['RegisterRequest']
export type EventPayload = Schemas['StoreEventRequest']
/** Prices in grosze. */
export type TicketTypePayload = Schemas['StoreTicketTypeRequest']
export type OrderItemInput = Schemas['StoreOrderRequest']['items'][number]
