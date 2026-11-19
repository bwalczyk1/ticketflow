import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Role, User } from '../../api/types'
import { ConfirmDialog } from '../../components/ConfirmButton'
import { ErrorBanner } from '../../components/ErrorBanner'
import { FormField } from '../../components/FormField'
import { Pagination } from '../../components/Pagination'
import { LoadingPage, RefreshingArea } from '../../components/StatusPages'
import { useAuth } from '../auth/AuthContext'
import { adminKey, changeUserRole, fetchUsers, ROLE_LABELS } from './adminApi'

const ROLES = Object.keys(ROLE_LABELS) as Role[]

/** The select cannot hold an empty value, so "all roles" has its own marker. */
const ALL_ROLES = 'ALL'

const ROLE_ERRORS = {
  NOT_FOUND: 'Nie znaleziono użytkownika. Odśwież listę.',
}

interface AdminUsersProps {
  params: URLSearchParams
  onParamsChange: (params: URLSearchParams) => void
}

interface RoleSelectProps {
  user: User
  disabled: boolean
  onChange: (role: Role) => void
}

function RoleSelect({ user, disabled, onChange }: RoleSelectProps) {
  return (
    <Select value={user.role} disabled={disabled} onValueChange={(role) => onChange(role as Role)}>
      <SelectTrigger className="w-full md:w-44" aria-label={`Rola: ${user.name}`}>
        <SelectValue />
      </SelectTrigger>

      <SelectContent>
        {ROLES.map((role) => (
          <SelectItem key={role} value={role}>
            {ROLE_LABELS[role]}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

export function AdminUsers({ params, onParamsChange }: AdminUsersProps) {
  const { user: currentUser } = useAuth()
  const queryClient = useQueryClient()
  const [pending, setPending] = useState<{ user: User; role: Role } | null>(null)
  const q = params.get('q') ?? ''
  const role = ROLES.find((candidate) => candidate === params.get('role'))
  const page = Number(params.get('page')) || 1

  const { data, error, isPending, isPlaceholderData } = useQuery({
    queryKey: [...adminKey, 'users', q, role, page],
    queryFn: () => fetchUsers(q, role, page),
    placeholderData: (previous) => previous,
  })

  const roleMutation = useMutation({
    mutationFn: ({ user, role: nextRole }: { user: User; role: Role }) => changeUserRole(user.id, nextRole),
    onSuccess: (updated) => toast.success(`Zmieniono rolę użytkownika ${updated.name} na: ${ROLE_LABELS[updated.role]}.`),
    onSettled: () => queryClient.invalidateQueries({ queryKey: [...adminKey, 'users'] }),
  })

  function applyFilters(next: { q?: string; role?: string }) {
    const nextParams = new URLSearchParams()

    if (next.q) {
      nextParams.set('q', next.q)
    }

    if (next.role && next.role !== ALL_ROLES) {
      nextParams.set('role', next.role)
    }

    onParamsChange(nextParams)
  }

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)

    applyFilters({ q: String(form.get('q') ?? '').trim(), role })
  }

  function changePage(next: number) {
    const nextParams = new URLSearchParams(params)

    nextParams.set('page', String(next))
    onParamsChange(nextParams)
  }

  function askForConfirmation(user: User, nextRole: Role) {
    // The banner of the previous attempt would otherwise look like the answer to this one.
    roleMutation.reset()
    setPending({ user, role: nextRole })
  }

  function renderRoleSelect(user: User) {
    return (
      <RoleSelect
        user={user}
        disabled={roleMutation.isPending || user.id === currentUser?.id}
        onChange={(nextRole) => askForConfirmation(user, nextRole)}
      />
    )
  }

  return (
    <>
      <form className="mb-4 grid gap-3 sm:grid-cols-[1fr_12rem_auto] sm:items-end" noValidate onSubmit={handleSearch}>
        {/* A new key puts the URL value back into the field when the filters change from outside. */}
        <FormField key={q} label="Szukaj" name="q" type="search" placeholder="Imię lub e-mail" defaultValue={q} />

        <div className="grid gap-2">
          <Label htmlFor="admin-role-filter">Rola</Label>

          <Select value={role ?? ALL_ROLES} onValueChange={(next) => applyFilters({ q, role: next })}>
            <SelectTrigger id="admin-role-filter" className="w-full">
              <SelectValue />
            </SelectTrigger>

            <SelectContent>
              <SelectItem value={ALL_ROLES}>Wszystkie</SelectItem>
              {ROLES.map((candidate) => (
                <SelectItem key={candidate} value={candidate}>
                  {ROLE_LABELS[candidate]}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <Button type="submit">Szukaj</Button>
      </form>

      <ErrorBanner error={error} />
      <ErrorBanner error={roleMutation.error} messages={ROLE_ERRORS} />

      {isPending && !error && <LoadingPage />}

      <RefreshingArea active={isPlaceholderData}>
        {data && data.data.length === 0 && (
          <p className="text-muted-foreground">Nie znaleziono użytkowników spełniających kryteria.</p>
        )}

        {data && data.data.length > 0 && (
          <>
            <div className="hidden rounded-xl border md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-4">Imię i nazwisko</TableHead>
                    <TableHead>E-mail</TableHead>
                    <TableHead className="pr-4">Rola</TableHead>
                  </TableRow>
                </TableHeader>

                <TableBody>
                  {data.data.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell className="pl-4 font-medium whitespace-normal">{user.name}</TableCell>
                      <TableCell className="whitespace-normal break-all">{user.email}</TableCell>
                      <TableCell className="pr-4">{renderRoleSelect(user)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <ul className="grid gap-3 md:hidden">
              {data.data.map((user) => (
                <li key={user.id}>
                  <Card className="py-4">
                    <CardContent className="grid gap-3 px-4">
                      <div>
                        <p className="font-medium break-words">{user.name}</p>
                        <p className="text-sm break-all text-muted-foreground">{user.email}</p>
                      </div>

                      {renderRoleSelect(user)}
                    </CardContent>
                  </Card>
                </li>
              ))}
            </ul>

            <Pagination meta={data.meta} onPageChange={changePage} />
          </>
        )}
      </RefreshingArea>

      {pending && (
        <ConfirmDialog
          open
          onOpenChange={(open) => !open && setPending(null)}
          label="Zmień rolę"
          title={`Zmienić rolę użytkownika ${pending.user.name}?`}
          description={`Rola zmieni się z „${ROLE_LABELS[pending.user.role]}” na „${ROLE_LABELS[pending.role]}”. Nowe uprawnienia zadziałają od razu.`}
          onConfirm={() => roleMutation.mutate(pending)}
        />
      )}
    </>
  )
}
