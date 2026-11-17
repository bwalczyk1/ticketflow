import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { ApiError } from '../../api/errors'
import type { EventDetails, PurchaseLimit, TicketType } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { QuantityPicker } from '../../components/QuantityPicker'
import { formatPrice } from '../../lib/money'
import { useAuth } from '../auth/AuthContext'
import { eventsKey } from '../catalog/catalogApi'
import { createOrder, ordersKey } from './checkoutApi'
import { WaitlistJoin } from '../waitlist/WaitlistJoin'
import { useNow } from './useCountdown'

interface TicketSelectionProps {
  event: EventDetails
}

function limitLabel(limit: PurchaseLimit, allowance: number, selected: number): string {
  if (allowance === 0) {
    return `Wykorzystano limit ${limit.max} biletów na to wydarzenie, więc nie możesz kupić kolejnych.`
  }

  return `Wybrano ${selected}. Możesz kupić jeszcze ${allowance} z ${limit.max} biletów.`
}

export function TicketSelection({ event }: TicketSelectionProps) {
  const { user } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const now = useNow()
  const ticketTypes = event.ticketTypes ?? []

  const mutation = useMutation({
    mutationFn: () => {
      const items = ticketTypes
        .filter((ticketType) => (quantities[ticketType.id] ?? 0) > 0)
        .map((ticketType) => ({ ticketTypeId: ticketType.id, quantity: quantities[ticketType.id] }))

      return createOrder(event.id, items)
    },
    onSuccess: (order) => {
      queryClient.invalidateQueries({ queryKey: ordersKey })
      queryClient.invalidateQueries({ queryKey: eventsKey })
      navigate(`/orders/${order.id}`)
    },
    onError: () => queryClient.invalidateQueries({ queryKey: eventsKey }),
  })

  const isAttendee = user?.role === 'ATTENDEE'
  const isOnSale = event.status === 'PUBLISHED' && Date.parse(event.startsAt) > now
  const showPickers = isAttendee && isOnSale
  const totalQuantity = ticketTypes.reduce((sum, ticketType) => sum + (quantities[ticketType.id] ?? 0), 0)
  const totalPrice = ticketTypes.reduce(
    (sum, ticketType) => sum + (quantities[ticketType.id] ?? 0) * ticketType.price,
    0,
  )
  const limit = event.purchaseLimit
  const allowance = limit ? Math.max(0, limit.max - limit.used) : 0
  const canRetry = mutation.error instanceof ApiError && mutation.error.code === 'RESERVATION_CONFLICT'

  function changeQuantity(ticketTypeId: number, quantity: number) {
    setQuantities((current) => ({ ...current, [ticketTypeId]: quantity }))
  }

  /** The pool's stock, or less when the purchase limit leaves fewer tickets. */
  function maxQuantity(ticketType: TicketType): number {
    const quantity = quantities[ticketType.id] ?? 0

    return Math.min(ticketType.available, quantity + allowance - totalQuantity)
  }

  function renderBuyAction() {
    if (!isOnSale) {
      return <p className="text-sm text-muted-foreground">Sprzedaż biletów na to wydarzenie jest zamknięta.</p>
    }

    if (!user) {
      return (
        <Button asChild>
          <Link to="/login" state={{ from: location.pathname }}>
            Zaloguj się, aby kupić bilety
          </Link>
        </Button>
      )
    }

    if (!isAttendee) {
      return null
    }

    return (
      <>
        <ErrorBanner error={mutation.error} />

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="font-semibold">Razem: {formatPrice(totalPrice)}</p>

            {limit && <p className="text-sm text-muted-foreground">{limitLabel(limit, allowance, totalQuantity)}</p>}
          </div>

          <div className="flex flex-wrap gap-2">
            {canRetry && (
              <Button variant="outline" type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
                Spróbuj ponownie
              </Button>
            )}

            <Button type="button" disabled={totalQuantity === 0 || mutation.isPending} onClick={() => mutation.mutate()}>
              {mutation.isPending ? 'Rezerwuję…' : 'Kup bilety'}
            </Button>
          </div>
        </div>
      </>
    )
  }

  const buyAction = renderBuyAction()

  return (
    <>
      {ticketTypes.length === 0 && <p className="text-muted-foreground">Brak biletów w sprzedaży.</p>}

      {ticketTypes.length > 0 && (
        <ul className="grid gap-3">
          {ticketTypes.map((ticketType) => (
            <li key={ticketType.id}>
              <Card className="py-4">
                <CardContent className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 sm:px-5">
                  <div>
                    <p className="font-medium">{ticketType.name}</p>
                    <p className="text-sm text-muted-foreground">
                      {ticketType.available > 0 ? `Dostępne: ${ticketType.available}` : 'Wyprzedane'}
                    </p>
                  </div>

                  <p className="font-semibold">{formatPrice(ticketType.price)}</p>

                  {showPickers && (
                    <QuantityPicker
                      label={`Liczba biletów: ${ticketType.name}`}
                      value={quantities[ticketType.id] ?? 0}
                      min={0}
                      max={maxQuantity(ticketType)}
                      onChange={(quantity) => changeQuantity(ticketType.id, quantity)}
                    />
                  )}

                  {showPickers && ticketType.available === 0 && (
                    <WaitlistJoin ticketType={ticketType} allowance={allowance} />
                  )}
                </CardContent>
              </Card>
            </li>
          ))}
        </ul>
      )}

      {ticketTypes.length > 0 && buyAction && (
        <>
          <Separator className="my-6" />
          {buyAction}
        </>
      )}
    </>
  )
}
