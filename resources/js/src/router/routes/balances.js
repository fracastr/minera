export default [
    {
      path: '/balances/Balances',
      name: 'balances-Balances',
      component: () => import('@/views/balances/Balances.vue'),
    },
    {
        path: '/balances/FormWizardNumber',
        name: 'balances-FormWizardNumber',
        component: () => import('@/views/balances/FormWizardNumber.vue'),
      },
  ]
