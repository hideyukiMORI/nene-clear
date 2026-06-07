import { useQuery } from '@tanstack/react-query'
import { getCurrentUser } from '@/api/endpoints'
import { fiscalYearStart, todayIso } from '@/utils/fiscal'

/**
 * Resolves the org's current fiscal year as a default date range for list
 * filters. Reads `fiscal_year_end_month` from a cached `/me` query (shared
 * across pages). Returns `range = null` when the month is unset or `/me` fails,
 * so callers keep their "no default → all" behaviour. `settled` flips once the
 * query resolves (success or error), so callers apply the default exactly once.
 */
export function useFiscalYearDefault(): { range: { fromIso: string; toIso: string } | null; settled: boolean } {
  const meQ = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: ({ signal }) => getCurrentUser(signal),
    staleTime: 5 * 60_000,
    retry: false,
  })

  const month = meQ.data?.fiscal_year_end_month ?? null
  const range = meQ.isSuccess && month != null
    ? { fromIso: fiscalYearStart(month), toIso: todayIso() }
    : null

  return { range, settled: meQ.isSuccess || meQ.isError }
}
