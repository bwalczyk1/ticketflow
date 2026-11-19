import type { RouteObject } from 'react-router-dom'
import { CheckInPage } from './CheckInPage'

export const checkinRoutes: RouteObject[] = [{ path: 'organizer/check-in', element: <CheckInPage /> }]
