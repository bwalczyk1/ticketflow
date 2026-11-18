import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PAGE_TITLE } from '@/lib/styles'

interface PlaceholderPageProps {
  title: string
}

interface MessagePageProps {
  title: string
  message: string
  backLink?: boolean
}

export function LoadingPage() {
  return (
    <div role="status" className="grid gap-3">
      <p className="text-sm text-muted-foreground">Ładowanie…</p>
      <Skeleton className="h-8 w-2/3 max-w-sm" />
      <Skeleton className="h-24 w-full" />
    </div>
  )
}

function MessagePage({ title, message, backLink = false }: MessagePageProps) {
  return (
    <section className="grid gap-2">
      <h1 className={PAGE_TITLE}>{title}</h1>
      <p className="text-muted-foreground">{message}</p>
      {backLink && (
        <Button variant="link" className="w-fit px-0" asChild>
          <Link to="/">Wróć do listy wydarzeń</Link>
        </Button>
      )}
    </section>
  )
}

export function PlaceholderPage({ title }: PlaceholderPageProps) {
  return <MessagePage title={title} message="Ta strona jest w przygotowaniu." />
}

export function ForbiddenPage() {
  return <MessagePage title="Brak dostępu" message="Nie masz uprawnień do tej strony." backLink />
}

export function NotFoundPage() {
  return <MessagePage title="Nie znaleziono strony" message="Ta strona nie istnieje." backLink />
}

export function CrashPage() {
  return (
    <main className="mx-auto grid max-w-6xl gap-2 px-4 py-6 sm:px-6">
      <h1 className={PAGE_TITLE}>Coś poszło nie tak</h1>
      <p className="text-muted-foreground">Odśwież stronę i spróbuj ponownie.</p>
      <Button variant="link" className="w-fit px-0" asChild>
        <a href="/">Wróć do listy wydarzeń</a>
      </Button>
    </main>
  )
}
