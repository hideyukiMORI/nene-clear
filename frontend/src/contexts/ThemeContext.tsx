import { createContext, useContext, useState, useEffect, type ReactNode } from 'react'

type Theme = 'a' | 'b' | 'c'

interface ThemeCtx {
  theme: Theme
  setTheme: (t: Theme) => void
}

const Ctx = createContext<ThemeCtx>({ theme: 'a', setTheme: () => {} })

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(() => {
    try { return (localStorage.getItem('nene_theme') as Theme) || 'a' } catch { return 'a' }
  })

  useEffect(() => {
    document.body.dataset.theme = theme
    localStorage.setItem('nene_theme', theme)
  }, [theme])

  function setTheme(t: Theme) { setThemeState(t) }

  return <Ctx.Provider value={{ theme, setTheme }}>{children}</Ctx.Provider>
}

export const useTheme = () => useContext(Ctx)
