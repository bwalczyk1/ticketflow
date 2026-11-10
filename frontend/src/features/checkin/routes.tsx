import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const checkinRoutes: RouteObject[] = [
  { path: 'organizer/check-in', element: <PlaceholderPage title="Check-in" /> },
]
