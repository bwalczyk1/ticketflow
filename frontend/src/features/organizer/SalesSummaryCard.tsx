import { useQuery } from '@tanstack/react-query'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ErrorBanner } from '../../components/ErrorBanner'
import { formatPrice } from '../../lib/money'
import { fetchSales, organizerKey } from './organizerApi'

/** Sold tickets, revenue, refunds and check-ins of one event. */
export function SalesSummaryCard({ eventId }: { eventId: string }) {
  const { data, error } = useQuery({
    queryKey: [...organizerKey, 'sales', eventId],
    queryFn: () => fetchSales(eventId),
    staleTime: 0,
  })

  const figures = data
    ? [
        { label: 'Sprzedane bilety', value: String(data.ticketsSold) },
        { label: 'Przychód', value: formatPrice(data.revenue) },
        { label: 'Zwroty', value: formatPrice(data.refunds) },
        { label: 'Odprawieni goście', value: String(data.checkedIn) },
      ]
    : []

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardTitle>
          <h2>Sprzedaż</h2>
        </CardTitle>
        <CardDescription>Liczby odświeżają się w tle, więc mogą się spóźniać o kilka sekund.</CardDescription>
      </CardHeader>

      <CardContent>
        <ErrorBanner error={error} />

        {!data && !error && <p className="text-sm text-muted-foreground">Ładowanie…</p>}

        {data && (
          <dl className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            {figures.map((figure) => (
              <div key={figure.label}>
                <dt className="text-sm text-muted-foreground">{figure.label}</dt>
                <dd className="text-xl font-semibold break-words">{figure.value}</dd>
              </div>
            ))}
          </dl>
        )}
      </CardContent>
    </Card>
  )
}
