import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError } from '../../api/errors'
import { BackLink } from '../../components/BackLink'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { TicketSelection } from '../checkout/TicketSelection'
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

      <TicketSelection event={event} />
    </article>
  )
}
