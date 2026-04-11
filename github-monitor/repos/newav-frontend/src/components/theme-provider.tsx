import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"

type Theme = "dark" | "light" | "system" | "auto"
type ResolvedTheme = "dark" | "light"

type ThemeProviderProps = {
  children: ReactNode
  defaultTheme?: Theme
  storageKey?: string
}

type ThemeProviderState = {
  theme: Theme
  resolvedTheme: ResolvedTheme
  setTheme: (theme: Theme) => void
}

const initialState: ThemeProviderState = {
  theme: "auto",
  resolvedTheme: "light",
  setTheme: () => null,
}

const ThemeProviderContext = createContext<ThemeProviderState>(initialState)

const NIGHT_START_MINUTES = 19 * 60 // 7:00 PM
const DAY_START_MINUTES = 7 * 60 // 7:00 AM

const getTimeBasedTheme = (referenceDate = new Date()): ResolvedTheme => {
  const minutes = referenceDate.getHours() * 60 + referenceDate.getMinutes()
  const isNight =
    minutes >= NIGHT_START_MINUTES || minutes < DAY_START_MINUTES
  return isNight ? "dark" : "light"
}

const getMsUntilNextBoundary = (referenceDate = new Date()): number => {
  const now = referenceDate
  const minutes = now.getHours() * 60 + now.getMinutes()
  const boundary = new Date(now)
  boundary.setSeconds(0, 0)

  if (minutes >= NIGHT_START_MINUTES) {
    boundary.setDate(boundary.getDate() + 1)
    boundary.setHours(7, 0, 0, 0)
  } else if (minutes < DAY_START_MINUTES) {
    boundary.setHours(7, 0, 0, 0)
  } else {
    boundary.setHours(19, 0, 0, 0)
  }

  const delta = boundary.getTime() - now.getTime()
  // Fallback to a minute to avoid zero/negative delays due to timing quirks
  return delta > 0 ? delta : 60 * 1000
}

const getResolvedFromTheme = (
  themeValue: Theme,
  fallback: ResolvedTheme,
): ResolvedTheme => {
  if (themeValue === "dark" || themeValue === "light") {
    return themeValue
  }

  if (themeValue === "auto") {
    return getTimeBasedTheme()
  }

  if (typeof window !== "undefined") {
    const prefersDark = window.matchMedia(
      "(prefers-color-scheme: dark)",
    ).matches
    return prefersDark ? "dark" : "light"
  }

  return fallback
}

export function ThemeProvider({
  children,
  defaultTheme = "auto",
  storageKey = "vite-ui-theme",
  ...props
}: ThemeProviderProps) {
  const getInitialTheme = (): Theme => {
    if (typeof window === "undefined") return defaultTheme

    const storedTheme = window.localStorage.getItem(storageKey) as Theme | null
    if (
      storedTheme &&
      ["light", "dark", "system", "auto"].includes(storedTheme)
    ) {
      return storedTheme
    }

    return defaultTheme
  }

  const [theme, setThemeState] = useState<Theme>(getInitialTheme)
  const fallbackResolved: ResolvedTheme =
    defaultTheme === "dark" ? "dark" : "light"
  const [resolvedTheme, setResolvedTheme] = useState<ResolvedTheme>(() =>
    getResolvedFromTheme(theme, fallbackResolved),
  )

  useEffect(() => {
    if (typeof window === "undefined") return

    const root = window.document.documentElement
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)")
    let autoTimeout: number | undefined

    const applyThemeClass = (themeValue: Theme, fallback: ResolvedTheme) => {
      const resolved = getResolvedFromTheme(themeValue, fallback)

      root.classList.remove("light", "dark")
      root.classList.add(resolved)
      root.style.colorScheme = resolved
      setResolvedTheme(resolved)
      return resolved
    }

    const cleanupFns: Array<() => void> = []
    applyThemeClass(theme, fallbackResolved)

    if (theme === "system") {
      const listener = (event: MediaQueryListEvent) => {
        root.classList.remove("light", "dark")
        const resolved = event.matches ? "dark" : "light"
        root.classList.add(resolved)
        root.style.colorScheme = resolved
        setResolvedTheme(resolved)
      }

      mediaQuery.addEventListener("change", listener)
      cleanupFns.push(() =>
        mediaQuery.removeEventListener("change", listener),
      )
    }

    if (theme === "auto") {
      const scheduleNext = () => {
        autoTimeout = window.setTimeout(() => {
          applyThemeClass("auto", fallbackResolved)
          scheduleNext()
        }, getMsUntilNextBoundary())
      }

      scheduleNext()

      cleanupFns.push(() => {
        if (autoTimeout) {
          window.clearTimeout(autoTimeout)
        }
      })
    }

    return () => {
      cleanupFns.forEach((cleanup) => cleanup())
    }
  }, [theme])

  const setTheme = (themeValue: Theme) => {
    setThemeState(themeValue)
    if (typeof window !== "undefined") {
      window.localStorage.setItem(storageKey, themeValue)
    }
  }

  const value = useMemo(
    () => ({
      theme,
      resolvedTheme,
      setTheme,
    }),
    [theme, resolvedTheme],
  )

  return (
    <ThemeProviderContext.Provider {...props} value={value}>
      {children}
    </ThemeProviderContext.Provider>
  )
}

export const useTheme = () => {
  const context = useContext(ThemeProviderContext)

  if (context === undefined)
    throw new Error("useTheme must be used within a ThemeProvider")

  return context
}
