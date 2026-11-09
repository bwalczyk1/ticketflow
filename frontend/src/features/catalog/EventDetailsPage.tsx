import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError } from '../../api/errors'
import { BackLink } from '../../components/BackLink'
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
      <BackLink to="/" label="Wszystkie wydarzenia" />

      <h1 className={`${PAGE_TITLE} break-words`}>{event.title}</h1>

      <p className="mt-2 text-muted-foreground">
        {formatDateTime(event.startsAt, event.timezone)} – {formatDateTime(event.endsAt, event.timezone)}
      </p>
      <p className="mt-1 flex flex-wrap items-center gap-2">
        {event.venue}, {event.city} <Badge variant="secondary">{event.category}</Badge>
      </p>
      <p className="mt-6 max-w-prose whitespace-pre-line break-words">{event.description}</p>

      <h2 className="mt-8 mb-4 text-xl font-semibold tracking-tight">Bilety</h2>

      {ticketTypes.length === 0 && <p className="text-muted-foreground">Brak biletów w sprzedaży.</p>}

      {ticketTypes.length > 0 && (
        <ul className="grid gap-3">
          {ticketTypes.map((ticketType) => (
            <li key={ticketType.id}>
              <Card className="py-4">
                <CardContent className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 sm:px-5">
                  <div>
                    <p className="font-medium">{ticketType.name}</p>
                    <p className="text-sm text-muted-foreground">
                      {ticketType.available > 0
                        ? `Dostępne: ${ticketType.available}`
                        : 'Wyprzedane'}
                    </p>
                  </div>
                  <p className="font-semibold">{formatPrice(ticketType.price)}</p>
                  {/* WAVE 3: the buy action (quantity picker and "Kup bilet") goes here. */}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </article>
  )
}
