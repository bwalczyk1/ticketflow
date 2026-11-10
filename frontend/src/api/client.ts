import { ApiError, toApiError } from './errors'

type QueryParams = Record<string, string | number | undefined>

interface RequestOptions {
  method?: string
  body?: unknown
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`
  const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix))

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null
}

function withQuery(path: string, query: QueryParams = {}): string {
  const params = new URLSearchParams()

  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== '') {
      params.set(key, String(value))
    }
  }

  const queryString = params.toString()

  return queryString ? `${path}?${queryString}` : path
}

async function send(path: string, { method = 'GET', body }: RequestOptions): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const xsrfToken = readCookie('XSRF-TOKEN')

  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  try {
    return await fetch(path, {
      method,
      headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'NETWORK_ERROR', 'Network request failed')
  }
}

export async function fetchCsrfCookie(): Promise<void> {
  const response = await send('/sanctum/csrf-cookie', {})

  if (!response.ok) {
    throw await toApiError(response)
  }
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  let response = await send(path, options)

  if (response.status === 419) {
    await fetchCsrfCookie()
    response = await send(path, options)
  }

  if (!response.ok) {
    throw await toApiError(response)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json()
}

export const api = {
  get: <T>(path: string, query?: QueryParams) => request<T>(withQuery(path, query)),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}
