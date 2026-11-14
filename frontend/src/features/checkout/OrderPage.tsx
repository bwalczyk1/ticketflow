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
import { OrderItemsCard } from './OrderItemsCard'
import { OrderOutcome } from './OrderOutcome'
import { isHoldOver, isPaymentInProgress } from './orderState'
import { ReservationCard } from './ReservationCard'
import { useNow } from './useCountdown'

// The general text speaks of events; on this page the code means the order moved on.
const ORDER_ERRORS = {
  INVALID_STATUS_TRANSITION: 'Tego zamówienia nie można już zmienić. Odśwież stronę, aby zobaczyć jego status.',
}

const POLL_INTERVAL_MS = 1500
const POLL_TIMEOUT_MS = 30_000

export function OrderPage() {
  const { id = '' } = useParams()
  const queryClient = useQueryClient()
  const now = useNow()
  const [pollingTimedOut, setPollingTimedOut] = useState(false)
  const orderKey = [...ordersKey, 'details', id]

  const { data: order, error, isPending } = useQuery({
    queryKey: orderKey,
    queryFn: () => fetchOrder(id),
    staleTime: 0,
    // Polling runs only while a payment waits for its result, and React Query stops it on unmount.
    refetchInterval: (query) => {
      const current = query.state.data

      return current && isPaymentInProgress(current) && !pollingTimedOut ? POLL_INTERVAL_MS : false
    },
  })

  const isPaying = order ? isPaymentInProgress(order) : false

  useEffect(() => {
    if (!isPaying || pollingTimedOut) {
      return
    }

    const timer = setTimeout(() => setPollingTimedOut(true), POLL_TIMEOUT_MS)

    return () => clearTimeout(timer)
  }, [isPaying, pollingTimedOut])

  function handleSettled() {
    queryClient.invalidateQueries({ queryKey: eventsKey })
    queryClient.invalidateQueries({ queryKey: ordersKey })
  }

  const payMutation = useMutation({
    mutationFn: () => payOrder(Number(id)),
    onSuccess: (updated: Order) => {
      setPollingTimedOut(false)
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

  const isHoldEnded = isHoldOver(order, now)
  const isReservationOpen = order.status === 'PENDING' && !isHoldEnded

  return (
    <section>
      <BackLink to="/orders" label="Moje zamówienia" />

      <h1 className={`${PAGE_TITLE} mb-6`}>Zamówienie #{order.id}</h1>

      <ErrorBanner error={payMutation.error} messages={ORDER_ERRORS} />
      <ErrorBanner error={cancelMutation.error} messages={ORDER_ERRORS} />

      <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
        <div className="grid gap-4">
          <OrderItemsCard order={order} />

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
                <Button className="mt-3" variant="outline" size="sm" type="button" onClick={() => setPollingTimedOut(false)}>
                  Sprawdź ponownie
                </Button>
              </AlertDescription>
            </Alert>
          )}
        </div>

        {isReservationOpen ? (
          <ReservationCard
            order={order}
            now={now}
            isPaying={isPaying}
            isBusy={payMutation.isPending || cancelMutation.isPending}
            onPay={() => payMutation.mutate()}
            onCancel={() => cancelMutation.mutate()}
          />
        ) : (
          <OrderOutcome order={order} isExpired={isHoldEnded} />
        )}
      </div>
    </section>
  )
}
