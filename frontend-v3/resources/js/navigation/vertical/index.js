// Same menu and permissions as the Vue 2 app (resources/js/src/navigation/vertical/apps-and-pages.js).
export default [
  { heading: 'Menú de aplicaciones' },
  {
    title: 'Módulo Balances',
    icon: { icon: 'tabler-adjustments-horizontal' },
    children: [
      { title: 'Generar Balance', to: { path: '/balances/BalanceFormWizard' }, action: 'create', subject: 'Balances' },
      { title: 'Listado Balances', to: { path: '/balances/Listado' }, action: 'read', subject: 'Balances' },
      { title: 'Dashboard Analytics', to: { path: '/dashboard/balance-analytics' }, action: 'read', subject: 'DashboardAnalytics' },
    ],
  },
  {
    title: 'Administración',
    icon: { icon: 'tabler-settings' },
    children: [
      { title: 'Usuarios', to: { path: '/admin/users' }, action: 'manage', subject: 'Users' },
    ],
  },
]
