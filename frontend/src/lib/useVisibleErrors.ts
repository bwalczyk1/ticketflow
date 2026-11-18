import { useState, type FormEvent } from 'react'

/**
 * Hides a field's error once the user edits that field, so a corrected field does not keep the
 * message from the previous submit. Pass `markEdited` to the form's `onChange` and call
 * `resetEdited` on every submit.
 */
export function useVisibleErrors(errors: Record<string, string>) {
  const [edited, setEdited] = useState<string[]>([])
  const visible = Object.fromEntries(Object.entries(errors).filter(([field]) => !edited.includes(field)))

  function markEdited(event: FormEvent<HTMLFormElement>) {
    const target = event.target

    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
      setEdited((current) => (current.includes(target.name) ? current : [...current, target.name]))
    }
  }

  function resetEdited() {
    setEdited([])
  }

  return { errors: visible, markEdited, resetEdited }
}
