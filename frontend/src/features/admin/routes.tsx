import type { RouteObject } from 'react-router-dom'
import { AdminPage } from './AdminPage'

export const adminRoutes: RouteObject[] = [{ path: 'admin', element: <AdminPage /> }]
