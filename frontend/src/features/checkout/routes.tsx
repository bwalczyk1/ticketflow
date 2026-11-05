import type { RouteObject } from 'react-router-dom'
import { PlaceholderPage } from '../../components/StatusPages'

export const checkoutRoutes: RouteObject[] = [
  { path: 'orders', element: <PlaceholderPage title="Moje zamówienia" /> },
]
