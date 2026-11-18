import { MoreHorizontal } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { ManagedEvent } from '../../api/types'
import { getAllowedActions } from './allowedActions'

export type ConfirmedAction = 'cancel' | 'delete'

interface EventActionsMenuProps {
  event: ManagedEvent
  disabled: boolean
  onPublish: () => void
  /** Cancelling and deleting ask for confirmation first, so the page opens its dialog. */
  onConfirm: (action: ConfirmedAction) => void
}

/** The "⋯" menu with everything an organizer can do with one event. */
export function EventActionsMenu({ event, disabled, onPublish, onConfirm }: EventActionsMenuProps) {
  const allowed = getAllowedActions(event.status)
  const isDestructive = allowed.cancel || allowed.delete

  // Not modal, so the confirmation dialog opened from an item does not fight the menu over focus.
  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon-sm" type="button" aria-label={`Akcje: ${event.title}`}>
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <Link to={`/organizer/events/${event.id}/ticket-types`}>Pule biletów</Link>
        </DropdownMenuItem>

        {allowed.edit && (
          <DropdownMenuItem asChild>
            <Link to={`/organizer/events/${event.id}/edit`}>Edytuj</Link>
          </DropdownMenuItem>
        )}

        {allowed.publish && (
          <DropdownMenuItem disabled={disabled} onSelect={onPublish}>
            Opublikuj
          </DropdownMenuItem>
        )}

        {isDestructive && <DropdownMenuSeparator />}

        {allowed.cancel && (
          <DropdownMenuItem variant="destructive" disabled={disabled} onSelect={() => onConfirm('cancel')}>
            Odwołaj
          </DropdownMenuItem>
        )}

        {allowed.delete && (
          <DropdownMenuItem variant="destructive" disabled={disabled} onSelect={() => onConfirm('delete')}>
            Usuń
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
