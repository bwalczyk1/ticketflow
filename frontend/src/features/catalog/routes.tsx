import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const catalogRoutes: RouteObject[] = [
  { index: true, element: <PlaceholderPage title="Wydarzenia" /> },
]
