import { Minus, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface QuantityPickerProps {
  /** Read out by screen readers, e.g. "Liczba biletów: Normalny". */
  label: string
  value: number
  min: number
  max: number
  onChange: (value: number) => void
}

/** A "-" / number / "+" stepper. */
export function QuantityPicker({ label, value, min, max, onChange }: QuantityPickerProps) {
  return (
    <div className="flex items-center gap-2" role="group" aria-label={label}>
      <Button
        variant="outline"
        size="icon-sm"
        type="button"
        aria-label="Mniej"
        disabled={value <= min}
        onClick={() => onChange(value - 1)}
      >
        <Minus />
      </Button>

      <span className="w-6 text-center font-medium tabular-nums" aria-live="polite">
        {value}
      </span>

      <Button
        variant="outline"
        size="icon-sm"
        type="button"
        aria-label="Więcej"
        disabled={value >= max}
        onClick={() => onChange(value + 1)}
      >
        <Plus />
      </Button>
    </div>
  )
}
