import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import type { TicketType } from '../../api/types'
import { ConfirmButton } from '../../components/ConfirmButton'
import { ErrorBanner } from '../../components/ErrorBanner'
import { QuantityPicker } from '../../components/QuantityPicker'
import { eventsKey } from '../catalog/catalogApi'
import { fetchAllEntries, joinWaitlist, leaveWaitlist, waitlistKey } from './waitlistApi'

interface WaitlistJoinProps {
  ticketType: TicketType
  /** How many more tickets the attendee may hold for the event. */
  allowance: number
}

/** Shown under a sold-out ticket type: sign up for the waitlist, or see the place in the queue. */
export function WaitlistJoin({ ticketType, allowance }: WaitlistJoinProps) {
  const queryClient = useQueryClient()
  const [quantity, setQuantity] = useState(1)
  const selected = Math.min(quantity, allowance)

  const { data } = useQuery({ queryKey: [...waitlistKey, 'all'], queryFn: fetchAllEntries })
  const entry = data?.data.find(
    (candidate) =>
      candidate.ticketType.id === ticketType.id && (candidate.status === 'WAITING' || candidate.status === 'OFFERED'),
  )

  const joinMutation = useMutation({
    mutationFn: () => joinWaitlist(ticketType.id, selected),
    onSuccess: () => toast.success(`Zapisano na listę oczekujących: ${ticketType.name}.`),
    // A conflict usually means the pool or the list changed meanwhile, so both are fetched again.
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: waitlistKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ]),
  })

  const leaveMutation = useMutation({
    mutationFn: () => leaveWaitlist(ticketType.id),
    onSuccess: () => toast.success(`Wypisano z listy oczekujących: ${ticketType.name}.`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: waitlistKey }),
  })

  if (entry?.status === 'OFFERED') {
    return (
      <p className="basis-full text-sm">
        Bilety czekają na Ciebie.{' '}
        <Link className="underline" to="/waitlist">
          Przejdź do oferty
        </Link>
      </p>
    )
  }

  if (entry) {
    return (
      <div className="grid basis-full gap-2">
        <ErrorBanner error={leaveMutation.error} />

        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm">
          <p>
            Jesteś na liście oczekujących ({entry.quantity} szt.)
            {entry.position !== null && `, pozycja ${entry.position}`}.{' '}
            <Link className="underline" to="/waitlist">
              Moja lista oczekujących
            </Link>
          </p>

          <ConfirmButton
            label="Wypisz się"
            title="Wypisać się z listy oczekujących?"
            description={`Stracisz swoje miejsce w kolejce na pulę „${ticketType.name}”.`}
            variant="outline"
            disabled={leaveMutation.isPending}
            onConfirm={() => leaveMutation.mutate()}
          />
        </div>
      </div>
    )
  }

  if (allowance < 1) {
    return (
      <p className="basis-full text-sm text-muted-foreground">
        Wykorzystano limit biletów na to wydarzenie, więc nie możesz zapisać się na listę.
      </p>
    )
  }

  return (
    <div className="grid basis-full gap-2">
      <ErrorBanner error={joinMutation.error} />

      <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <p className="text-sm text-muted-foreground">
          Gdy bilety wrócą do sprzedaży, damy Ci 30 minut na zakup.
        </p>

        <div className="flex flex-wrap items-center gap-2">
          <QuantityPicker
            label={`Liczba biletów na liście: ${ticketType.name}`}
            value={selected}
            min={1}
            max={allowance}
            onChange={setQuantity}
          />

          <Button size="sm" type="button" disabled={joinMutation.isPending} onClick={() => joinMutation.mutate()}>
            Zapisz się na listę oczekujących
          </Button>
        </div>
      </div>
    </div>
  )
}
