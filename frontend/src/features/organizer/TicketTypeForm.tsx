import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { getFieldErrors } from '../../api/errors'
import { Button } from '@/components/ui/button'
import type { ManagedTicketType } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { FormField } from '../../components/FormField'
import { groszeToPln, plnToGrosze } from '../../lib/money'
import { eventsKey } from '../catalog/catalogApi'
import {
  createTicketType,
  organizerKey,
  updateTicketType,
  type TicketTypePayload,
} from './organizerApi'

interface TicketTypeFormProps {
  eventId: string
  /** When given, the form edits this ticket type; otherwise it adds a new one. */
  ticketType?: ManagedTicketType
  onDone: () => void
}

export function TicketTypeForm({ eventId, ticketType, onDone }: TicketTypeFormProps) {
  const queryClient = useQueryClient()
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: (payload: TicketTypePayload) =>
      ticketType
        ? updateTicketType(eventId, ticketType.id, payload)
        : createTicketType(eventId, payload),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: organizerKey }),
        queryClient.invalidateQueries({ queryKey: eventsKey }),
      ])

      onDone()
    },
  })

  const fieldErrors = { ...getFieldErrors(mutation.error), ...clientErrors }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)
    const price = plnToGrosze(String(form.get('price')))
    const quantity = Number(form.get('quantity'))
    const errors: Record<string, string> = {}

    if (price === null) {
      errors.price = 'Podaj cenę w złotych, np. 49,99.'
    }

    if (!Number.isInteger(quantity) || quantity < 1) {
      errors.quantity = 'Podaj całkowitą liczbę biletów, co najmniej 1.'
    }

    setClientErrors(errors)

    if (price === null || Object.keys(errors).length > 0) {
      return
    }

    mutation.mutate({ name: String(form.get('name')).trim(), price, quantity })
  }

  return (
    <form onSubmit={handleSubmit}>
      <ErrorBanner error={mutation.error} />

      <div className="grid items-start gap-4 sm:grid-cols-3">
        <FormField
          label="Nazwa puli"
          name="name"
          defaultValue={ticketType?.name}
          maxLength={100}
          required
          error={fieldErrors.name}
        />
        <FormField
          label="Cena (zł)"
          name="price"
          inputMode="decimal"
          defaultValue={ticketType ? groszeToPln(ticketType.price) : undefined}
          required
          error={fieldErrors.price}
        />
        <FormField
          label="Liczba biletów"
          name="quantity"
          type="number"
          min={1}
          defaultValue={ticketType?.quantity}
          hint={ticketType ? `Sprzedane: ${ticketType.sold}, zablokowane: ${ticketType.held}` : undefined}
          required
          error={fieldErrors.quantity}
        />
      </div>

      <div className="mt-4 flex gap-2">
        <Button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Zapisywanie…' : ticketType ? 'Zapisz' : 'Dodaj pulę'}
        </Button>
        {ticketType && (
          <Button variant="outline" type="button" onClick={onDone}>
            Anuluj
          </Button>
        )}
      </div>
    </form>
  )
}
