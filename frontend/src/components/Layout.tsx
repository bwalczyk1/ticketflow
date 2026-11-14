import { useMutation } from '@tanstack/react-query'
import { LogOut } from 'lucide-react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { Role } from '../api/types'
import { useAuth } from '../features/auth/AuthContext'
import { ErrorBanner } from './ErrorBanner'
import { ModeToggle } from './ModeToggle'

interface NavItem {
  to: string
  label: string
  roles?: Role[]
}

const NAV_ITEMS: NavItem[] = [
  { to: '/', label: 'Wydarzenia' },
  { to: '/orders', label: 'Moje zamówienia', roles: ['ATTENDEE'] },
  { to: '/tickets', label: 'Moje bilety', roles: ['ATTENDEE'] },
  { to: '/waitlist', label: 'Lista oczekujących', roles: ['ATTENDEE'] },
  { to: '/organizer', label: 'Moje wydarzenia', roles: ['ORGANIZER'] },
  { to: '/organizer/check-in', label: 'Check-in', roles: ['ORGANIZER'] },
  { to: '/admin', label: 'Admin', roles: ['ADMIN'] },
]

function navLinkClass({ isActive }: { isActive: boolean }) {
  return cn(
    'shrink-0 rounded-md px-3 py-2 text-sm font-medium transition-colors',
    isActive ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground',
  )
}

export function Layout() {
  const { user, isLoading, logout } = useAuth()
  const logoutMutation = useMutation({ mutationFn: logout })

  const navItems = NAV_ITEMS.filter(
    (item) => !item.roles || (user && item.roles.includes(user.role)),
  )

  return (
    <div className="min-h-svh">
      <header className="border-b">
        {/* Below the md breakpoint the navigation moves to its own row and scrolls sideways. */}
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 sm:px-6">
          <Link className="text-lg font-bold tracking-tight" to="/">
            TicketFlow
          </Link>

          <nav
            className="order-last -mx-1 flex w-full gap-1 overflow-x-auto md:order-none md:w-auto"
            aria-label="Główna nawigacja"
          >
            {navItems.map((item) => (
              <NavLink key={item.to} to={item.to} end className={navLinkClass}>
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="ml-auto flex items-center gap-2">
            {user && (
              <>
                <span className="hidden max-w-48 truncate text-sm font-medium sm:inline">
                  {user.name}
                </span>
                <Button
                  variant="outline"
                  type="button"
                  disabled={logoutMutation.isPending}
                  onClick={() => logoutMutation.mutate()}
                >
                  <LogOut />
                  Wyloguj
                </Button>
              </>
            )}

            {!user && !isLoading && (
              <>
                <Button variant="ghost" asChild>
                  <Link to="/login">Zaloguj się</Link>
                </Button>
                <Button asChild>
                  <Link to="/register">Załóż konto</Link>
                </Button>
              </>
            )}

            <ModeToggle />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <ErrorBanner error={logoutMutation.error} />
        <Outlet />
      </main>
    </div>
  )
}
