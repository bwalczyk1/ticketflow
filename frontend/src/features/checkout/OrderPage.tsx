import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { LoaderCircle } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
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
import { getOrderWait, isHoldOver, type OrderWait } from './orderState'
import { ReservationCard } from './ReservationCard'
import { useNow } from './useCountdown'

// The general text speaks of events; on this page the code means the order moved on.
const ORDER_ERRORS = {
  INVALID_STATUS_TRANSITION: 'Tego zamówienia nie można już zmienić. Odśwież stronę, aby zobaczyć jego status.',
}

/** How often and how long the page asks for the order while it waits for the server. */
const POLLING: Record<OrderWait, { interval: number; timeout: number }> = {
  payment: { interval: 1500, timeout: 30_000 },
  // The scheduler expires holds once a minute, so this waits for up to two ticks.
  expiry: { interval: 5000, timeout: 120_000 },
  invoice: { interval: 3000, timeout: 60_000 },
  refund: { interval: 2000, timeout: 60_000 },
  correction: { interval: 3000, timeout: 60_000 },
}

export function OrderPage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const now = useNow()
  const [timedOutWait, setTimedOutWait] = useState<OrderWait | null>(null)
  const orderKey = [...ordersKey, 'details', id]

  const { data: order, error, isPending } = useQuery({
    queryKey: orderKey,
    queryFn: () => fetchOrder(id),
    staleTime: 0,
    // Polling runs only while the page waits for the server, and React Query stops it on unmount.
    refetchInterval: (query) => {
      const current = query.state.data
      const currentWait = current ? getOrderWait(current, Date.now()) : null

      return currentWait && currentWait !== timedOutWait ? POLLING[currentWait].interval : false
    },
  })

  const wait = order ? getOrderWait(order, now) : null
  const isPaying = wait === 'payment'
  const pollingTimedOut = wait !== null && wait === timedOutWait

  useEffect(() => {
    if (!wait || wait === timedOutWait) {
      return
    }

    const timer = setTimeout(() => setTimedOutWait(wait), POLLING[wait].timeout)

    return () => clearTimeout(timer)
  }, [wait, timedOutWait])

  function checkAgain() {
    setTimedOutWait(null)
    queryClient.invalidateQueries({ queryKey: orderKey })
  }

  function handleSettled() {
    queryClient.invalidateQueries({ queryKey: eventsKey })
    queryClient.invalidateQueries({ queryKey: ordersKey })
  }

  const payMutation = useMutation({
    mutationFn: () => payOrder(Number(id)),
    onSuccess: (updated: Order) => {
      setTimedOutWait(null)
      queryClient.setQueryData(orderKey, updated)
    },
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

          <OrderRefund order={order} now={now} wait={wait} timedOut={pollingTimedOut} onCheckAgain={checkAgain} />

          {isPaying && !pollingTimedOut && (
            <Alert role="status">
              <LoaderCircle className="animate-spin" />
              <AlertTitle>Płatność w toku</AlertTitle>
              <AlertDescription>Sprawdzamy status płatności. Strona odświeży się sama.</AlertDescription>
            </Alert>
          )}

          {isPaying && pollingTimedOut && (
            <Alert role="status">
              <LoaderCircle />
              <AlertTitle>Płatność trwa dłużej niż zwykle</AlertTitle>
              <AlertDescription>
                Nie mamy jeszcze wyniku. Nie płać drugi raz, tylko sprawdź status za chwilę.
                <Button className="mt-3" variant="outline" size="sm" type="button" onClick={checkAgain}>
                  Sprawdź ponownie
                </Button>
              </AlertDescription>
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

        {wait === 'expiry' && <ExpiringNotice timedOut={pollingTimedOut} onCheckAgain={checkAgain} />}

        {!isReservationOpen && wait !== 'expiry' && <OrderOutcome order={order} />}
      </div>
    </section>
  )
}
