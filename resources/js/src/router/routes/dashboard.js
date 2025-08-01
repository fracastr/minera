export default [
  {
    path: '/dashboard/analytics',
    name: 'dashboard-analytics',
    component: () => import('@/views/dashboard/analytics/Analytics.vue'),
  },
  {
    path: '/dashboard/balances',
    name: 'dashboard-balances',
    component: () => import('@/views/dashboard/ecommerce/Dashboard.vue'),
  },
]
