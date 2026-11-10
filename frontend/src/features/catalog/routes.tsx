import type { RouteObject } from 'react-router-dom'
import { EventDetailsPage } from './EventDetailsPage'
import { EventListPage } from './EventListPage'

export const catalogRoutes: RouteObject[] = [
  { index: true, element: <EventListPage /> },
  { path: 'events/:id', element: <EventDetailsPage /> },
]
