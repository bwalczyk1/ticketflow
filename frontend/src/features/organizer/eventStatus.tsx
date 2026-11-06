import type { EventStatus } from '../../api/types'

const STATUS_LABELS: Record<EventStatus, string> = {
  DRAFT: 'Szkic',
  PUBLISHED: 'Opublikowane',
  FINISHED: 'Zakończone',
  CANCELLED: 'Odwołane',
}

export function StatusBadge({ status }: { status: EventStatus }) {
  return <span className={`badge badge--${status.toLowerCase()}`}>{STATUS_LABELS[status]}</span>
}
