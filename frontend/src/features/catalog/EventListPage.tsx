import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import type { CatalogEvent } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { formatPrice, plnToGrosze } from '../../lib/money'
import { eventsKey, fetchEvents, type CatalogFilters } from './catalogApi'
import { EventFilters } from './EventFilters'

/** Translates the URL (dates and PLN as typed) into what the API expects. */
function toApiFilters(params: URLSearchParams): CatalogFilters {
  const from = params.get('from')
  const to = params.get('to')
  const priceMax = plnToGrosze(params.get('priceMax') ?? '')

  return {
    q: params.get('q') ?? undefined,
    city: params.get('city') ?? undefined,
    category: params.get('category') ?? undefined,
    from: from ? `${from}T00:00:00Z` : undefined,
    to: to ? `${to}T23:59:59Z` : undefined,
    price_max: priceMax ?? undefined,
    sort: params.get('sort') ?? undefined,
    page: Number(params.get('page')) || undefined,
  }
}

function EventCard({ event }: { event: CatalogEvent }) {
  const prices = (event.ticketTypes ?? []).map((ticketType) => ticketType.price)

  return (
    <li className="card event-card">
      <h2>
        <Link to={`/events/${event.id}`}>{event.title}</Link>
      </h2>
      <p className="muted">{formatDateTime(event.startsAt, event.timezone)}</p>
      <p>
        {event.venue}, {event.city}
      </p>
      <p className="event-card__footer">
        <span className="badge">{event.category}</span>
        {prices.length > 0 && <strong>od {formatPrice(Math.min(...prices))}</strong>}
      </p>
    </li>
  )
}

export function EventListPage() {
  const [params, setParams] = useSearchParams()
  const filters = toApiFilters(params)

  const { data, error, isPending } = useQuery({
    queryKey: [...eventsKey, 'list', filters],
    queryFn: () => fetchEvents(filters),
    placeholderData: (previous) => previous,
  })

  function changePage(page: number) {
    const next = new URLSearchParams(params)

    next.set('page', String(page))
    setParams(next)
  }

  return (
    <section>
      <h1>Wydarzenia</h1>

      {/* The key remounts the form when the URL changes, e.g. after "Wyczyść" or Back. */}
      <EventFilters key={params.toString()} params={params} onApply={setParams} />

      <ErrorBanner error={error} />

      {isPending && !error && <LoadingPage />}

      {data && data.data.length === 0 && (
        <p className="muted">Nie znaleziono wydarzeń pasujących do filtrów.</p>
      )}

      {data && data.data.length > 0 && (
        <>
          <p className="muted">Znaleziono: {data.meta.total}</p>
          <ul className="event-grid">
            {data.data.map((event) => (
              <EventCard key={event.id} event={event} />
            ))}
          </ul>
          <Pagination meta={data.meta} onPageChange={changePage} />
        </>
      )}
    </section>
  )
}
