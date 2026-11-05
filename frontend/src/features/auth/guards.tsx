import { Navigate, Outlet, useLocation } from 'react-router-dom'
import type { Role } from '../../api/types'
import { ForbiddenPage, LoadingPage } from '../../components/StatusPages'
import { useAuth } from './AuthContext'

interface RequireAuthProps {
  roles?: Role[]
}

export function RequireAuth({ roles }: RequireAuthProps) {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <LoadingPage />
  }

  if (!user) {
    const from = location.pathname + location.search

    return <Navigate to="/login" replace state={{ from }} />
  }

  if (roles && !roles.includes(user.role)) {
    return <ForbiddenPage />
  }

  return <Outlet />
}

/** Also finishes the login: once the user is known, it goes back to the target path. */
export function RequireGuest() {
  const { user, isLoading } = useAuth()
  const location = useLocation()
  const state = location.state as { from?: string } | null

  if (isLoading) {
    return <LoadingPage />
  }

  if (user) {
    return <Navigate to={state?.from ?? '/'} replace />
  }

  return <Outlet />
}
