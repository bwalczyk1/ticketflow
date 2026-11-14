import type { RouteObject } from 'react-router-dom'
import { MyTicketsPage } from './MyTicketsPage'

export const ticketsRoutes: RouteObject[] = [{ path: 'tickets', element: <MyTicketsPage /> }]
