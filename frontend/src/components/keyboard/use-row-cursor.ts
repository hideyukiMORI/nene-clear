import { useEffect, useRef, useState } from 'react'

export type RowCursorAction = 'down' | 'up' | 'open'

/** Custom event the dispatcher emits for j / k / o / Enter. */
export const KBD_LIST_EVENT = 'kbd:list'

/**
 * List row cursor (layer 4). A list opts in with its row count and an open
 * handler; the global dispatcher emits `kbd:list`, and the cursored row gets
 * `.is-cursor` (the caller renders it). Returns the active index, or -1 for none.
 */
export function useRowCursor(count: number, onOpen: (index: number) => void): number {
  const [cursor, setCursor] = useState(-1)
  const cursorRef = useRef(-1)
  const onOpenRef = useRef(onOpen)

  useEffect(() => {
    cursorRef.current = cursor
  }, [cursor])
  useEffect(() => {
    onOpenRef.current = onOpen
  }, [onOpen])

  // Keep the cursor in range when the row set shrinks (e.g. after filtering).
  if (cursor >= count) setCursor(count - 1)

  useEffect(() => {
    const handler = (event: Event): void => {
      const action = (event as CustomEvent<{ action: RowCursorAction }>).detail.action
      if (action === 'down') setCursor((c) => Math.min(count - 1, c + 1))
      else if (action === 'up') setCursor((c) => (c <= 0 ? 0 : c - 1))
      else if (cursorRef.current >= 0) onOpenRef.current(cursorRef.current)
    }
    document.addEventListener(KBD_LIST_EVENT, handler)
    return () => {
      document.removeEventListener(KBD_LIST_EVENT, handler)
    }
  }, [count])

  // Keep the cursored row visible (guard scrollIntoView for jsdom).
  useEffect(() => {
    if (cursor < 0) return
    const el = document.querySelector(`[data-kbd-row="${String(cursor)}"]`)
    if (el instanceof HTMLElement && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'nearest' })
    }
  }, [cursor])

  return cursor
}
