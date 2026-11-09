import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import type { User } from '../../api/types'
import { AuthContext } from './AuthContext'
import * as authApi from './authApi'

interface AuthProviderProps {
  children: ReactNode
}

export function AuthProvider({ children }: AuthProviderProps) {
  const queryClient = useQueryClient()

  const { data: user = null, isPending } = useQuery({
    queryKey: authApi.currentUserKey,
    queryFn: authApi.fetchCurrentUser,
  })

  // Data cached for the previous session must not leak into the new one.
  function startSession(newUser: User) {
    queryClient.removeQueries({
      predicate: (query) => query.queryKey[0] !== authApi.currentUserKey[0],
    })

    queryClient.setQueryData(authApi.currentUserKey, newUser)
  }

  async function login(payload: authApi.LoginPayload) {
    startSession(await authApi.login(payload))
  }

  async function register(payload: authApi.RegisterPayload) {
    startSession(await authApi.register(payload))
  }

  async function logout() {
    await authApi.logout()

    // A full reload clears all client state, and no route guard reacts to the logout.
    window.location.assign('/')
  }

  return (
    <AuthContext value={{ user, isLoading: isPending, login, register, logout }}>
      {children}
    </AuthContext>
  )
}
