import { Badge } from '@/components/ui/badge'
import type { WaitlistStatus } from '../../api/types'

const STATUS_LABELS: Record<WaitlistStatus, string> = {
  WAITING: 'Oczekuje',
  OFFERED: 'Bilety czekają na Ciebie',
  CONVERTED: 'Kupione',
  EXPIRED: 'Oferta wygasła',
  CANCELLED: 'Wypisano',
}

const STATUS_VARIANTS = {
  WAITING: 'secondary',
  OFFERED: 'default',
  CONVERTED: 'outline',
  EXPIRED: 'outline',
  CANCELLED: 'outline',
} as const satisfies Record<WaitlistStatus, string>

export function WaitlistStatusBadge({ status }: { status: WaitlistStatus }) {
  return <Badge variant={STATUS_VARIANTS[status]}>{STATUS_LABELS[status]}</Badge>
}
