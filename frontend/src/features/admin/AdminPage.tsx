import { useSearchParams } from 'react-router-dom'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PAGE_TITLE } from '@/lib/styles'
import { AdminEvents } from './AdminEvents'
import { AdminUsers } from './AdminUsers'

type View = 'users' | 'events'

export function AdminPage() {
  const [params, setParams] = useSearchParams()
  const view: View = params.get('view') === 'events' ? 'events' : 'users'

  function changeView(next: string) {
    // Filters and the page belong to one view, so switching starts with a clean URL.
    setParams(next === 'events' ? { view: 'events' } : {})
  }

  return (
    <section>
      <h1 className={`${PAGE_TITLE} mb-6`}>Panel administratora</h1>

      <Tabs className="mb-4 max-w-full overflow-x-auto" value={view} onValueChange={changeView}>
        <TabsList aria-label="Widok">
          <TabsTrigger value="users">Użytkownicy</TabsTrigger>
          <TabsTrigger value="events">Wydarzenia</TabsTrigger>
        </TabsList>
      </Tabs>

      {view === 'users' ? (
        <AdminUsers params={params} onParamsChange={setParams} />
      ) : (
        <AdminEvents params={params} onParamsChange={setParams} />
      )}
    </section>
  )
}
