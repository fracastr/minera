import { ref, watch } from 'vue'

export type ThemeMode = 'system' | 'light' | 'dark'

// public/app-theme.js applies the same rule before first paint; keep both in sync.
const KEY = 'cmp.theme'
const media = window.matchMedia('(prefers-color-scheme: dark)')

function read(): ThemeMode {
  try {
    const saved = localStorage.getItem(KEY)
    return saved === 'light' || saved === 'dark' ? saved : 'system'
  } catch {
    return 'system'
  }
}

export const themeMode = ref<ThemeMode>(read())

function apply() {
  const dark = themeMode.value === 'dark' || (themeMode.value === 'system' && media.matches)
  document.documentElement.classList.toggle('dark', dark)
}

watch(themeMode, mode => {
  try {
    if (mode === 'system') localStorage.removeItem(KEY)
    else localStorage.setItem(KEY, mode)
  } catch {}
  apply()
})
media.addEventListener('change', apply)
apply()
