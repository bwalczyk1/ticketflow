import { useQuery } from '@tanstack/react-query'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { StatusBadge } from '../organizer/eventStatus'
import { adminKey, fetchAdminEvents } from './adminApi'

interface AdminEventsProps {
  params: URLSearchParams
  onParamsChange: (params: URLSearchParams) => void
}

export function AdminEvents({ params, onParamsChange }: AdminEventsProps) {
  const page = Number(params.get('page')) || 1

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...adminKey, 'events', page],
    queryFn: () => fetchAdminEvents(page),
    placeholderData: (previous) => previous,
  })

  function changePage(next: number) {
    const nextParams = new URLSearchParams(params)

    nextParams.set('page', String(next))
    onParamsChange(nextParams)
  }

  return (
    <>
      <ErrorBanner error={error} />

      {isPending && !error && <LoadingPage />}

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && <p className="text-muted-foreground">Nie ma jeszcze żadnych wydarzeń.</p>}

        {data && data.data.length > 0 && (
          <>
            <div className="hidden rounded-xl border md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-4">Wydarzenie</TableHead>
                    <TableHead>Organizator</TableHead>
                    <TableHead>Data</TableHead>
                    <TableHead>Miejsce</TableHead>
                    <TableHead className="pr-4">Status</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {data.data.map((event) => (
                    <TableRow key={event.id}>
                      <TableCell className="pl-4 font-medium whitespace-normal">{event.title}</TableCell>
                      <TableCell className="whitespace-normal">
                        {event.organizer.name}
                        <p className="text-sm text-muted-foreground">{event.organizer.email}</p>
                      </TableCell>
                      <TableCell>{formatDateTime(event.startsAt, event.timezone)}</TableCell>
                      <TableCell className="whitespace-normal">
                        {event.venue}, {event.city}
                      </TableCell>
                      <TableCell className="pr-4">
                        <StatusBadge status={event.status} />
                      </TableCell>
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
                        <StatusBadge status={event.status} />
                      </div>

                      <p className="text-sm text-muted-foreground">
                        {formatDateTime(event.startsAt, event.timezone)} · {event.venue}, {event.city}
                      </p>
                      <p className="text-sm break-all text-muted-foreground">
                        Organizator: {event.organizer.name} ({event.organizer.email})
                      </p>
                    </CardContent>
                  </Card>
                </li>
              ))}
            </ul>

            <Pagination meta={data.meta} onPageChange={changePage} />
          </>
        )}
      </RefreshingArea>
    </>
  )
}
