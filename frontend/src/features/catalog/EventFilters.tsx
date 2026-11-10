import { useState, type FormEvent } from 'react'
import type { URLSearchParamsInit } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { FormField } from '../../components/FormField'
import { CATEGORY_SUGGESTIONS } from '../../lib/categories'
import { plnToGrosze } from '../../lib/money'

const SORT_OPTIONS = [
  { value: 'startsAt', label: 'Data: od najbliższych' },
  { value: '-startsAt', label: 'Data: od najdalszych' },
  { value: 'title', label: 'Tytuł: A–Z' },
  { value: '-title', label: 'Tytuł: Z–A' },
]

const TEXT_PARAMS = ['q', 'city', 'category', 'from', 'to', 'priceMax', 'sort']

interface EventFiltersProps {
  params: URLSearchParams
  onApply: (params: URLSearchParamsInit) => void
}

/** Keep the filter values in the URL; this form only edits them and applies on submit. */
export function EventFilters({ params, onApply }: EventFiltersProps) {
  const [priceError, setPriceError] = useState<string>()
  const sort = params.get('sort')
  // Unlike a native <select>, this one would keep an unknown value from the URL, so fall back here.
  const sortValue = SORT_OPTIONS.find((option) => option.value === sort)?.value ?? 'startsAt'

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const form = new FormData(event.currentTarget)
    const next = new URLSearchParams()

    for (const name of TEXT_PARAMS) {
      const value = String(form.get(name) ?? '').trim()

      if (value) {
        next.set(name, value)
      }
    }

    if (next.has('priceMax') && plnToGrosze(next.get('priceMax') ?? '') === null) {
      setPriceError('Podaj kwotę w złotych, np. 120 lub 49,99.')

      return
    }

    setPriceError(undefined)
    onApply(next)
  }

  return (
    <Card className="mb-6 py-4">
      <CardContent className="px-4 sm:px-5">
        <form
          className="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-4"
          onSubmit={handleSubmit}
        >
          <FormField label="Szukaj" name="q" defaultValue={params.get('q') ?? ''} maxLength={160} />
          <FormField label="Miasto" name="city" defaultValue={params.get('city') ?? ''} />
          <FormField
            label="Kategoria"
            name="category"
            list="category-suggestions"
            defaultValue={params.get('category') ?? ''}
          />
          <datalist id="category-suggestions">
            {CATEGORY_SUGGESTIONS.map((category) => (
              <option key={category} value={category} />
            ))}
          </datalist>
          <FormField label="Od dnia" name="from" type="date" defaultValue={params.get('from') ?? ''} />
          <FormField label="Do dnia" name="to" type="date" defaultValue={params.get('to') ?? ''} />
          <FormField
            label="Cena maksymalna (zł)"
            name="priceMax"
            inputMode="decimal"
            placeholder="np. 150"
            defaultValue={params.get('priceMax') ?? ''}
            error={priceError}
          />

          <div className="grid gap-2">
            <Label htmlFor="sort">Sortowanie</Label>
            <Select name="sort" defaultValue={sortValue}>
              <SelectTrigger id="sort" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SORT_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex gap-2 self-end">
            <Button type="submit">Filtruj</Button>
            <Button variant="outline" type="button" onClick={() => onApply({})}>
              Wyczyść
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
