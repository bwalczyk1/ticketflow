import { Link } from 'react-router-dom'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Order } from '../../api/types'
import { formatDateTime } from '../../lib/dates'
import { formatPrice } from '../../lib/money'

export function OrderItemsCard({ order }: { order: Order }) {
  const { event } = order

  return (
    <Card>
      <CardHeader>
        <CardTitle className="break-words">
          <Link className="hover:underline" to={`/events/${event.id}`}>
            {event.title}
          </Link>
        </CardTitle>
        <CardDescription>
          {formatDateTime(event.startsAt, event.timezone)} · {event.venue}, {event.city}
        </CardDescription>
      </CardHeader>

      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="pl-0">Pula</TableHead>
              <TableHead>Ilość i cena</TableHead>
              <TableHead className="pr-0 text-right">Wartość</TableHead>
            </TableRow>
          </TableHeader>

          <TableBody>
            {order.items.map((item) => (
              <TableRow key={item.ticketTypeId}>
                <TableCell className="pl-0 font-medium whitespace-normal">{item.name}</TableCell>
                <TableCell>
                  {item.quantity} × {formatPrice(item.unitPrice)}
                </TableCell>
                <TableCell className="pr-0 text-right">
                  {formatPrice(item.quantity * item.unitPrice)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>

          <TableFooter className="bg-transparent">
            <TableRow>
              <TableCell className="pl-0 font-semibold" colSpan={2}>
                Razem
              </TableCell>
              <TableCell className="pr-0 text-right text-base font-semibold">
                {formatPrice(order.total)}
              </TableCell>
            </TableRow>
          </TableFooter>
        </Table>
      </CardContent>
    </Card>
  )
}
