export default [
  {
    path: '/admin/users',
    name: 'admin-users',
    component: () => import('@/views/admin/Users.vue'),
    meta: {
      resource: 'Users',
      action: 'manage',
    },
  },
]
