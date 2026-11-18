import { Badge } from '@/components/ui/badge'
import type { EventStatus } from '../../api/types'

const STATUS_LABELS: Record<EventStatus, string> = {
  DRAFT: 'Szkic',
  PUBLISHED: 'Opublikowane',
  FINISHED: 'Zakończone',
  CANCELLED: 'Odwołane',
}

const STATUS_VARIANTS = {
  DRAFT: 'secondary',
  PUBLISHED: 'default',
  FINISHED: 'outline',
  CANCELLED: 'destructive',
} as const satisfies Record<EventStatus, string>

export function StatusBadge({ status }: { status: EventStatus }) {
  return <Badge variant={STATUS_VARIANTS[status]}>{STATUS_LABELS[status]}</Badge>
}
