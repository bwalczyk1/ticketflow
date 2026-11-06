import { api, fetchCsrfCookie } from '../../api/client'
import { ApiError } from '../../api/errors'
import type { User } from '../../api/types'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
}

export const currentUserKey = ['currentUser']

/** Resolves to null for a guest, because 401 is the normal answer here. */
export async function fetchCurrentUser(): Promise<User | null> {
  try {
    return await api.get<User>('/api/me')
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null
    }

    throw error
  }
}

export async function login(payload: LoginPayload): Promise<User> {
  await fetchCsrfCookie()

  return api.post<User>('/api/auth/login', payload)
}

export async function register(payload: RegisterPayload): Promise<User> {
  await fetchCsrfCookie()

  return api.post<User>('/api/auth/register', payload)
}

/** A 401 here means the session is already gone, which is what the user wanted. */
export async function logout(): Promise<void> {
  try {
    await api.post<void>('/api/auth/logout')
  } catch (error) {
    if (!(error instanceof ApiError && error.status === 401)) {
      throw error
    }
  }
}
