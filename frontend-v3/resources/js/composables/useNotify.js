// App-wide toasts (replaces vue-toastification from the Vue 2 app). Rendered by AppNotify in App.vue.
const notifications = ref([])
let nextId = 1

export const useNotify = () => {
  const notify = (text, { color = 'success', title = '', timeout = 4000 } = {}) => {
    notifications.value.push({ id: nextId++, text, color, title, timeout, show: true })
  }

  return {
    notifications,
    notify,
    success: (text, opts) => notify(text, { ...opts, color: 'success' }),
    error: (text, opts) => notify(text, { timeout: 7000, ...opts, color: 'error' }),
    warning: (text, opts) => notify(text, { ...opts, color: 'warning' }),
    info: (text, opts) => notify(text, { ...opts, color: 'info' }),
  }
}
