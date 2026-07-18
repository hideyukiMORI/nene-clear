import { api } from '@/shared/api/client'

/**
 * Streams a server CSV export to a browser download. Goes through the transport
 * (`api.getBlob`) so the Authorization + X-Authorization mirror is applied
 * (#265, #312); callers pass the export path and the target filename.
 */
export async function downloadCsv(path: string, filename: string): Promise<void> {
  const blob = await api.getBlob(path)
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
