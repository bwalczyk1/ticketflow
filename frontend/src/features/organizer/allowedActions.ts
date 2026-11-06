import type { EventStatus } from '../../api/types'

/** What the status permits; the API still has the final word and may answer 409. */
export function getAllowedActions(status: EventStatus) {
  return {
    edit: status === 'DRAFT' || status === 'PUBLISHED',
    publish: status === 'DRAFT',
    cancel: status === 'PUBLISHED',
    delete: status !== 'FINISHED',
  }
}
