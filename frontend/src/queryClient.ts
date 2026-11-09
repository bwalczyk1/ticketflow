import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query'
import { ApiError } from './api/errors'
import { currentUserKey } from './features/auth/authApi'
import { router } from './router'

/** Any 401 outside the login form means the session is gone, on every page. */
function redirectToLoginOn401(error: Error) {
  if (!(error instanceof ApiError) || error.status !== 401) {
    return
  }

  if (error.code === 'INVALID_CREDENTIALS') {
    return
  }

  const { pathname, search } = router.state.location

  queryClient.setQueryData(currentUserKey, null)
  router.navigate('/login', { replace: true, state: { from: pathname + search } })
}

export const queryClient = new QueryClient({
  queryCache: new QueryCache({ onError: redirectToLoginOn401 }),
  mutationCache: new MutationCache({ onError: redirectToLoginOn401 }),
  defaultOptions: {
    queries: {
      retry: false,
      staleTime: 30_000,
    },
  },
})
