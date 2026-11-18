import { CircleAlert } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { ApiError, getErrorMessage } from '../api/errors'

interface ErrorBannerProps {
  error: unknown
}

export function ErrorBanner({ error }: ErrorBannerProps) {
  if (!error) {
    return null
  }

  const requestId = error instanceof ApiError && error.status >= 500 ? error.requestId : null

  return (
    <Alert variant="destructive" className="mb-4">
      <CircleAlert />
      <AlertTitle className="line-clamp-none">{getErrorMessage(error)}</AlertTitle>
      {requestId && <AlertDescription>Identyfikator błędu: {requestId}</AlertDescription>}
    </Alert>
  )
}
