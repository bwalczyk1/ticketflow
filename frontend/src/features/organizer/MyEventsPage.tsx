import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import type { EventStatus, ManagedEvent } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage } from '../../components/StatusPages'
import { eventsKey } from '../catalog/catalogApi'
import { formatDateTime } from '../../lib/dates'
import { getAllowedActions } from './allowedActions'
import { StatusBadge } from './eventStatus'
import {
  cancelEvent,
  deleteEvent,
  fetchMyEvents,
  organizerKey,
  publishEvent,
} from './organizerApi'

const STATUS_FILTERS: { value: EventStatus | ''; label: string }[] = [
  { value: '', label: 'Wszystkie' },
  { value: 'DRAFT', label: 'Szkice' },
  { value: 'PUBLISHED', label: 'Opublikowane' },
  { value: 'FINISHED', label: 'Zakończone' },
  { value: 'CANCELLED', label: 'Odwołane' },
]

export function MyEventsPage() {
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const status = STATUS_FILTERS.find(
    (filter) => filter.value !== '' && filter.value === params.get('status'),
  )?.value as EventStatus | undefined
  const page = Number(params.get('page')) || 1

  const { data, error, isPending } = useQuery({
    queryKey: [...organizerKey, 'events', status, page],
    queryFn: () => fetchMyEvents(status, page),
    placeholderData: (previous) => previous,
  })

  const actionMutation = useMutation({
    mutationFn: (action: () => Promise<unknown>) => action(),
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: organizerKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ]),
  })

  function run(confirmation: string | null, action: () => Promise<unknown>) {
    if (confirmation && !window.confirm(confirmation)) {
      return
    }

    actionMutation.mutate(action)
  }

  function changeStatus(value: string) {
    setParams(value ? { status: value } : {})
  }

  function changePage(next: number) {
    const nextParams = new URLSearchParams(params)

    nextParams.set('page', String(next))
    setParams(nextParams)
  }

  function renderActions(event: ManagedEvent) {
    const allowed = getAllowedActions(event.status)
    const busy = actionMutation.isPending

    return (
      <div className="actions">
        <Link className="button button--secondary" to={`/organizer/events/${event.id}/ticket-types`}>
          Pule biletów
        </Link>

        {allowed.edit && (
          <Link className="button button--secondary" to={`/organizer/events/${event.id}/edit`}>
            Edytuj
          </Link>
        )}

        {allowed.publish && (
          <button
            className="button"
            type="button"
            disabled={busy}
            onClick={() => run(null, () => publishEvent(event.id))}
          >
            Opublikuj
          </button>
        )}

        {allowed.cancel && (
          <button
            className="button button--danger"
            type="button"
            disabled={busy}
            onClick={() =>
              run(`Odwołać wydarzenie „${event.title}”? Tej operacji nie można cofnąć.`, () =>
                cancelEvent(event.id),
              )
            }
          >
            Odwołaj
          </button>
        )}

        {allowed.delete && (
          <button
            className="button button--danger"
            type="button"
            disabled={busy}
            onClick={() =>
              run(`Usunąć wydarzenie „${event.title}”? Tej operacji nie można cofnąć.`, () =>
                deleteEvent(event.id),
              )
            }
          >
            Usuń
          </button>
        )}
      </div>
    )
  }

  return (
    <section>
      <div className="page-head">
        <h1>Moje wydarzenia</h1>
        <Link className="button" to="/organizer/events/new">
          Nowe wydarzenie
        </Link>
      </div>

      <div className="field field--inline">
        <label htmlFor="status-filter">Status</label>
        <select
          id="status-filter"
          value={status ?? ''}
          onChange={(event) => changeStatus(event.target.value)}
        >
          {STATUS_FILTERS.map((filter) => (
            <option key={filter.value} value={filter.value}>
              {filter.label}
            </option>
          ))}
        </select>
      </div>

      <ErrorBanner error={error} />
      <ErrorBanner error={actionMutation.error} />

      {isPending && !error && <LoadingPage />}

      {data && data.data.length === 0 && (
        <p className="muted">Nie masz jeszcze żadnych wydarzeń w tym widoku.</p>
      )}

      {data && data.data.length > 0 && (
        <>
          <ul className="item-list">
            {data.data.map((event) => (
              <li key={event.id} className="card">
                <div className="item-list__head">
                  <h2>{event.title}</h2>
                  <StatusBadge status={event.status} />
                </div>
                <p className="muted">
                  {formatDateTime(event.startsAt, event.timezone)} · {event.venue}, {event.city}
                </p>
                {renderActions(event)}
              </li>
            ))}
          </ul>
          <Pagination meta={data.meta} onPageChange={changePage} />
        </>
      )}
    </section>
  )
}
