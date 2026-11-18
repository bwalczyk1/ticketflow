import type { Paginated } from '../api/types'

interface PaginationProps {
  meta: Paginated<unknown>['meta']
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
  if (meta.totalPages <= 1) {
    return null
  }

  return (
    <nav className="pagination" aria-label="Paginacja">
      <button
        className="button button--secondary"
        type="button"
        disabled={meta.page <= 1}
        onClick={() => onPageChange(meta.page - 1)}
      >
        Poprzednia
      </button>

      <span className="muted">
        Strona {meta.page} z {meta.totalPages}
      </span>

      <button
        className="button button--secondary"
        type="button"
        disabled={meta.page >= meta.totalPages}
        onClick={() => onPageChange(meta.page + 1)}
      >
        Następna
      </button>
    </nav>
  )
}
