import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ApiError } from '../../api/errors'
import type { ManagedTicketType } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { formatPrice } from '../../lib/money'
import { eventsKey } from '../catalog/catalogApi'
import { getAllowedActions } from './allowedActions'
import { StatusBadge } from './eventStatus'
import { deleteTicketType, fetchMyEvent, organizerKey } from './organizerApi'
import { TicketTypeForm } from './TicketTypeForm'

export function TicketTypesPage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const [editingId, setEditingId] = useState<number | null>(null)
  const [newFormKey, setNewFormKey] = useState(0)

  const { data: event, error, isPending } = useQuery({
    queryKey: [...organizerKey, 'event', id],
    queryFn: () => fetchMyEvent(id),
  })

  const deleteMutation = useMutation({
    mutationFn: (ticketTypeId: number) => deleteTicketType(id, ticketTypeId),
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: organizerKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ]),
  })

  function handleDelete(ticketType: ManagedTicketType) {
    if (window.confirm(`Usunąć pulę „${ticketType.name}”? Tej operacji nie można cofnąć.`)) {
      deleteMutation.mutate(ticketType.id)
    }
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

  const ticketTypes = event.ticketTypes ?? []
  const canEdit = getAllowedActions(event.status).edit

  return (
    <section>
      <p>
        <Link to="/organizer">← Moje wydarzenia</Link>
      </p>

      <div className="item-list__head">
        <h1>Pule biletów: {event.title}</h1>
        <StatusBadge status={event.status} />
      </div>

      <ErrorBanner error={deleteMutation.error} />

      {ticketTypes.length === 0 && <p className="muted">To wydarzenie nie ma jeszcze żadnych pul.</p>}

      <ul className="item-list">
        {ticketTypes.map((ticketType) => (
          <li key={ticketType.id} className="card">
            {editingId === ticketType.id ? (
              <TicketTypeForm
                eventId={id}
                ticketType={ticketType}
                onDone={() => setEditingId(null)}
              />
            ) : (
              <>
                <div className="item-list__head">
                  <h2>{ticketType.name}</h2>
                  <strong>{formatPrice(ticketType.price)}</strong>
                </div>
                <p className="muted">
                  Pula: {ticketType.quantity} · sprzedane: {ticketType.sold} · zablokowane:{' '}
                  {ticketType.held} · dostępne: {ticketType.available}
                </p>

                {canEdit && (
                  <div className="actions">
                    <button
                      className="button button--secondary"
                      type="button"
                      onClick={() => setEditingId(ticketType.id)}
                    >
                      Edytuj
                    </button>
                    <button
                      className="button button--danger"
                      type="button"
                      disabled={deleteMutation.isPending}
                      onClick={() => handleDelete(ticketType)}
                    >
                      Usuń
                    </button>
                  </div>
                )}
              </>
            )}
          </li>
        ))}
      </ul>

      {canEdit ? (
        <div className="card">
          <h2>Nowa pula</h2>
          {/* A new key empties the form after each successful add. */}
          <TicketTypeForm
            key={newFormKey}
            eventId={id}
            onDone={() => setNewFormKey((key) => key + 1)}
          />
        </div>
      ) : (
        <p className="muted">Wydarzenie jest zakończone lub odwołane, więc pule są tylko do odczytu.</p>
      )}
    </section>
  )
}
