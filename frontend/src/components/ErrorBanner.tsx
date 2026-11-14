import { CircleAlert } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { ApiError, getErrorMessage, type ErrorMessages } from '../api/errors'

interface ErrorBannerProps {
  error: unknown
  /** Action-specific wording for some error codes. */
  messages?: ErrorMessages
}

export function ErrorBanner({ error, messages }: ErrorBannerProps) {
  if (!error) {
    return null
  }

  const requestId = error instanceof ApiError && error.status >= 500 ? error.requestId : null

  return (
    <Alert variant="destructive" className="mb-4">
      <CircleAlert />
      <AlertTitle className="line-clamp-none">{getErrorMessage(error, messages)}</AlertTitle>
      {requestId && <AlertDescription>Identyfikator błędu: {requestId}</AlertDescription>}
    </Alert>
  )
}
