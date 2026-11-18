import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Pagination as PaginationNav,
  PaginationContent,
  PaginationItem,
} from '@/components/ui/pagination'
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
    <PaginationNav aria-label="Paginacja" className="mt-6">
      <PaginationContent>
        <PaginationItem>
          <Button
            variant="ghost"
            type="button"
            disabled={meta.page <= 1}
            onClick={() => onPageChange(meta.page - 1)}
          >
            <ChevronLeft />
            Poprzednia
          </Button>
        </PaginationItem>

        <PaginationItem className="px-2 text-sm text-muted-foreground">
          Strona {meta.page} z {meta.totalPages}
        </PaginationItem>

        <PaginationItem>
          <Button
            variant="ghost"
            type="button"
            disabled={meta.page >= meta.totalPages}
            onClick={() => onPageChange(meta.page + 1)}
          >
            Następna
            <ChevronRight />
          </Button>
        </PaginationItem>
      </PaginationContent>
    </PaginationNav>
  )
}
