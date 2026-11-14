import { Badge } from '@/components/ui/badge'
import type { TicketStatus } from '../../api/types'

const TICKET_STATUS_LABELS: Record<TicketStatus, string> = {
  VALID: 'Ważny',
  USED: 'Wykorzystany',
  VOID: 'Unieważniony',
}

const TICKET_STATUS_VARIANTS = {
  VALID: 'default',
  USED: 'secondary',
  VOID: 'destructive',
} as const satisfies Record<TicketStatus, string>

export function TicketStatusBadge({ status }: { status: TicketStatus }) {
  return <Badge variant={TICKET_STATUS_VARIANTS[status]}>{TICKET_STATUS_LABELS[status]}</Badge>
}
