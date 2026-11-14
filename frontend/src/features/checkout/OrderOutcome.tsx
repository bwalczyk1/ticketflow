import { CircleAlert, CircleCheck, Clock, LoaderCircle } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import type { Order } from '../../api/types'

interface OrderOutcomeProps {
  order: Order
}

interface ExpiringNoticeProps {
  /** The server has not expired the order within the polling time. */
  timedOut: boolean
  onCheckAgain: () => void
}

interface OutcomeAlertProps {
  title: string
  children: ReactNode
  icon: ReactNode
  destructive?: boolean
}

function OutcomeAlert({ title, children, icon, destructive }: OutcomeAlertProps) {
  return (
    <Alert variant={destructive ? 'destructive' : 'default'} role="status">
      {icon}
      <AlertTitle className="line-clamp-none">{title}</AlertTitle>
      <AlertDescription>{children}</AlertDescription>
    </Alert>
  )
}

/** What the user sees once the order is over: paid, rejected, expired or cancelled. */
export function OrderOutcome({ order }: OrderOutcomeProps) {
  const eventLink = (
    <Button className="mt-3" variant="outline" size="sm" asChild>
      <Link to={`/events/${order.event.id}`}>Wróć do wydarzenia</Link>
    </Button>
  )

  if (order.status === 'PAID') {
    return (
      <OutcomeAlert title="Płatność przyjęta" icon={<CircleCheck />}>
        Dziękujemy, zamówienie jest opłacone. Bilety znajdziesz w zakładce „Moje bilety”.
        <br />
        <Button className="mt-3" size="sm" asChild>
          <Link to="/tickets">Przejdź do biletów</Link>
        </Button>
      </OutcomeAlert>
    )
  }

  if (order.status === 'FAILED') {
    return (
      <OutcomeAlert title="Płatność została odrzucona" icon={<CircleAlert />} destructive>
        Z konta nic nie pobrano, a miejsca wróciły do puli. Złóż nowe zamówienie.
        <br />
        {eventLink}
      </OutcomeAlert>
    )
  }

  if (order.status === 'CANCELLED') {
    return (
      <OutcomeAlert title="Rezerwacja anulowana" icon={<CircleAlert />}>
        Miejsca wróciły do puli. Możesz wybrać bilety jeszcze raz.
        <br />
        {eventLink}
      </OutcomeAlert>
    )
  }

  if (order.status === 'EXPIRED') {
    return (
      <OutcomeAlert title="Rezerwacja wygasła, miejsca wróciły do puli" icon={<Clock />}>
        Czas na płatność minął. Wybierz bilety jeszcze raz.
        <br />
        {eventLink}
      </OutcomeAlert>
    )
  }

  return null
}

/**
 * The countdown reached zero, but the order stays PENDING until the scheduler expires it,
 * so the page does not claim yet that the seats are back in the pool.
 */
export function ExpiringNotice({ timedOut, onCheckAgain }: ExpiringNoticeProps) {
  if (timedOut) {
    return (
      <OutcomeAlert title="Rezerwacja wygasa" icon={<Clock />}>
        Czas na płatność minął, ale system jeszcze nie zwolnił miejsc. Sprawdź status za chwilę.
        <br />
        <Button className="mt-3" variant="outline" size="sm" type="button" onClick={onCheckAgain}>
          Sprawdź ponownie
        </Button>
      </OutcomeAlert>
    )
  }

  return (
    <OutcomeAlert title="Rezerwacja wygasa…" icon={<LoaderCircle className="animate-spin" />}>
      Czas na płatność minął. Czekamy, aż system zwolni zablokowane miejsca. Strona odświeży się sama.
    </OutcomeAlert>
  )
}
