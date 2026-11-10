import type { RouteObject } from 'react-router-dom'
import { RequireGuest } from './guards'
import { LoginPage } from './LoginPage'
import { RegisterPage } from './RegisterPage'

export const authRoutes: RouteObject[] = [
  {
    element: <RequireGuest />,
    children: [
      { path: 'login', element: <LoginPage /> },
      { path: 'register', element: <RegisterPage /> },
    ],
  },
]
