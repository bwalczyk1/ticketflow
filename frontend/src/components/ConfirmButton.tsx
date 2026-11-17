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
import { cn } from '@/lib/utils'

interface ConfirmDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Text on the confirming action. */
  label: string
  /** Text on the button that closes the dialog without doing anything. */
  cancelLabel?: string
  title: string
  description: string
  onConfirm: () => void
}

interface ConfirmButtonProps {
  /** Text on the button and on the confirming action in the dialog. */
  label: string
  cancelLabel?: string
  title: string
  description: string
  /** A red text button by default; `outline` for a regular secondary button. */
  variant?: 'ghost' | 'outline'
  size?: 'sm' | 'default'
  disabled?: boolean
  /** Runs when the dialog opens, e.g. to clear the error of the previous attempt. */
  onOpen?: () => void
  onConfirm: () => void
}

/** A confirmation dialog for a destructive action, opened by the caller. */
export function ConfirmDialog({
  open,
  onOpenChange,
  label,
  cancelLabel = 'Anuluj',
  title,
  description,
  onConfirm,
}: ConfirmDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel>{cancelLabel}</AlertDialogCancel>
          <AlertDialogAction variant="destructive" onClick={onConfirm}>
            {label}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}

/** A destructive button that asks for confirmation in a dialog before it runs the action. */
export function ConfirmButton({
  label,
  cancelLabel,
  title,
  description,
  variant = 'ghost',
  size = 'sm',
  disabled,
  onOpen,
  onConfirm,
}: ConfirmButtonProps) {
  const [open, setOpen] = useState(false)

  function openDialog() {
    onOpen?.()
    setOpen(true)
  }

  return (
    <>
      <Button
        variant={variant}
        size={size}
        type="button"
        className={cn(variant === 'ghost' && 'text-destructive hover:text-destructive')}
        disabled={disabled}
        onClick={openDialog}
      >
        {label}
      </Button>

      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        label={label}
        cancelLabel={cancelLabel}
        title={title}
        description={description}
        onConfirm={onConfirm}
      />
    </>
  )
}
