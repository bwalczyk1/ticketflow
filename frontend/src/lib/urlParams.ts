import { useSearchParams } from 'react-router-dom'

/** A status tab: the value kept in the URL ('' for all statuses) and its label. */
export interface StatusFilter<T extends string> {
  value: T | ''
  label: string
}

/** The `page` URL parameter, and a setter that keeps the other parameters (filters, tabs). */
export function usePageParam(): [number, (page: number) => void] {
  const [params, setParams] = useSearchParams()
  const page = Number(params.get('page')) || 1

  function setPage(next: number) {
    setParams((current) => {
      current.set('page', String(next))

      return current
    })
  }

  return [page, setPage]
}

/** The `status` URL parameter, limited to the given tabs. Switching the tab starts from page 1. */
export function useStatusParam<T extends string>(filters: StatusFilter<T>[]): [T | undefined, (value: string) => void] {
  const [params, setParams] = useSearchParams()
  const value = params.get('status')
  const status = filters.find((filter) => filter.value !== '' && filter.value === value)?.value as T | undefined

  function setStatus(next: string) {
    setParams(next ? { status: next } : {})
  }

  return [status, setStatus]
}
