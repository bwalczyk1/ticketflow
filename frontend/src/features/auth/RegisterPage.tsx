import { useMutation } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { getFieldErrors } from '../../api/errors'
import { ErrorBanner } from '../../components/ErrorBanner'
import { FormField } from '../../components/FormField'
import { useAuth } from './AuthContext'

export function RegisterPage() {
  const { register } = useAuth()
  const location = useLocation()
  const mutation = useMutation({ mutationFn: register })
  const fieldErrors = getFieldErrors(mutation.error)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)

    mutation.mutate({
      name: String(form.get('name')),
      email: String(form.get('email')),
      password: String(form.get('password')),
    })
  }

  return (
    <section className="card card--narrow">
      <h1>Rejestracja</h1>
      <ErrorBanner error={mutation.error} />

      <form className="form" onSubmit={handleSubmit}>
        <FormField
          label="Imię i nazwisko"
          name="name"
          autoComplete="name"
          required
          error={fieldErrors.name}
        />
        <FormField
          label="E-mail"
          name="email"
          type="email"
          autoComplete="email"
          required
          error={fieldErrors.email}
        />
        <FormField
          label="Hasło"
          name="password"
          type="password"
          autoComplete="new-password"
          required
          hint="Od 10 do 72 znaków."
          error={fieldErrors.password}
        />
        <button className="button" type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Tworzenie konta…' : 'Załóż konto'}
        </button>
      </form>

      <p className="muted">
        Masz już konto?{' '}
        <Link to="/login" state={location.state}>
          Zaloguj się
        </Link>
      </p>
    </section>
  )
}
