import { createRouter, createWebHistory } from 'vue-router';

// Četiri površine: javno, registracija, klijent, admin (dispečer).
// Placeholder rute; svaka faza dodaje svoje. Guard po ulozi ide iz auth store-a.
const routes = [
  { path: '/', name: 'naslovna', component: () => import('../pages/public/Naslovna.vue') },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
