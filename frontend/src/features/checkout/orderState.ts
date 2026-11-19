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

/**
 * What the order page waits for: the payment result, the scheduler expiring the hold,
 * the invoice that is issued in the background after the payment, or the refund and
 * its correction invoice.
 */
export type OrderWait = 'payment' | 'expiry' | 'invoice' | 'refund' | 'correction'

export function getOrderWait(order: Order, now: number): OrderWait | null {
  if (isPaymentInProgress(order)) {
    return 'payment'
  }

  if (order.status === 'PENDING' && isHoldOver(order, now)) {
    return 'expiry'
  }

  if (order.refund?.status === 'PENDING') {
    return 'refund'
  }

  // Undefined means a backend without refunds, so there is nothing to wait for.
  if (order.status === 'REFUNDED' && order.correctionInvoice === null) {
    return 'correction'
  }

  // Undefined means a backend without invoices, so there is nothing to wait for.
  if (order.status === 'PAID' && order.invoice === null) {
    return 'invoice'
  }

  return null
}

/** A reservation that can still be paid for or cancelled. */
export function isReservationActive(order: Order, now: number): boolean {
  return order.status === 'PENDING' && order.paymentStatus === null && !isHoldOver(order, now)
}

/** The refund window is open: the order is paid, no refund is running and the deadline has not passed. */
export function isRefundOpen(order: Order, now: number): boolean {
  const deadline = order.refundableUntil ? Date.parse(order.refundableUntil) : 0

  return order.status === 'PAID' && !order.refund && deadline > now
}
