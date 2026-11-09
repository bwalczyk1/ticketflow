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
    <div className="banner" role="alert">
      <p>{getErrorMessage(error)}</p>
      {requestId && <p className="banner__request-id">Identyfikator błędu: {requestId}</p>}
    </div>
  )
}
