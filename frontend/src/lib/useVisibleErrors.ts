import { useState, type FormEvent } from 'react'

function omitFields(errors: Record<string, string>, fields: string[]): Record<string, string> {
  return Object.fromEntries(Object.entries(errors).filter(([field]) => !fields.includes(field)))
}

/**
 * The field errors a form shows: the server's errors from the last request plus the form's own
 * checks. A field's error disappears once the user edits that field, so a corrected field does not
 * keep an old message. Pass `markEdited` to the form's `onChange` and call `startRequest` right
 * before the request is sent, because only its answer replaces the old server errors.
 */
export function useVisibleErrors(serverErrors: Record<string, string>) {
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({})
  // Fields changed since the last request, whose server errors are out of date.
  const [edited, setEdited] = useState<string[]>([])
  const errors = { ...omitFields(serverErrors, edited), ...clientErrors }

  function markEdited(event: FormEvent<HTMLFormElement>) {
    const target = event.target

    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
      const { name } = target

      setEdited((current) => (current.includes(name) ? current : [...current, name]))
      setClientErrors((current) => omitFields(current, [name]))
    }
  }

  function startRequest() {
    setEdited([])
  }

  return { errors, markEdited, setClientErrors, startRequest }
}
