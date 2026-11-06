import { useMutation } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { getFieldErrors } from '../../api/errors'
import { ErrorBanner } from '../../components/ErrorBanner'
import { FormField } from '../../components/FormField'
import { useAuth } from './AuthContext'

export function LoginPage() {
  const { login } = useAuth()
  const location = useLocation()
  const mutation = useMutation({ mutationFn: login })
  const fieldErrors = getFieldErrors(mutation.error)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)

    mutation.mutate({
      email: String(form.get('email')),
      password: String(form.get('password')),
    })
  }

  return (
    <section className="card card--narrow">
      <h1>Logowanie</h1>
      <ErrorBanner error={mutation.error} />

      <form className="form" onSubmit={handleSubmit}>
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
          autoComplete="current-password"
          required
          error={fieldErrors.password}
        />
        <button className="button" type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Logowanie…' : 'Zaloguj się'}
        </button>
      </form>

      <p className="muted">
        Nie masz konta?{' '}
        <Link to="/register" state={location.state}>
          Załóż konto
        </Link>
      </p>
    </section>
  )
}
