/** Event the sidebar button dispatches to open the `?` cheat-sheet overlay. */
export const SHOW_SHORTCUTS_EVENT = 'kbd:show-shortcuts'

/** Opens the cheat-sheet from outside the dispatcher (e.g. a sidebar button). */
export function openShortcutsOverlay(): void {
  document.dispatchEvent(new CustomEvent(SHOW_SHORTCUTS_EVENT))
}
