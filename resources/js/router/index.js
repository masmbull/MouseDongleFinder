import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { title: 'Dashboard' }
  },
  {
    path: '/mice',
    name: 'MouseDatabase',
    component: () => import('@/views/MouseDatabase.vue'),
    meta: { title: 'Mouse Database' }
  },
  {
    path: '/mice/:id',
    name: 'MouseDetail',
    component: () => import('@/views/MouseDetail.vue'),
    meta: { title: 'Mouse Detail' }
  },
  {
    path: '/dongles',
    name: 'DongleDatabase',
    component: () => import('@/views/DongleDatabase.vue'),
    meta: { title: 'Dongle Database' }
  },
  {
    path: '/dongles/:id',
    name: 'DongleDetail',
    component: () => import('@/views/DongleDetail.vue'),
    meta: { title: 'Dongle Detail' }
  },
  {
    path: '/matching',
    name: 'MatchingCenter',
    component: () => import('@/views/MatchingCenter.vue'),
    meta: { title: 'Matching Center' }
  },
  {
    path: '/matches',
    name: 'MatchList',
    component: () => import('@/views/MatchList.vue'),
    meta: { title: 'Matching List' }
  },
  {
    path: '/usb-detective',
    name: 'UsbDetective',
    component: () => import('@/views/UsbDetective.vue'),
    meta: { title: 'USB Detective' }
  },
  {
    path: '/dongle-lab',
    name: 'DongleLab',
    component: () => import('@/views/DongleLab.vue'),
    meta: { title: 'Dongle Lab' }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound.vue'),
    meta: { title: 'Page Not Found' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  }
})

export default router