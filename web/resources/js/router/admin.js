// Rute admin (dispečer) površine. Vlasnik: admin web UI faza.
// Guard po ulozi je u index.js (meta.role).
const adminRoutes = [
  { path: '/admin', name: 'admin-pocetna', component: () => import('../pages/admin/Pocetna.vue'), meta: { role: 'dispecer' } },
];

export default adminRoutes;
