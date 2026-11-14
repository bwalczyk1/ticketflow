import { useMutation } from '@tanstack/react-query'
import { LogOut, Menu } from 'lucide-react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'
import type { Role } from '../api/types'
import { useAuth } from '../features/auth/AuthContext'
import { ErrorBanner } from './ErrorBanner'
import { ModeToggle } from './ModeToggle'
import { useTheme, type Theme } from './ThemeContext'

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
  const { theme, setTheme } = useTheme()

  const navItems = NAV_ITEMS.filter(
    (item) => !item.roles || (user && item.roles.includes(user.role)),
  )

  return (
    <div className="min-h-svh">
      <header className="border-b">
        <div className="mx-auto flex max-w-6xl items-center gap-x-6 px-4 py-3 sm:px-6">
          <Link className="text-lg font-bold tracking-tight" to="/">
            TicketFlow
          </Link>

          <nav className="hidden gap-1 md:flex" aria-label="Główna nawigacja">
            {navItems.map((item) => (
              <NavLink key={item.to} to={item.to} end className={navLinkClass}>
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="ml-auto hidden items-center gap-2 md:flex">
            {user && (
              <>
                <span className="max-w-48 truncate text-sm font-medium">{user.name}</span>
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

          {/* Below the md breakpoint everything moves into one menu. */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button className="ml-auto md:hidden" variant="outline" size="icon" type="button">
                <Menu />
                <span className="sr-only">Menu</span>
              </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="min-w-56">
              {user && <DropdownMenuLabel className="truncate">{user.name}</DropdownMenuLabel>}

              {navItems.map((item) => (
                <DropdownMenuItem key={item.to} asChild>
                  <Link to={item.to}>{item.label}</Link>
                </DropdownMenuItem>
              ))}

              {!user && !isLoading && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem asChild>
                    <Link to="/login">Zaloguj się</Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link to="/register">Załóż konto</Link>
                  </DropdownMenuItem>
                </>
              )}

              <DropdownMenuSeparator />
              <DropdownMenuLabel>Motyw</DropdownMenuLabel>
              <DropdownMenuRadioGroup value={theme} onValueChange={(value) => setTheme(value as Theme)}>
                <DropdownMenuRadioItem value="light">Jasny</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="dark">Ciemny</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="system">Systemowy</DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>

              {user && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem disabled={logoutMutation.isPending} onSelect={() => logoutMutation.mutate()}>
                    <LogOut />
                    Wyloguj
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
        <ErrorBanner error={logoutMutation.error} />
        <Outlet />
      </main>
    </div>
  )
}
