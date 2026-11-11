import type { RouteObject } from 'react-router-dom'
import { OrderPage } from './OrderPage'
import { OrdersPage } from './OrdersPage'

export const checkoutRoutes: RouteObject[] = [
  { path: 'orders', element: <OrdersPage /> },
  { path: 'orders/:id', element: <OrderPage /> },
]
