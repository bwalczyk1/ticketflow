import { useState } from 'react'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'

interface ConfirmDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Text on the confirming action. */
  label: string
  title: string
  description: string
  onConfirm: () => void
}

interface ConfirmButtonProps {
  /** Text on the button and on the confirming action in the dialog. */
  label: string
  title: string
  description: string
  disabled?: boolean
  /** Runs when the dialog opens, e.g. to clear the error of the previous attempt. */
  onOpen?: () => void
  onConfirm: () => void
}

/** A confirmation dialog for a destructive action, opened by the caller. */
export function ConfirmDialog({ open, onOpenChange, label, title, description, onConfirm }: ConfirmDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel>Anuluj</AlertDialogCancel>
          <AlertDialogAction variant="destructive" onClick={onConfirm}>
            {label}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

/** A destructive button that asks for confirmation in a dialog before it runs the action. */
export function ConfirmButton({ label, title, description, disabled, onOpen, onConfirm }: ConfirmButtonProps) {
  const [open, setOpen] = useState(false)

  function openDialog() {
    onOpen?.()
    setOpen(true)
  }

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        type="button"
        className="text-destructive hover:text-destructive"
        disabled={disabled}
        onClick={openDialog}
      >
        {label}
      </Button>

      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        label={label}
        title={title}
        description={description}
        onConfirm={onConfirm}
      />
    </>
  )
}
