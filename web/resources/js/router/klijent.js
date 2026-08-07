// Rute klijentske površine. Vlasnik: klijent web UI faza.
// Guard po ulozi je u index.js (meta.role).
const klijentRoutes = [
  { path: '/klijent', name: 'klijent-pocetna', component: () => import('../pages/klijent/Pocetna.vue'), meta: { role: 'klijent' } },
  { path: '/klijent/prijavi-kvar', name: 'klijent-prijavi-kvar', component: () => import('../pages/klijent/PrijaviKvar.vue'), meta: { role: 'klijent' } },
  { path: '/klijent/intervencije', name: 'klijent-intervencije', component: () => import('../pages/klijent/Intervencije.vue'), meta: { role: 'klijent' } },
  { path: '/klijent/pretplata', name: 'klijent-pretplata', component: () => import('../pages/klijent/Pretplata.vue'), meta: { role: 'klijent' } },
  { path: '/klijent/cjenovnik', name: 'klijent-cjenovnik', component: () => import('../pages/klijent/Cjenovnik.vue'), meta: { role: 'klijent' } },
  { path: '/klijent/profil', name: 'klijent-profil', component: () => import('../pages/klijent/Profil.vue'), meta: { role: 'klijent' } },
];

export default klijentRoutes;
