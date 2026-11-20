export default [
  {
    path: '/dashboard/analytics',
    name: 'dashboard-analytics',
    component: () => import('@/views/dashboard/analytics/Analytics.vue'),
  },
  {
    path: '/dashboard/balance-analytics',
    name: 'dashboard-balance-analytics',
    component: () => import('@/views/dashboard/balance-analytics/BalanceAnalyticsDashboard.vue'),
    meta: {
      resource: 'DashboardAnalytics',
      action: 'read',
    },
  },
  {
    path: '/dashboard/balances',
    name: 'dashboard-balances',
    component: () => import('@/views/dashboard/ecommerce/Dashboard.vue'),
    meta: {
      resource: 'Balances',
      action: 'read',
    },
  },
]
