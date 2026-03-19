import { createRouter, createWebHistory } from 'vue-router'

const Listone = () => import('./components/Listone.vue')
const MiaSquadra = () => import('./components/MiaSquadra.vue')
const Classifica = () => import('./components/Classifica.vue')
const Impostazioni = () => import('./components/Impostazioni.vue')
const Admin = () => import('./components/Admin.vue')

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