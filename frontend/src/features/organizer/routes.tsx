import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const organizerRoutes: RouteObject[] = [
  { path: 'organizer', element: <PlaceholderPage title="Moje wydarzenia" /> },
]
