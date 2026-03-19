import { createRouter, createWebHistory } from 'vue-router'

const Listone = () => import('./components/listGlobal.vue')
const MiaSquadra = () => import('./components/mySquad.vue')
const Classifica = () => import('./components/highlightsGlobal.vue')
const Impostazioni = () => import('./components/settingGlobal.vue')
const Admin = () => import('./components/adminDashboard.vue')

const routes = [
  { path: '/', redirect: '/listone' },
  { path: '/listone', component: Listone },
  { path: '/squadra', component: MiaSquadra },
  { path: '/classifica', component: Classifica },
  { path: '/impostazioni', component: Impostazioni },
  { path: '/admin-segreto', component: Admin },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})