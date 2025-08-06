export default [
    {
      path: '/balances/Balances',
      name: 'balances-Balances',
      component: () => import('@/views/balances/Balances.vue'),
    },
    {
        path: '/balances/BalanceFormWizard',
        name: 'balances-BalanceFormWizard',
        component: () => import('@/views/balances/BalanceFormWizard.vue'),
    },
    {
        path: '/balances/Listado',
        name: 'balances-Listado',
        component: () => import('@/views/balances/Listado.vue'),
    },
  ]
