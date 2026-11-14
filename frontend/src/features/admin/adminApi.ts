import { api } from '../../api/client'
import type { AdminEvent, Paginated, Role, User } from '../../api/types'

export const adminKey = ['admin']

export const ADMIN_PAGE_SIZE = 10

export const ROLE_LABELS: Record<Role, string> = {
  ADMIN: 'Administrator',
  ORGANIZER: 'Organizator',
  ATTENDEE: 'Uczestnik',
}

export function fetchUsers(q: string, role: Role | undefined, page: number): Promise<Paginated<User>> {
  return api.get<Paginated<User>>('/api/admin/users', { q, role, page, limit: ADMIN_PAGE_SIZE })
}

/** 409 OWN_ADMIN_ROLE_PROTECTED when an admin changes their own role, 404 for an unknown user. */
export function changeUserRole(id: number, role: Role): Promise<User> {
  return api.patch<User>(`/api/admin/users/${id}/role`, { role })
}

export function fetchAdminEvents(page: number): Promise<Paginated<AdminEvent>> {
  return api.get<Paginated<AdminEvent>>('/api/admin/events', { page, limit: ADMIN_PAGE_SIZE })
}
