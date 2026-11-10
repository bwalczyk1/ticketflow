import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const ticketsRoutes: RouteObject[] = [
  { path: 'tickets', element: <PlaceholderPage title="Moje bilety" /> },
]
