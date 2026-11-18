import { api } from '../../api/client'
import type { CatalogEvent, Paginated } from '../../api/types'

export const PAGE_SIZE = 9

export interface CatalogFilters {
  q?: string
  city?: string
  category?: string
  from?: string
  to?: string
  /** Already in grosze, unlike the PLN text the user types. */
  price_max?: number
  sort?: string
  page?: number
}

export const eventsKey = ['events']

export function fetchEvents(filters: CatalogFilters): Promise<Paginated<CatalogEvent>> {
  return api.get<Paginated<CatalogEvent>>('/api/events', { ...filters, limit: PAGE_SIZE })
}

export function fetchEvent(id: string): Promise<CatalogEvent> {
  return api.get<CatalogEvent>(`/api/events/${id}`)
}
