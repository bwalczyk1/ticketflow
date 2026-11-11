import { CircleAlert, CircleCheck, Clock } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import type { Order } from '../../api/types'

interface OrderOutcomeProps {
  order: Order
  /** The hold ran out, although the server may not have marked the order EXPIRED yet. */
  isExpired: boolean
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
export function OrderOutcome({ order, isExpired }: OrderOutcomeProps) {
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

  if (isExpired) {
    return (
      <OutcomeAlert title="Rezerwacja wygasła" icon={<Clock />}>
        Czas na płatność minął, a miejsca wróciły do puli. Wybierz bilety jeszcze raz.
        <br />
        {eventLink}
      </OutcomeAlert>
    )
  }

  return null
}
