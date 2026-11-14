import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError } from '../../api/errors'
import { BackLink } from '../../components/BackLink'
import { ConfirmButton } from '../../components/ConfirmButton'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { formatPrice } from '../../lib/money'
import { eventsKey } from '../catalog/catalogApi'
import { getAllowedActions } from './allowedActions'
import { StatusBadge } from './eventStatus'
import { deleteTicketType, fetchMyEvent, organizerKey } from './organizerApi'
import { TicketTypeForm } from './TicketTypeForm'

const DELETE_ERRORS = {
  POOL_PROTECTED: 'Nie można usunąć puli, w której sprzedano lub zablokowano bilety.',
}

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
      <BackLink to="/organizer" label="Moje wydarzenia" />

      <div className="mb-6 flex flex-wrap items-center gap-3">
        <h1 className={`${PAGE_TITLE} break-words`}>Pule biletów: {event.title}</h1>
        <StatusBadge status={event.status} />
      </div>

      <ErrorBanner error={deleteMutation.error} messages={DELETE_ERRORS} />

      {ticketTypes.length === 0 && (
        <p className="mb-6 text-muted-foreground">To wydarzenie nie ma jeszcze żadnych pul.</p>
      )}

      {ticketTypes.length > 0 && (
        <ul className="mb-6 grid gap-3">
          {ticketTypes.map((ticketType) => (
            <li key={ticketType.id}>
              <Card className="py-4">
                <CardContent className="px-4 sm:px-5">
                  {editingId === ticketType.id ? (
                    <TicketTypeForm
                      eventId={id}
                      ticketType={ticketType}
                      onDone={() => setEditingId(null)}
                    />
                  ) : (
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-baseline gap-x-3">
                          <h2 className="font-semibold break-words">{ticketType.name}</h2>
                          <span className="font-semibold">{formatPrice(ticketType.price)}</span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                          Pula: {ticketType.quantity} · sprzedane: {ticketType.sold} · zablokowane:{' '}
                          {ticketType.held} · dostępne: {ticketType.available}
                        </p>
                      </div>

                      {canEdit && (
                        <div className="flex gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            onClick={() => setEditingId(ticketType.id)}
                          >
                            Edytuj
                          </Button>
                          <ConfirmButton
                            label="Usuń"
                            title={`Usunąć pulę „${ticketType.name}”?`}
                            description="Tej operacji nie można cofnąć."
                            disabled={deleteMutation.isPending}
                            onOpen={() => deleteMutation.reset()}
                            onConfirm={() => deleteMutation.mutate(ticketType.id)}
                          />
                        </div>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {canEdit ? (
        <Card>
          <CardHeader>
            <CardTitle>
              <h2>Nowa pula</h2>
            </CardTitle>
          </CardHeader>
          <CardContent>
            {/* A new key empties the form after each successful add. */}
            <TicketTypeForm
              key={newFormKey}
              eventId={id}
              onDone={() => setNewFormKey((key) => key + 1)}
            />
          </CardContent>
        </Card>
      ) : (
        <p className="text-muted-foreground">
          Wydarzenie jest zakończone lub odwołane, więc pule są tylko do odczytu.
        </p>
      )}
    </section>
  )
}
