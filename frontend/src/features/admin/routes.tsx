import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const adminRoutes: RouteObject[] = [
  { path: 'admin', element: <PlaceholderPage title="Użytkownicy" /> },
]
