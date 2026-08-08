<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { fetchCities } from '../api/catalog';
import { nabrojiGradove } from '../utils/format';
import AppPromo from '../components/AppPromo.vue';

const route = useRoute();

const navItems = [
  { label: 'Proizvod', to: '/proizvod' },
  { label: 'Cijene', to: '/cijene' },
  { label: 'Cjenovnik radova', to: '/cjenovnik' },
  { label: 'Gdje radimo', to: '/gdje-radimo' },
  { label: 'Česta pitanja', to: '/pitanja' },
  { label: 'Kontakt', to: '/kontakt' },
];

const footerColumns = [
  {
    naslov: 'Usluga',
    linkovi: [
      { label: 'Proizvod', to: '/proizvod' },
      { label: 'Cijene', to: '/cijene' },
      { label: 'Cjenovnik radova', to: '/cjenovnik' },
      { label: 'Gdje radimo', to: '/gdje-radimo' },
      { label: 'Česta pitanja', to: '/pitanja' },
    ],
  },
  {
    naslov: 'Pretplata',
    linkovi: [
      { label: 'Registracija', to: '/registracija' },
      { label: 'Prijava', to: '/prijava' },
    ],
  },
  {
    naslov: 'Firma',
    linkovi: [
      { label: 'Kontakt', to: '/kontakt' },
      { label: 'Uslovi korištenja', to: '/uslovi' },
    ],
  },
];

const descriptor = ref('Održavanje doma, na pretplatu.');

onMounted(async () => {
  try {
    const cities = await fetchCities();
    const aktivni = cities.filter((c) => c.status === 'aktivan').map((c) => c.name);
    if (aktivni.length > 0) {
      descriptor.value = `Održavanje doma, na pretplatu. ${nabrojiGradove(aktivni)}.`;
    }
  } catch (e) {
    // Podnožje ostaje sa generičkim opisom ako grad lista ne stigne.
  }
});

function isActive(to) {
  return route.path === to;
}
</script>

<template>
  <div style="min-height:100vh;display:flex;flex-direction:column">
    <header style="border-bottom:1px solid var(--sand);background:var(--white);position:sticky;top:0;z-index:50">
      <div class="container" style="display:flex;align-items:center;gap:48px;height:88px">
        <RouterLink to="/" style="display:block;flex:none">
          <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="176" height="38">
        </RouterLink>
        <nav style="display:flex;gap:2px;flex:1;min-width:0" aria-label="Glavna navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            style="padding:10px 12px;font-size:14px;color:var(--ink);white-space:nowrap;border-bottom:2px solid transparent;text-decoration:none"
            :style="{ fontWeight: isActive(item.to) ? 600 : 400, borderBottomColor: isActive(item.to) ? 'var(--ember)' : 'transparent' }"
          >{{ item.label }}</RouterLink>
        </nav>
        <div style="display:flex;align-items:center;gap:16px;flex:none">
          <RouterLink to="/prijava" class="btn btn-ghost-ink" style="padding:11px 18px;font-size:14px">Prijava</RouterLink>
          <RouterLink to="/registracija" class="btn btn-ember" style="padding:12px 20px;font-size:14px">Pretplati se</RouterLink>
        </div>
      </div>
    </header>

    <main style="flex:1">
      <slot />
    </main>

    <footer style="background:var(--ink);padding:64px 0 32px">
      <div class="container">
        <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:48px;padding-bottom:48px;border-bottom:1px solid var(--bark)">
          <div>
            <RouterLink to="/" style="display:block;margin-bottom:18px">
              <img :src="'/assets/logo-ivory.svg'" alt="HAUS" width="166" height="36">
            </RouterLink>
            <p style="font-size:15px;font-weight:400;color:var(--sand);line-height:1.55;max-width:300px;margin-bottom:24px">{{ descriptor }}</p>
            <h2 style="font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--grey);margin-bottom:16px">Aplikacija</h2>
            <AppPromo variant="footer" />
          </div>
          <div v-for="col in footerColumns" :key="col.naslov">
            <h2 style="font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--grey);margin-bottom:16px">{{ col.naslov }}</h2>
            <ul style="display:flex;flex-direction:column;gap:10px">
              <li v-for="link in col.linkovi" :key="link.to">
                <RouterLink :to="link.to" style="font-size:15px;font-weight:400;color:var(--sand);text-decoration:none">{{ link.label }}</RouterLink>
              </li>
            </ul>
          </div>
        </div>
        <div style="display:flex;align-items:baseline;justify-content:space-between;padding-top:28px;gap:24px;flex-wrap:wrap">
          <p style="font-size:19px;font-weight:600;color:var(--ivory)">Vi živite. HAUS održava.</p>
          <p style="font-size:13px;font-weight:400;color:var(--grey)">
            <span class="num">© 2026</span> HAUS d.o.o. Sarajevo · haus.ba ·
            <RouterLink to="/uslovi" style="color:var(--grey)">Uslovi korištenja</RouterLink>
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
