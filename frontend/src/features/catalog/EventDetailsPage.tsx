import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { ApiError } from '../../api/errors'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { formatPrice } from '../../lib/money'
import { eventsKey, fetchEvent } from './catalogApi'

export function EventDetailsPage() {
  const { id = '' } = useParams()

  const { data: event, error, isPending } = useQuery({
    queryKey: [...eventsKey, 'details', id],
    queryFn: () => fetchEvent(id),
  })

  if (error instanceof ApiError && error.status === 404) {
    return <NotFoundPage />
  }

  if (error) {
    return <ErrorBanner error={error} />
  }

  if (isPending) {
    return <LoadingPage />
  }

  const ticketTypes = event.ticketTypes ?? []

  return (
    <article>
      <p>
        <Link to="/">← Wszystkie wydarzenia</Link>
      </p>

      <h1>{event.title}</h1>

      <p className="muted">
        {formatDateTime(event.startsAt, event.timezone)} – {formatDateTime(event.endsAt, event.timezone)}
      </p>
      <p>
        {event.venue}, {event.city} <span className="badge">{event.category}</span>
      </p>
      <p className="event-description">{event.description}</p>

      <h2>Bilety</h2>

      {ticketTypes.length === 0 && <p className="muted">Brak biletów w sprzedaży.</p>}

      {ticketTypes.length > 0 && (
        <ul className="ticket-list">
          {ticketTypes.map((ticketType) => (
            <li key={ticketType.id} className="card ticket-list__item">
              <div>
                <strong>{ticketType.name}</strong>
                <div className="muted">
                  {ticketType.available > 0
                    ? `Dostępne: ${ticketType.available}`
                    : 'Wyprzedane'}
                </div>
              </div>
              <strong>{formatPrice(ticketType.price)}</strong>
              {/* WAVE 3: the buy action (quantity picker and "Kup bilet") goes here. */}
            </li>
          ))}
        </ul>
      )}
    </article>
  )
}
