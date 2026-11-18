import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useStatusParam, type StatusFilter } from '../lib/urlParams'

/** Tabs that filter a list by status, kept in the `status` URL parameter. */
export function StatusTabs<T extends string>({ filters }: { filters: StatusFilter<T>[] }) {
  const [status, setStatus] = useStatusParam(filters)

  // Manual activation: otherwise one click changes the tab twice (mousedown and focus), adding two history entries.
  return (
    <Tabs
      className="mb-4 max-w-full overflow-x-auto"
      activationMode="manual"
      value={status ?? ''}
      onValueChange={setStatus}
    >
      <TabsList aria-label="Status">
        {filters.map((filter) => (
          <TabsTrigger key={filter.value} value={filter.value}>
            {filter.label}
          </TabsTrigger>
        ))}
      </TabsList>
    </Tabs>
  )
}
