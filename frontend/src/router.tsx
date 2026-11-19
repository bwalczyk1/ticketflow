import { createBrowserRouter } from 'react-router-dom'
import { Layout } from './components/Layout'
import { CrashPage, NotFoundPage } from './components/StatusPages'
import { adminRoutes } from './features/admin/routes'
import { RequireAuth } from './features/auth/guards'
import { authRoutes } from './features/auth/routes'
import { catalogRoutes } from './features/catalog/routes'
import { checkinRoutes } from './features/checkin/routes'
import { checkoutRoutes } from './features/checkout/routes'
import { organizerRoutes } from './features/organizer/routes'
import { ticketsRoutes } from './features/tickets/routes'
import { waitlistRoutes } from './features/waitlist/routes'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <Layout />,
    errorElement: <CrashPage />,
    children: [
      ...catalogRoutes,
      ...authRoutes,
      {
        element: <RequireAuth roles={['ATTENDEE']} />,
        children: [...checkoutRoutes, ...ticketsRoutes, ...waitlistRoutes],
      },
      {
        element: <RequireAuth roles={['ORGANIZER']} />,
        children: [...organizerRoutes, ...checkinRoutes],
      },
      {
        element: <RequireAuth roles={['ADMIN']} />,
        children: adminRoutes,
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
