import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { LoaderCircle } from 'lucide-react'
import { useParams } from 'react-router-dom'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { PAGE_TITLE } from '@/lib/styles'
import { ApiError } from '../../api/errors'
import type { Order } from '../../api/types'
import { BackLink } from '../../components/BackLink'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LoadingPage, NotFoundPage } from '../../components/StatusPages'
import { eventsKey } from '../catalog/catalogApi'
import { cancelOrder, fetchOrder, ordersKey, payOrder } from './checkoutApi'
import { OrderDocuments } from './OrderDocuments'
import { OrderItemsCard } from './OrderItemsCard'
import { OrderRefund } from './OrderRefund'
import { ExpiringNotice, OrderOutcome } from './OrderOutcome'
import { getOrderWait, isHoldOver } from './orderState'
import { ReservationCard } from './ReservationCard'
import { useNow } from './useCountdown'

// The general text speaks of events; on this page the code means the order moved on.
const ORDER_ERRORS = {
  INVALID_STATUS_TRANSITION: 'Tego zamówienia nie można już zmienić. Odśwież stronę, aby zobaczyć jego status.',
}

const POLL_INTERVAL_MS = 2000

export function OrderPage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const now = useNow()
  const orderKey = [...ordersKey, 'details', id]

  const { data: order, error, isPending } = useQuery({
    queryKey: orderKey,
    queryFn: () => fetchOrder(id),
    staleTime: 0,
    // Polls only while the page waits for the server, and React Query stops it on unmount.
    refetchInterval: (query) =>
      query.state.data && getOrderWait(query.state.data, Date.now()) ? POLL_INTERVAL_MS : false,
  })

  const wait = order ? getOrderWait(order, now) : null
  const isPaying = wait === 'payment'

  function handleSettled() {
    queryClient.invalidateQueries({ queryKey: eventsKey })
    queryClient.invalidateQueries({ queryKey: ordersKey })
  }

  const payMutation = useMutation({
    mutationFn: () => payOrder(Number(id)),
    onSuccess: (updated: Order) => queryClient.setQueryData(orderKey, updated),
    onError: () => queryClient.invalidateQueries({ queryKey: orderKey }),
    onSettled: handleSettled,
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelOrder(Number(id)),
    onSuccess: (updated: Order) => queryClient.setQueryData(orderKey, updated),
    onError: () => queryClient.invalidateQueries({ queryKey: orderKey }),
    onSettled: handleSettled,
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

  const isReservationOpen = order.status === 'PENDING' && !isHoldOver(order, now)

  return (
    <section>
      <BackLink to="/orders" label="Moje zamówienia" />

      <h1 className={`${PAGE_TITLE} mb-6`}>Zamówienie #{order.id}</h1>

      <ErrorBanner error={payMutation.error} messages={ORDER_ERRORS} />
      <ErrorBanner error={cancelMutation.error} messages={ORDER_ERRORS} />

      <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
        <div className="grid gap-4">
          <OrderItemsCard order={order} />

          {order.status === 'PAID' && <OrderDocuments order={order} />}

          <OrderRefund order={order} now={now} />

          {isPaying && (
            <Alert role="status">
              <LoaderCircle className="animate-spin" />
              <AlertTitle>Płatność w toku</AlertTitle>
              <AlertDescription>Sprawdzamy status płatności. Strona odświeży się sama.</AlertDescription>
            </Alert>
          )}
        </div>

        {isReservationOpen && (
          <ReservationCard
            order={order}
            now={now}
            isPaying={isPaying}
            isBusy={payMutation.isPending || cancelMutation.isPending}
            onPay={() => payMutation.mutate()}
            onCancelOpen={() => {
              payMutation.reset()
              cancelMutation.reset()
            }}
            onCancel={() => cancelMutation.mutate()}
          />
        )}

        {wait === 'expiry' && <ExpiringNotice />}

        {!isReservationOpen && wait !== 'expiry' && <OrderOutcome order={order} />}
      </div>
    </section>
  )
}
