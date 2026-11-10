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
}

export interface ManagedEvent extends Omit<CatalogEvent, 'ticketTypes'> {
  ticketTypes?: ManagedTicketType[]
}
