import { Badge } from '@/components/ui/badge'
import type { OrderStatus } from '../../api/types'

const STATUS_LABELS: Record<OrderStatus, string> = {
  PENDING: 'Oczekuje na płatność',
  PAID: 'Opłacone',
  EXPIRED: 'Wygasło',
  FAILED: 'Płatność odrzucona',
  CANCELLED: 'Anulowane',
}

const STATUS_VARIANTS = {
  PENDING: 'secondary',
  PAID: 'default',
  EXPIRED: 'outline',
  FAILED: 'destructive',
  CANCELLED: 'outline',
} as const satisfies Record<OrderStatus, string>

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  return <Badge variant={STATUS_VARIANTS[status]}>{STATUS_LABELS[status]}</Badge>
}
