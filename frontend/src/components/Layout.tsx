import { useMutation } from '@tanstack/react-query'
import { Link, NavLink, Outlet } from 'react-router-dom'
import type { Role } from '../api/types'
import { useAuth } from '../features/auth/AuthContext'
import { ErrorBanner } from './ErrorBanner'

interface NavItem {
  to: string
  label: string
  roles?: Role[]
}

const NAV_ITEMS: NavItem[] = [
  { to: '/', label: 'Wydarzenia' },
  { to: '/orders', label: 'Moje zamówienia', roles: ['ATTENDEE'] },
  { to: '/tickets', label: 'Moje bilety', roles: ['ATTENDEE'] },
  { to: '/organizer', label: 'Moje wydarzenia', roles: ['ORGANIZER'] },
  { to: '/organizer/check-in', label: 'Check-in', roles: ['ORGANIZER'] },
  { to: '/admin', label: 'Użytkownicy', roles: ['ADMIN'] },
]

export function Layout() {
  const { user, isLoading, logout } = useAuth()
  const logoutMutation = useMutation({ mutationFn: logout })

  const navItems = NAV_ITEMS.filter(
    (item) => !item.roles || (user && item.roles.includes(user.role)),
  )

  return (
    <div className="layout">
      <header className="header">
        <div className="header__inner">
          <Link className="header__brand" to="/">
            TicketFlow
          </Link>

          <nav className="header__nav" aria-label="Główna nawigacja">
            {navItems.map((item) => (
              <NavLink key={item.to} to={item.to} end>
                {item.label}
              </NavLink>
            ))}
          </nav>

          {user && (
            <div className="header__user">
              <span className="muted">{user.name}</span>
              <button
                className="button button--secondary"
                type="button"
                disabled={logoutMutation.isPending}
                onClick={() => logoutMutation.mutate()}
              >
                Wyloguj
              </button>
            </div>
          )}

          {!user && !isLoading && (
            <div className="header__user">
              <Link to="/login">Zaloguj się</Link>
              <Link to="/register">Załóż konto</Link>
            </div>
          )}
        </div>
      </header>

      <main className="main">
        <ErrorBanner error={logoutMutation.error} />
        <Outlet />
      </main>
    </div>
  )
}
