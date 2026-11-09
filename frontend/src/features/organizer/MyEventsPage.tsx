import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PAGE_TITLE } from '@/lib/styles'
import type { EventStatus, ManagedEvent } from '../../api/types'
import { ConfirmButton } from '../../components/ConfirmButton'
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

const IRREVERSIBLE = 'Tej operacji nie można cofnąć.'

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
      <div className="flex justify-end gap-2">
        <Button variant="outline" size="sm" asChild>
          <Link to={`/organizer/events/${event.id}/ticket-types`}>Pule biletów</Link>
        </Button>

        {allowed.edit && (
          <Button variant="outline" size="sm" asChild>
            <Link to={`/organizer/events/${event.id}/edit`}>Edytuj</Link>
          </Button>
        )}

        {allowed.publish && (
          <Button
            size="sm"
            type="button"
            disabled={busy}
            onClick={() => actionMutation.mutate(() => publishEvent(event.id))}
          >
            Opublikuj
          </Button>
        )}

        {allowed.cancel && (
          <ConfirmButton
            label="Odwołaj"
            title={`Odwołać wydarzenie „${event.title}”?`}
            description={IRREVERSIBLE}
            disabled={busy}
            onConfirm={() => actionMutation.mutate(() => cancelEvent(event.id))}
          />
        )}

        {allowed.delete && (
          <ConfirmButton
            label="Usuń"
            title={`Usunąć wydarzenie „${event.title}”?`}
            description={IRREVERSIBLE}
            disabled={busy}
            onConfirm={() => actionMutation.mutate(() => deleteEvent(event.id))}
          />
        )}
      </div>
    )
  }

  return (
    <section>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className={PAGE_TITLE}>Moje wydarzenia</h1>
        <Button asChild>
          <Link to="/organizer/events/new">
            <Plus />
            Nowe wydarzenie
          </Link>
        </Button>
      </div>

      <Tabs className="mb-4 max-w-full overflow-x-auto" value={status ?? ''} onValueChange={changeStatus}>
        <TabsList aria-label="Status">
          {STATUS_FILTERS.map((filter) => (
            <TabsTrigger key={filter.value} value={filter.value}>
              {filter.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <ErrorBanner error={error} />
      <ErrorBanner error={actionMutation.error} />

      {isPending && !error && <LoadingPage />}

      {data && data.data.length === 0 && (
        <p className="text-muted-foreground">Nie masz jeszcze żadnych wydarzeń w tym widoku.</p>
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="rounded-xl border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="pl-4">Wydarzenie</TableHead>
                  <TableHead>Data</TableHead>
                  <TableHead>Miejsce</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="pr-4 text-right">Akcje</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.data.map((event) => (
                  <TableRow key={event.id}>
                    <TableCell className="pl-4 font-medium">{event.title}</TableCell>
                    <TableCell>{formatDateTime(event.startsAt, event.timezone)}</TableCell>
                    <TableCell>
                      {event.venue}, {event.city}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={event.status} />
                    </TableCell>
                    <TableCell className="pr-4">{renderActions(event)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <Pagination meta={data.meta} onPageChange={changePage} />
        </>
      )}
    </section>
  )
}
