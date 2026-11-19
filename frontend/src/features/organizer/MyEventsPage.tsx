import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
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
import { ConfirmDialog } from '../../components/ConfirmButton'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { eventsKey } from '../catalog/catalogApi'
import { formatDateTime } from '../../lib/dates'
import { EventActionsMenu, type ConfirmedAction } from './EventActionsMenu'
import { StatusBadge } from './eventStatus'
import {
  cancelEvent,
  deleteEvent,
  fetchMyEvents,
  organizerKey,
  publishEvent,
} from './organizerApi'

const IRREVERSIBLE = 'Tej operacji nie można cofnąć.'

const CONFIRM_TEXTS: Record<ConfirmedAction, { label: string; title: string; description: string; done: string }> = {
  cancel: {
    label: 'Odwołaj',
    title: 'Odwołać wydarzenie',
    description: `Wszystkie opłacone zamówienia zostaną zwrócone, bilety unieważnione, a uczestnicy dostaną wiadomość e-mail. ${IRREVERSIBLE}`,
    done: 'Odwołano wydarzenie',
  },
  delete: { label: 'Usuń', title: 'Usunąć wydarzenie', description: IRREVERSIBLE, done: 'Usunięto wydarzenie' },
}

interface EventAction {
  run: () => Promise<unknown>
  /** Shown in a toast once the action succeeds. */
  done: string
}

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
  const [confirm, setConfirm] = useState<{ action: ConfirmedAction; event: ManagedEvent } | null>(null)

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...organizerKey, 'events', status, page],
    queryFn: () => fetchMyEvents(status, page),
    placeholderData: (previous) => previous,
  })

  const actionMutation = useMutation({
    mutationFn: ({ run }: EventAction) => run(),
    onSuccess: (_, { done }) => toast.success(done),
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

  function openConfirm(action: ConfirmedAction, event: ManagedEvent) {
    // The banner of the previous attempt would otherwise look like the answer to this one.
    actionMutation.reset()
    setConfirm({ action, event })
  }

  function runConfirmed() {
    if (!confirm) {
      return
    }

    const { action, event } = confirm

    actionMutation.mutate({
      run: () => (action === 'cancel' ? cancelEvent(event.id) : deleteEvent(event.id)),
      done: `${CONFIRM_TEXTS[action].done} „${event.title}”.`,
    })
  }

  function renderActions(event: ManagedEvent) {
    return (
      <EventActionsMenu
        event={event}
        disabled={actionMutation.isPending}
        onPublish={() =>
          actionMutation.mutate({
            run: () => publishEvent(event.id),
            done: `Opublikowano wydarzenie „${event.title}”.`,
          })
        }
        onConfirm={(action) => openConfirm(action, event)}
      />
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

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && (
          <p className="text-muted-foreground">Nie masz jeszcze żadnych wydarzeń w tym widoku.</p>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="hidden rounded-xl border md:block">
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
                      <TableCell className="pl-4 font-medium whitespace-normal">{event.title}</TableCell>
                      <TableCell>{formatDateTime(event.startsAt, event.timezone)}</TableCell>
                      <TableCell className="whitespace-normal">
                        {event.venue}, {event.city}
                      </TableCell>
                      <TableCell>
                        <StatusBadge status={event.status} />
                      </TableCell>
                      <TableCell className="pr-4 text-right">{renderActions(event)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <ul className="grid gap-3 md:hidden">
              {data.data.map((event) => (
                <li key={event.id}>
                  <Card className="py-4">
                    <CardContent className="grid gap-2 px-4">
                      <div className="flex items-start justify-between gap-2">
                        <p className="min-w-0 font-medium break-words">{event.title}</p>
                        {renderActions(event)}
                      </div>

                      <p className="text-sm text-muted-foreground">
                        {formatDateTime(event.startsAt, event.timezone)} · {event.venue}, {event.city}
                      </p>

                      <div>
                        <StatusBadge status={event.status} />
                      </div>
                    </CardContent>
                  </Card>
                </li>
              ))}
            </ul>

            <Pagination meta={data.meta} onPageChange={changePage} />
          </>
        )}
      </RefreshingArea>

      {confirm && (
        <ConfirmDialog
          open
          onOpenChange={(open) => !open && setConfirm(null)}
          label={CONFIRM_TEXTS[confirm.action].label}
          title={`${CONFIRM_TEXTS[confirm.action].title} „${confirm.event.title}”?`}
          description={CONFIRM_TEXTS[confirm.action].description}
          onConfirm={runConfirmed}
        />
      )}
    </section>
  )
}
