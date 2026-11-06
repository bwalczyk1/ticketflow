import type { RouteObject } from 'react-router-dom'
import { EventFormPage } from './EventFormPage'
import { MyEventsPage } from './MyEventsPage'
import { TicketTypesPage } from './TicketTypesPage'

export const organizerRoutes: RouteObject[] = [
  { path: 'organizer', element: <MyEventsPage /> },
  { path: 'organizer/events/new', element: <EventFormPage /> },
  { path: 'organizer/events/:id/edit', element: <EventFormPage /> },
  { path: 'organizer/events/:id/ticket-types', element: <TicketTypesPage /> },
]
