import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Download, LoaderCircle } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import type { Order, RefundStatus } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { TextAreaField } from '../../components/TextAreaField'
import { formatDateTime } from '../../lib/dates'
import { formatPrice } from '../../lib/money'
import { eventsKey } from '../catalog/catalogApi'
import { ticketsKey } from '../tickets/ticketsApi'
import { downloadCorrectionInvoice, ordersKey, refundOrder } from './checkoutApi'
import { isRefundOpen, type OrderWait } from './orderState'

const REFUND_STATUS_LABELS: Record<RefundStatus, string> = {
  PENDING: 'W toku',
  SUCCEEDED: 'Zwrot zrealizowany',
  FAILED: 'Zwrot nie powiódł się',
}

const REFUND_ERRORS = {
  ORDER_NOT_REFUNDABLE: 'Tego zamówienia nie można już zwrócić. Odśwież stronę, aby zobaczyć jego status.',
}

interface OrderRefundProps {
  order: Order
  now: number
  /** What the page is polling for, to tell a running refund from a stuck one. */
  wait: OrderWait | null
  timedOut: boolean
  onCheckAgain: () => void
}

interface RefundDialogProps {
  disabled: boolean
  onOpen: () => void
  onConfirm: (reason: string) => void
}

function RefundDialog({ disabled, onOpen, onConfirm }: RefundDialogProps) {
  const [reason, setReason] = useState('')

  function handleOpenChange(open: boolean) {
    if (open) {
      setReason('')
      onOpen()
    }
  }

  return (
    <AlertDialog onOpenChange={handleOpenChange}>
      <AlertDialogTrigger asChild>
        <Button variant="outline" type="button" disabled={disabled}>
          Zwróć bilety
        </Button>
      </AlertDialogTrigger>

      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Zwrócić bilety?</AlertDialogTitle>
          <AlertDialogDescription>
            Zwrot obejmuje całe zamówienie. Bilety zostaną unieważnione, a pieniądze wrócą na Twoje konto.
            Tej operacji nie można cofnąć.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <TextAreaField
          label="Powód zwrotu (opcjonalnie)"
          maxLength={500}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
        />

        <AlertDialogFooter>
          <AlertDialogCancel>Wróć</AlertDialogCancel>
          <AlertDialogAction variant="destructive" onClick={() => onConfirm(reason.trim())}>
            Zwróć bilety
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

/** The refund button of a paid order, and the refund and its correction invoice once there is one. */
export function OrderRefund({ order, now, wait, timedOut, onCheckAgain }: OrderRefundProps) {
  const queryClient = useQueryClient()
  const refundMutation = useMutation({
    mutationFn: (reason: string) => refundOrder(order.id, reason),
    onSuccess: (updated) => {
      queryClient.setQueryData([...ordersKey, 'details', String(order.id)], updated)
      toast.success('Zlecono zwrot biletów.')
    },
    onError: () => queryClient.invalidateQueries({ queryKey: [...ordersKey, 'details', String(order.id)] }),
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ordersKey }),
        queryClient.invalidateQueries({ queryKey: ticketsKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ]),
  })
  const correctionMutation = useMutation({ mutationFn: () => downloadCorrectionInvoice(order) })
  const { refund } = order
  const deadline = order.refundableUntil ? formatDateTime(order.refundableUntil, order.event.timezone) : null

  // An older backend sends neither field, so there is nothing to show.
  if (order.refundableUntil === undefined && refund === undefined) {
    return null
  }

  if (isRefundOpen(order, now)) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>
            <h2>Zwrot biletów</h2>
          </CardTitle>
          <CardDescription>Bilety możesz zwrócić do {deadline}.</CardDescription>
        </CardHeader>

        <CardContent className="grid gap-3">
          <ErrorBanner error={refundMutation.error} messages={REFUND_ERRORS} />

          <div>
            <RefundDialog
              disabled={refundMutation.isPending}
              onOpen={() => refundMutation.reset()}
              onConfirm={(reason) => refundMutation.mutate(reason)}
            />
          </div>
        </CardContent>
      </Card>
    )
  }

  if (!refund) {
    if (order.status !== 'PAID') {
      return null
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>
            <h2>Zwrot biletów</h2>
          </CardTitle>
        </CardHeader>

        <CardContent className="grid gap-3">
          <ErrorBanner error={refundMutation.error} messages={REFUND_ERRORS} />

          <p className="text-sm text-muted-foreground">
            Zwrot jest możliwy tylko do 48 godzin przed wydarzeniem, więc tych biletów nie można już zwrócić.
          </p>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h2>Zwrot biletów</h2>
        </CardTitle>
      </CardHeader>

      <CardContent className="grid gap-4">
        <ErrorBanner error={correctionMutation.error} />

        {refund.status === 'PENDING' && wait === 'refund' && !timedOut && (
          <Alert role="status">
            <LoaderCircle className="animate-spin" />
            <AlertTitle>Zwrot w toku</AlertTitle>
            <AlertDescription>Przetwarzamy zwrot. Strona odświeży się sama.</AlertDescription>
          </Alert>
        )}

        {refund.status === 'PENDING' && timedOut && (
          <Alert role="status">
            <LoaderCircle />
            <AlertTitle>Zwrot trwa dłużej niż zwykle</AlertTitle>
            <AlertDescription>
              Nie mamy jeszcze wyniku. Sprawdź status za chwilę.
              <Button className="mt-3" variant="outline" size="sm" type="button" onClick={onCheckAgain}>
                Sprawdź ponownie
              </Button>
            </AlertDescription>
          </Alert>
        )}

        <dl className="grid gap-1 text-sm sm:grid-cols-[10rem_1fr]">
          <dt className="text-muted-foreground">Status</dt>
          <dd className="font-medium">{REFUND_STATUS_LABELS[refund.status]}</dd>

          <dt className="text-muted-foreground">Kwota</dt>
          <dd className="font-medium">{formatPrice(refund.amount)}</dd>

          <dt className="text-muted-foreground">Zlecony</dt>
          <dd>{formatDateTime(refund.createdAt)}</dd>

          {refund.reason && (
            <>
              <dt className="text-muted-foreground">Powód</dt>
              <dd className="break-words">{refund.reason}</dd>
            </>
          )}
        </dl>

        {order.status === 'REFUNDED' && (
          <>
            <Separator />

            {order.correctionInvoice ? (
              <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div>
                  <p className="font-medium">Faktura korygująca {order.correctionInvoice.number}</p>
                  <p className="text-sm text-muted-foreground">
                    Wystawiona {formatDateTime(order.correctionInvoice.issuedAt)}
                  </p>
                </div>

                <Button
                  variant="outline"
                  size="sm"
                  type="button"
                  disabled={correctionMutation.isPending}
                  onClick={() => correctionMutation.mutate()}
                >
                  {correctionMutation.isPending ? <LoaderCircle className="animate-spin" /> : <Download />}
                  Pobierz fakturę korygującą
                </Button>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground" role="status">
                Faktura korygująca zostanie wystawiona po zaksięgowaniu zwrotu.
                {timedOut && wait === 'correction' && (
                  <Button className="ml-2" variant="outline" size="sm" type="button" onClick={onCheckAgain}>
                    Sprawdź ponownie
                  </Button>
                )}
              </p>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}
