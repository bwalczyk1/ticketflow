import { LoaderCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import type { Order } from '../../api/types'
import { formatPrice } from '../../lib/money'
import { CancelOrderDialog } from './CancelOrderDialog'
import { OrderStatusBadge } from './OrderStatusBadge'
import { formatCountdown } from './useCountdown'

interface ReservationCardProps {
  order: Order
  now: number
  isPaying: boolean
  isBusy: boolean
  onPay: () => void
  onCancel: () => void
}

/** The side card of a pending order: the countdown and the pay and cancel buttons. */
export function ReservationCard({ order, now, isPaying, isBusy, onPay, onCancel }: ReservationCardProps) {
  const expiresAt = Date.parse(order.expiresAt)
  const holdLength = expiresAt - Date.parse(order.createdAt)
  const remaining = Math.max(0, expiresAt - now)
  const percent = Math.min(100, (remaining / holdLength) * 100)

  return (
    <Card>
      <CardHeader className="flex flex-wrap items-center justify-between gap-2">
        <CardTitle>Rezerwacja</CardTitle>
        <OrderStatusBadge status={order.status} />
      </CardHeader>

      <CardContent className="grid gap-4">
        {isPaying ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
            Czekamy na wynik płatności, miejsca są zablokowane.
          </div>
        ) : (
          <>
            <div>
              <p className="text-5xl font-bold tracking-tight tabular-nums" role="timer" aria-live="off">
                {formatCountdown(remaining)}
              </p>
              <p className="mt-1.5 text-sm text-muted-foreground">do końca blokady miejsc</p>
            </div>

            <Progress value={percent} aria-label="Pozostały czas blokady" />
          </>
        )}

        {!isPaying && (
          <div className="grid gap-2">
            <Button type="button" disabled={isBusy} onClick={onPay}>
              Zapłać {formatPrice(order.total)}
            </Button>

            <CancelOrderDialog disabled={isBusy} onConfirm={onCancel} />
          </div>
        )}

        <p className="text-sm text-muted-foreground">Po upływie czasu miejsca wracają do puli.</p>
      </CardContent>
    </Card>
  )
}
