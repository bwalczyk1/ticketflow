import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'

interface CancelOrderDialogProps {
  disabled: boolean
  onConfirm: () => void
}

export function CancelOrderDialog({ disabled, onConfirm }: CancelOrderDialogProps) {
  return (
    <AlertDialog>
      <AlertDialogTrigger asChild>
        <Button variant="outline" type="button" disabled={disabled}>
          Anuluj rezerwację
        </Button>
      </AlertDialogTrigger>

      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Anulować rezerwację?</AlertDialogTitle>
          <AlertDialogDescription>
            Zablokowane miejsca wrócą do puli od razu, a zamówienia nie da się już opłacić.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel>Wróć</AlertDialogCancel>
          <AlertDialogAction variant="destructive" onClick={onConfirm}>
            Anuluj rezerwację
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
