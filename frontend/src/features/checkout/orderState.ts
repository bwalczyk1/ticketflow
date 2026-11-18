import type { Order } from '../../api/types'

/** The payment was started and the background job has not answered yet. */
export function isPaymentInProgress(order: Order): boolean {
  return order.status === 'PENDING' && order.paymentStatus === 'PENDING'
}

/**
 * The hold has run out. The scheduler marks the order EXPIRED a little later, so the
 * timestamp is checked too, not only the status.
 */
export function isHoldOver(order: Order, now: number): boolean {
  if (order.status === 'EXPIRED') {
    return true
  }

  return (
    order.status === 'PENDING' && !isPaymentInProgress(order) && Date.parse(order.expiresAt) <= now
  )
}

/** A reservation that can still be paid for or cancelled. */
export function isReservationActive(order: Order, now: number): boolean {
  return order.status === 'PENDING' && order.paymentStatus === null && !isHoldOver(order, now)
}
