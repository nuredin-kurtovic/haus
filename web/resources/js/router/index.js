import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import klijentRoutes from './klijent';
import adminRoutes from './admin';

// Četiri površine: javno, registracija, klijent, admin (dispečer).
// Klijent i admin rute žive u svojim modulima (klijent.js, admin.js);
// ovaj fajl se ne dira pri dodavanju ekrana tim površinama.
const routes = [
  { path: '/', name: 'naslovna', component: () => import('../pages/public/Naslovna.vue') },
  { path: '/proizvod', name: 'proizvod', component: () => import('../pages/public/Proizvod.vue') },
  { path: '/cijene', name: 'cijene', component: () => import('../pages/public/Cijene.vue') },
  // Cjenovnik radova je sada podsekcija /cijene (sidro #cjenovnik-radova). Redirekt
  // čuva stare linkove i bookmarke; ruta ne drži svoju komponentu.
  { path: '/cjenovnik', redirect: { path: '/cijene', hash: '#cjenovnik-radova' } },
  { path: '/gdje-radimo', name: 'gdje-radimo', component: () => import('../pages/public/GdjeRadimo.vue') },
  { path: '/pitanja', name: 'pitanja', component: () => import('../pages/public/Pitanja.vue') },
  { path: '/kontakt', name: 'kontakt', component: () => import('../pages/public/Kontakt.vue') },
  { path: '/uslovi', name: 'uslovi', component: () => import('../pages/public/Uslovi.vue') },

  { path: '/prijava', name: 'prijava', component: () => import('../pages/auth/Prijava.vue') },
  { path: '/registracija', name: 'registracija', component: () => import('../pages/auth/Registracija.vue') },
  { path: '/placanje/simulacija', name: 'placanje-simulacija', component: () => import('../pages/placanje/Simulacija.vue') },

  ...klijentRoutes,
  ...adminRoutes,
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

// Guard po ulozi: rute sa meta.role traže prijavu i tačnu ulogu.
router.beforeEach((to) => {
  if (!to.meta.role) return true;
  const auth = useAuthStore();
  if (!auth.token) return { name: 'prijava', query: { nazad: to.fullPath } };
  if (auth.role && auth.role !== to.meta.role) {
    return auth.role === 'dispecer' ? { name: 'admin-pocetna' } : { name: 'klijent-pocetna' };
  }
  return true;
});

export default router;
