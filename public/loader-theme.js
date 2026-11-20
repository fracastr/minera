// Matches the splash screen to the user's saved skin before anything paints.
// External file (not inline) because the CSP only allows script-src 'self'.
try {
  if (localStorage.getItem('vuexy-skin') === 'dark')
    document.documentElement.classList.add('loader-dark')
}
catch (e) {}
