import { createRouter, createWebHistory } from 'vue-router';

// Četiri površine: javno, registracija, klijent, admin (dispečer).
// Javne stranice nose PublicLayout unutar svoje komponente. Prijava,
// registracija i klijent/admin placeholderi su bez javnog layouta.
const routes = [
  { path: '/', name: 'naslovna', component: () => import('../pages/public/Naslovna.vue') },
  { path: '/proizvod', name: 'proizvod', component: () => import('../pages/public/Proizvod.vue') },
  { path: '/cijene', name: 'cijene', component: () => import('../pages/public/Cijene.vue') },
  { path: '/cjenovnik', name: 'cjenovnik', component: () => import('../pages/public/Cjenovnik.vue') },
  { path: '/gdje-radimo', name: 'gdje-radimo', component: () => import('../pages/public/GdjeRadimo.vue') },
  { path: '/pitanja', name: 'pitanja', component: () => import('../pages/public/Pitanja.vue') },
  { path: '/kontakt', name: 'kontakt', component: () => import('../pages/public/Kontakt.vue') },
  { path: '/uslovi', name: 'uslovi', component: () => import('../pages/public/Uslovi.vue') },

  { path: '/prijava', name: 'prijava', component: () => import('../pages/auth/Prijava.vue') },
  { path: '/registracija', name: 'registracija', component: () => import('../pages/auth/Registracija.vue') },

  { path: '/klijent', name: 'klijent-pocetna', component: () => import('../pages/klijent/Pocetna.vue') },
  { path: '/admin', name: 'admin-pocetna', component: () => import('../pages/admin/Pocetna.vue') },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.hash) return { el: to.hash, top: 150 };
    return { top: 0 };
  },
});

export default router;
