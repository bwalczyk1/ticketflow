export type Role = 'ADMIN' | 'ORGANIZER' | 'ATTENDEE'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Paginated<T> {
  data: T[]
  meta: {
    page: number
    limit: number
    total: number
    totalPages: number
  }
}

export interface FieldError {
  field: string
  message: string
}

export interface ApiErrorBody {
  error: {
    code: string
    message: string
    details?: FieldError[]
    requestId?: string
  }
}

export type EventStatus = 'DRAFT' | 'PUBLISHED' | 'FINISHED' | 'CANCELLED'

/** A ticket type as the public catalog shows it. Prices are integer grosze. */
export interface TicketType {
  id: number
  name: string
  price: number
  available: number
}

/** A ticket type with the sales counters only its organizer sees. */
export interface ManagedTicketType extends TicketType {
  quantity: number
  sold: number
  held: number
}

export interface CatalogEvent {
  id: number
  title: string
  description: string
  venue: string
  city: string
  category: string
  timezone: string
  startsAt: string
  endsAt: string
  status: EventStatus
  ticketTypes?: TicketType[]
  /**
   * Hand-written ahead of the backend change that adds it to GET /api/events/{id}; check against
   * the OpenAPI docs once they are regenerated. Null for guests and users who are not attendees.
   */
  purchaseLimit?: PurchaseLimit | null
}

/** How many tickets the attendee may hold for one event and how many they already have. */
export interface PurchaseLimit {
  max: number
  used: number
}

export interface ManagedEvent extends Omit<CatalogEvent, 'ticketTypes'> {
  ticketTypes?: ManagedTicketType[]
}

export type OrderStatus = 'PENDING' | 'PAID' | 'EXPIRED' | 'FAILED' | 'CANCELLED'

export type PaymentStatus = 'PENDING' | 'SUCCEEDED' | 'FAILED'

export interface OrderItem {
  ticketTypeId: number
  name: string
  quantity: number
  /** Integer grosze. */
  unitPrice: number
}

export interface Order {
  id: number
  status: OrderStatus
  /** Integer grosze. */
  total: number
  expiresAt: string
  createdAt: string
  /** Null until the payment is started. */
  paymentStatus: PaymentStatus | null
  event: {
    id: number
    title: string
    venue: string
    city: string
    timezone: string
    startsAt: string
  }
  items: OrderItem[]
  /**
   * Hand-written ahead of the backend change that adds it; check against the OpenAPI docs once
   * they are regenerated. Null until the invoice is issued, missing on an older backend.
   */
  invoice?: Invoice | null
}

/** Hand-written ahead of the backend change that adds it (see `Order.invoice`). */
export interface Invoice {
  number: string
  issuedAt: string
}

export type TicketStatus = 'VALID' | 'USED' | 'VOID'

export interface Ticket {
  id: number
  code: string
  status: TicketStatus
  /** The ticket's number within its order item, starting at 1. */
  position: number
  checkedInAt: string | null
  /** False until the PDF has been generated in the background. */
  pdfReady: boolean
  orderId: number
  ticketType: {
    id: number
    name: string
  }
  event: Order['event']
}
