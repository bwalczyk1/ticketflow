import { ArrowLeft } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'

interface BackLinkProps {
  to: string
  label: string
}

export function BackLink({ to, label }: BackLinkProps) {
  return (
    <Button variant="ghost" size="sm" className="mb-4 -ml-3" asChild>
      <Link to={to}>
        <ArrowLeft />
        {label}
      </Link>
    </Button>
  )
}
