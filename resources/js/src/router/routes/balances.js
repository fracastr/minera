export default [
    {
      path: '/balances/Balances',
      name: 'balances-Balances',
      component: () => import('@/views/balances/Balances.vue'),
      meta: {
        resource: 'Balances',
        action: 'read',
      },
    },
    {
        path: '/balances/BalanceFormWizard',
        name: 'balances-BalanceFormWizard',
        component: () => import('@/views/balances/BalanceFormWizard.vue'),
        meta: {
          resource: 'Balances',
          action: 'create',
        },
    },
    {
        path: '/balances/Listado',
        name: 'balances-Listado',
        component: () => import('@/views/balances/Listado.vue'),
        meta: {
          resource: 'Balances',
          action: 'read',
        },
    },
  ]
