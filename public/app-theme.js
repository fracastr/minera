// Applies the saved theme (or the system one) before first paint, so dark mode never flashes light.
// External file, not inline: the CSP only allows script-src 'self'. Same rule as web/src/lib/theme.ts.
try {
  var mode = localStorage.getItem('cmp.theme')
  if (mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches))
    document.documentElement.classList.add('dark')
}
catch (e) {}
