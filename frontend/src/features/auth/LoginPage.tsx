import { useMutation } from '@tanstack/react-query'
import type { FormEvent } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card'
import { PAGE_TITLE } from '@/lib/styles'
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
    <Card className="mx-auto w-full max-w-sm">
      <CardHeader>
        <h1 className={PAGE_TITLE}>Logowanie</h1>
      </CardHeader>

      <CardContent>
        <ErrorBanner error={mutation.error} />

        <form className="grid gap-4" noValidate onSubmit={handleSubmit}>
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
          <Button className="w-full" type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Logowanie…' : 'Zaloguj się'}
          </Button>
        </form>
      </CardContent>

      <CardFooter>
        <p className="text-sm text-muted-foreground">
          Nie masz konta?{' '}
          <Link
            className="font-medium text-foreground underline underline-offset-4"
            to="/register"
            state={location.state}
          >
            Załóż konto
          </Link>
        </p>
      </CardFooter>
    </Card>
  )
}
