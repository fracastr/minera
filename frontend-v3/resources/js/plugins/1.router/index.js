import { setupLayouts } from 'virtual:meta-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupGuards } from './guards'

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])
    
    return route
  }
  
  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory('/'),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),

    // Paths of the retired Vue 2 app that people may have bookmarked.
    { path: '/dashboard/balances', redirect: '/balances/Listado' },
    { path: '/balances/Balances', redirect: '/balances/Listado' },
    { path: '/pages/miscellaneous/not-authorized', redirect: '/not-authorized' },
    { path: '/register', redirect: '/login' },
  ],
})

setupGuards(router)

export { router }
export default function (app) {
  app.use(router)
}
