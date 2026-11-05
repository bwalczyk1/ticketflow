import { Link } from 'react-router-dom'

interface PlaceholderPageProps {
  title: string
}

export function LoadingPage() {
  return <p className="muted">Ładowanie…</p>
}

export function PlaceholderPage({ title }: PlaceholderPageProps) {
  return (
    <section>
      <h1>{title}</h1>
      <p className="muted">Ta strona jest w przygotowaniu.</p>
    </section>
  )
}

export function ForbiddenPage() {
  return (
    <section>
      <h1>Brak dostępu</h1>
      <p className="muted">Nie masz uprawnień do tej strony.</p>
      <Link to="/">Wróć do listy wydarzeń</Link>
    </section>
  )
}

export function NotFoundPage() {
  return (
    <section>
      <h1>Nie znaleziono strony</h1>
      <p className="muted">Ta strona nie istnieje.</p>
      <Link to="/">Wróć do listy wydarzeń</Link>
    </section>
  )
}

export function CrashPage() {
  return (
    <main className="main">
      <h1>Coś poszło nie tak</h1>
      <p className="muted">Odśwież stronę i spróbuj ponownie.</p>
      <a href="/">Wróć do listy wydarzeń</a>
    </main>
  )
}
