import { useId, type TextareaHTMLAttributes } from 'react'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  error?: string
}

export function TextAreaField({ label, error, ...textareaProps }: TextAreaFieldProps) {
  const id = useId()
  const errorId = `${id}-error`

  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>{label}</Label>
      <Textarea
        id={id}
        className="min-h-36"
        aria-invalid={Boolean(error)}
        aria-describedby={error ? errorId : undefined}
        {...textareaProps}
      />
      {error && (
        <p id={errorId} className="text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  )
}
