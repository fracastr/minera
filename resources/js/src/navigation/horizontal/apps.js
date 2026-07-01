export default [
  {
    header: 'Modulos',
    icon: 'PackageIcon',
    children: [
      {
        title: 'Modulo Balances',
        icon: 'SlidersIcon',
        children: [
          {
            title: 'Generar Balance',
            route: 'balances-BalanceFormWizard',
            icon: 'CheckSquareIcon',
            resource: 'Balances',
            action: 'create',
          },
          {
            title: 'Listado Balances',
            route: 'balances-Listado',
            icon: 'PieChartIcon',
            resource: 'Balances',
            action: 'read',
          },
        ],
      },
      {
        title: 'Administracion',
        icon: 'SettingsIcon',
        children: [
          {
            title: 'Usuarios',
            route: 'admin-users',
            icon: 'UsersIcon',
            resource: 'Users',
            action: 'manage',
          },
        ],
      },
    ],
  },
]
