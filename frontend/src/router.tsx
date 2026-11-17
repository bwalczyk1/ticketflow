import { createBrowserRouter } from 'react-router-dom'
import { Layout } from './components/Layout'
import { CrashPage, NotFoundPage } from './components/StatusPages'
import { AdminPage } from './features/admin/AdminPage'
import { RequireAuth, RequireGuest } from './features/auth/guards'
import { LoginPage } from './features/auth/LoginPage'
import { RegisterPage } from './features/auth/RegisterPage'
import { EventDetailsPage } from './features/catalog/EventDetailsPage'
import { EventListPage } from './features/catalog/EventListPage'
import { CheckInPage } from './features/checkin/CheckInPage'
import { OrderPage } from './features/checkout/OrderPage'
import { OrdersPage } from './features/checkout/OrdersPage'
import { EventFormPage } from './features/organizer/EventFormPage'
import { MyEventsPage } from './features/organizer/MyEventsPage'
import { TicketTypesPage } from './features/organizer/TicketTypesPage'
import { MyTicketsPage } from './features/tickets/MyTicketsPage'
import { WaitlistPage } from './features/waitlist/WaitlistPage'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    errorElement: <CrashPage />,
    children: [
      { index: true, element: <EventListPage /> },
      { path: 'events/:id', element: <EventDetailsPage /> },
      {
        element: <RequireGuest />,
        children: [
          { path: 'login', element: <LoginPage /> },
          { path: 'register', element: <RegisterPage /> },
        ],
      },
      {
        element: <RequireAuth roles={['ATTENDEE']} />,
        children: [
          { path: 'orders', element: <OrdersPage /> },
          { path: 'orders/:id', element: <OrderPage /> },
          { path: 'tickets', element: <MyTicketsPage /> },
          { path: 'waitlist', element: <WaitlistPage /> },
        ],
      },
      {
        element: <RequireAuth roles={['ORGANIZER']} />,
        children: [
          { path: 'organizer', element: <MyEventsPage /> },
          { path: 'organizer/events/new', element: <EventFormPage /> },
          { path: 'organizer/events/:id/edit', element: <EventFormPage /> },
          { path: 'organizer/events/:id/ticket-types', element: <TicketTypesPage /> },
          { path: 'organizer/check-in', element: <CheckInPage /> },
        ],
      },
      {
        element: <RequireAuth roles={['ADMIN']} />,
        children: [{ path: 'admin', element: <AdminPage /> }],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
