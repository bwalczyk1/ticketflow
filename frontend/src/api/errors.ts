import type { ApiErrorBody, FieldError } from './types'

const GENERIC_MESSAGE = 'Coś poszło nie tak. Spróbuj ponownie.'

const ERROR_MESSAGES: Record<string, string> = {
  VALIDATION_ERROR: 'Popraw zaznaczone pola formularza.',
  UNAUTHENTICATED: 'Zaloguj się, aby kontynuować.',
  INVALID_CREDENTIALS: 'Nieprawidłowy e-mail lub hasło.',
  FORBIDDEN: 'Nie masz uprawnień do tej operacji.',
  NOT_FOUND: 'Nie znaleziono szukanego zasobu.',
  CSRF_TOKEN_MISMATCH: 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
  EMAIL_TAKEN: 'Konto z tym adresem e-mail już istnieje.',
  TICKETS_SOLD_OUT: 'Bilety z tej puli zostały wyprzedane.',
  RESERVATION_CONFLICT: 'Zbyt duży ruch przy tej puli biletów. Spróbuj ponownie.',
  TICKET_LIMIT_EXCEEDED: 'Przekroczono limit biletów na jedno wydarzenie.',
  RESERVATION_EXPIRED: 'Rezerwacja wygasła. Wybierz bilety jeszcze raz.',
  PAYMENT_ALREADY_STARTED: 'Płatność za to zamówienie została już rozpoczęta.',
  SALES_CLOSED: 'Sprzedaż biletów na to wydarzenie jest zamknięta.',
  REFUND_NOT_ALLOWED: 'Zwrot jest możliwy tylko do 48 godzin przed wydarzeniem.',
  TICKET_ALREADY_USED: 'Ten bilet został już wykorzystany.',
  POOL_PROTECTED: 'Nie można zmniejszyć puli poniżej liczby sprzedanych i zablokowanych biletów.',
  EVENT_HAS_SALES: 'Nie można usunąć wydarzenia, na które sprzedano lub zablokowano bilety.',
  EVENT_CLOSED: 'To wydarzenie jest zakończone lub odwołane, więc nie można go zmieniać.',
  EVENT_NOT_PUBLISHABLE:
    'Nie można opublikować wydarzenia: potrzebuje co najmniej jednej puli biletów i daty startu w przyszłości.',
  INVALID_STATUS_TRANSITION: 'Ta zmiana statusu wydarzenia jest niedozwolona.',
  INTERNAL_ERROR: 'Błąd serwera. Spróbuj ponownie później.',
  NETWORK_ERROR: 'Brak połączenia z serwerem. Spróbuj ponownie.',
}

export class ApiError extends Error {
  status: number
  code: string
  details: FieldError[]
  requestId: string | null

  constructor(
    status: number,
    code: string,
    message: string,
    details: FieldError[] = [],
    requestId: string | null = null,
  ) {
    super(message)

    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.details = details
    this.requestId = requestId
  }
}

export async function toApiError(response: Response): Promise<ApiError> {
  const body: ApiErrorBody | null = await response.json().catch(() => null)
  const error = body?.error

  if (!error) {
    return new ApiError(response.status, 'UNKNOWN_ERROR', GENERIC_MESSAGE)
  }

  return new ApiError(
    response.status,
    error.code,
    error.message,
    error.details,
    error.requestId,
  )
}

export function getErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return GENERIC_MESSAGE
  }

  return ERROR_MESSAGES[error.code] ?? error.message
}

/** Returns the first message for each field, keyed by the field name. */
export function getFieldErrors(error: unknown): Record<string, string> {
  const fieldErrors: Record<string, string> = {}

  if (error instanceof ApiError) {
    for (const { field, message } of error.details) {
      fieldErrors[field] ??= message
    }
  }

  return fieldErrors
}
