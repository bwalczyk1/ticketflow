import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
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
import { PAGE_TITLE } from '@/lib/styles'
import type { Order } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { formatDateTime } from '../../lib/dates'
import { formatPrice } from '../../lib/money'
import { fetchOrders, ordersKey } from './checkoutApi'
import { isReservationActive } from './orderState'
import { OrderStatusBadge } from './OrderStatusBadge'
import { useNow } from './useCountdown'

function OrderAction({ order, now }: { order: Order; now: number }) {
  const isActive = isReservationActive(order, now)

  return (
    <Button variant={isActive ? 'default' : 'outline'} size="sm" asChild>
      <Link to={`/orders/${order.id}`}>{isActive ? 'Dokończ płatność' : 'Szczegóły'}</Link>
    </Button>
  )
}

function itemsSummary(order: Order): string {
  return order.items.map((item) => `${item.quantity} × ${item.name}`).join(', ')
}

export function OrdersPage() {
  const now = useNow()
  const [params, setParams] = useSearchParams()
  const page = Number(params.get('page')) || 1

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...ordersKey, 'list', page],
    queryFn: () => fetchOrders(page),
    staleTime: 0,
    placeholderData: (previous) => previous,
  })

  function changePage(next: number) {
    setParams({ page: String(next) })
  }

  return (
    <section>
      <h1 className={`${PAGE_TITLE} mb-6`}>Moje zamówienia</h1>

      <ErrorBanner error={error} />

      {isPending && !error && <LoadingPage />}

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && (
          <p className="text-muted-foreground">
            Nie masz jeszcze zamówień.{' '}
            <Link className="underline" to="/">
              Wybierz wydarzenie
            </Link>
          </p>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="hidden rounded-xl border md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-4">Zamówienie</TableHead>
                    <TableHead>Wydarzenie</TableHead>
                    <TableHead>Złożone</TableHead>
                    <TableHead className="text-right">Kwota</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="pr-4 text-right">Akcje</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {data.data.map((order) => (
                    <TableRow key={order.id}>
                      <TableCell className="pl-4 font-medium">#{order.id}</TableCell>
                      <TableCell className="whitespace-normal">
                        <p className="font-medium">{order.event.title}</p>
                        <p className="text-sm text-muted-foreground">{itemsSummary(order)}</p>
                      </TableCell>
                      <TableCell>{formatDateTime(order.createdAt)}</TableCell>
                      <TableCell className="text-right">{formatPrice(order.total)}</TableCell>
                      <TableCell>
                        <OrderStatusBadge status={order.status} />
                      </TableCell>
                      <TableCell className="pr-4 text-right">
                        <OrderAction order={order} now={now} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <ul className="grid gap-3 md:hidden">
              {data.data.map((order) => (
                <li key={order.id}>
                  <Card className="py-4">
                    <CardContent className="grid gap-3 px-4">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-medium">Zamówienie #{order.id}</span>
                        <OrderStatusBadge status={order.status} />
                      </div>

                      <div>
                        <p className="font-medium break-words">{order.event.title}</p>
                        <p className="text-sm text-muted-foreground">{itemsSummary(order)}</p>
                        <p className="text-sm text-muted-foreground">{formatDateTime(order.createdAt)}</p>
                      </div>

                      <div className="flex items-center justify-between gap-2">
                        <span className="font-semibold">{formatPrice(order.total)}</span>
                        <OrderAction order={order} now={now} />
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
    </section>
  )
}
