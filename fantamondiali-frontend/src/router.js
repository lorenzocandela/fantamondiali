import { createRouter, createWebHistory } from 'vue-router'

const ListGlobal   = () => import('./components/listGlobal.vue')
const MySquad      = () => import('./components/mySquad.vue')
const Classifica   = () => import('./components/highlightsGlobal.vue')
const Calendario   = () => import('./components/highlightsGlobal.vue')
const Impostazioni = () => import('./components/settingGlobal.vue')
const Admin        = () => import('./components/adminDashboard.vue')

const routes = [
  { path: '/',              redirect: '/listone' },
  { path: '/listone',      component: ListGlobal },
  { path: '/squadra',      component: MySquad },
  { path: '/classifica',   component: Classifica },
  { path: '/calendario',   component: Calendario },
  { path: '/impostazioni', component: Impostazioni },
  { path: '/admin-segreto', component: Admin },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})