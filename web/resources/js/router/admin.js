// Rute admin (dispečer) površine. Vlasnik: admin web UI faza.
// Guard po ulozi je u index.js (meta.role).
const adminRoutes = [
  { path: '/admin', name: 'admin-pocetna', component: () => import('../pages/admin/Pregled.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/zahtjevi', name: 'admin-zahtjevi', component: () => import('../pages/admin/Zahtjevi.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/klijenti', name: 'admin-klijenti', component: () => import('../pages/admin/Klijenti.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/klijenti/:id', name: 'admin-klijent-detalj', component: () => import('../pages/admin/KlijentDetalj.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/pretplate', name: 'admin-pretplate', component: () => import('../pages/admin/Pretplate.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/naplata', name: 'admin-naplata', component: () => import('../pages/admin/Naplata.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/cjenovnik', name: 'admin-cjenovnik', component: () => import('../pages/admin/Cjenovnik.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/gradovi', name: 'admin-gradovi', component: () => import('../pages/admin/Gradovi.vue'), meta: { role: 'dispecer' } },
  { path: '/admin/postavke', name: 'admin-postavke', component: () => import('../pages/admin/Postavke.vue'), meta: { role: 'dispecer' } },
];

export default adminRoutes;
