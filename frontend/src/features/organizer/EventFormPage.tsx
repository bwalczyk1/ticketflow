import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError, getFieldErrors } from '../../api/errors'
import type { ManagedEvent } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { FormField } from '../../components/FormField'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { TextAreaField } from '../../components/TextAreaField'
import { CATEGORY_SUGGESTIONS } from '../../lib/categories'
import { isValidTimeZone, utcIsoToZonedLocal, zonedLocalToUtcIso } from '../../lib/dates'
import { eventsKey } from '../catalog/catalogApi'
import { getAllowedActions } from './allowedActions'
import {
  createEvent,
  fetchMyEvent,
  organizerKey,
  updateEvent,
  type EventPayload,
} from './organizerApi'

const DEFAULT_TIME_ZONE = 'Europe/Warsaw'

interface EventFormProps {
  event?: ManagedEvent
}

function EventForm({ event }: EventFormProps) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: (payload: EventPayload) =>
      event ? updateEvent(String(event.id), payload) : createEvent(payload),
    onSuccess: async (saved) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: organizerKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ])

      navigate(event ? '/organizer' : `/organizer/events/${saved.id}/ticket-types`)
    },
  })

  const fieldErrors = { ...getFieldErrors(mutation.error), ...clientErrors }
  const timeZone = event?.timezone ?? DEFAULT_TIME_ZONE

  function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()

    const form = new FormData(submitEvent.currentTarget)
    const text = (name: string) => String(form.get(name) ?? '').trim()
    const zone = text('timezone')
    const errors: Record<string, string> = {}

    if (!isValidTimeZone(zone)) {
      errors.timezone = 'Podaj poprawną strefę czasową, np. Europe/Warsaw.'
    }

    const startsAt = errors.timezone ? null : zonedLocalToUtcIso(text('startsAt'), zone)
    const endsAt = errors.timezone ? null : zonedLocalToUtcIso(text('endsAt'), zone)

    if (!errors.timezone && !startsAt) {
      errors.startsAt = 'Podaj datę. Ta godzina nie istnieje w wybranej strefie (zmiana czasu).'
    }

    if (!errors.timezone && !endsAt) {
      errors.endsAt = 'Podaj datę. Ta godzina nie istnieje w wybranej strefie (zmiana czasu).'
    }

    setClientErrors(errors)

    if (Object.keys(errors).length > 0 || !startsAt || !endsAt) {
      return
    }

    mutation.mutate({
      title: text('title'),
      description: text('description'),
      venue: text('venue'),
      city: text('city'),
      category: text('category'),
      timezone: zone,
      startsAt,
      endsAt,
    })
  }

  return (
    <Card className="max-w-3xl">
      <CardContent>
        <ErrorBanner error={mutation.error} />

        <form className="grid gap-4 sm:grid-cols-2" onSubmit={handleSubmit}>
          <div className="sm:col-span-2">
            <FormField
              label="Tytuł"
              name="title"
              defaultValue={event?.title}
              maxLength={160}
              required
              error={fieldErrors.title}
            />
          </div>
          <div className="sm:col-span-2">
            <TextAreaField
              label="Opis"
              name="description"
              defaultValue={event?.description}
              maxLength={5000}
              required
              error={fieldErrors.description}
            />
          </div>
          <FormField
            label="Miejsce"
            name="venue"
            defaultValue={event?.venue}
            maxLength={160}
            required
            error={fieldErrors.venue}
          />
          <FormField
            label="Miasto"
            name="city"
            defaultValue={event?.city}
            maxLength={100}
            required
            error={fieldErrors.city}
          />
          <FormField
            label="Kategoria"
            name="category"
            list="category-suggestions"
            defaultValue={event?.category}
            maxLength={50}
            required
            error={fieldErrors.category}
          />
          <datalist id="category-suggestions">
            {CATEGORY_SUGGESTIONS.map((category) => (
              <option key={category} value={category} />
            ))}
          </datalist>
          <FormField
            label="Strefa czasowa"
            name="timezone"
            defaultValue={timeZone}
            hint="Daty poniżej są podawane w tej strefie (nazwa IANA, np. Europe/Warsaw)."
            required
            error={fieldErrors.timezone}
          />
          <FormField
            label="Początek"
            name="startsAt"
            type="datetime-local"
            defaultValue={event ? utcIsoToZonedLocal(event.startsAt, timeZone) : undefined}
            required
            error={fieldErrors.startsAt}
          />
          <FormField
            label="Koniec"
            name="endsAt"
            type="datetime-local"
            defaultValue={event ? utcIsoToZonedLocal(event.endsAt, timeZone) : undefined}
            required
            error={fieldErrors.endsAt}
          />

          <div className="flex gap-2 sm:col-span-2">
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? 'Zapisywanie…' : 'Zapisz'}
            </Button>
            <Button variant="outline" asChild>
              <Link to="/organizer">Anuluj</Link>
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

export function EventFormPage() {
  const { id } = useParams()

  const { data: event, error, isPending } = useQuery({
    queryKey: [...organizerKey, 'event', id],
    queryFn: () => fetchMyEvent(id ?? ''),
    enabled: id !== undefined,
    // The form copies the values once, so it must always start from fresh data.
    gcTime: 0,
  })

  if (id === undefined) {
    return (
      <section>
        <h1 className={`${PAGE_TITLE} mb-6`}>Nowe wydarzenie</h1>
        <EventForm />
      </section>
    )
  }

  if (error instanceof ApiError && (error.status === 404 || error.status === 403)) {
    return <NotFoundPage />
  }

  if (error) {
    return <ErrorBanner error={error} />
  }

  if (isPending) {
    return <LoadingPage />
  }

  return (
    <section>
      <h1 className={`${PAGE_TITLE} mb-6 break-words`}>Edycja: {event.title}</h1>

      {getAllowedActions(event.status).edit ? (
        <EventForm event={event} />
      ) : (
        <p className="text-muted-foreground">To wydarzenie jest zakończone lub odwołane, więc nie można go edytować.</p>
      )}
    </section>
  )
}
