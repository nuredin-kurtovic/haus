<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { fetchCities } from '../api/catalog';
import { nabrojiGradove } from '../utils/format';
import AppPromo from '../components/AppPromo.vue';

const route = useRoute();
const menuOpen = ref(false);

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}
function closeMenu() {
  menuOpen.value = false;
}
watch(() => route.path, closeMenu);

const navItems = [
  { label: 'Početna', to: '/' },
  { label: 'Proizvod', to: '/proizvod' },
  { label: 'Cjenovnik', to: '/cjenovnik' },
  { label: 'Gdje radimo', to: '/gdje-radimo' },
  { label: 'Česta pitanja', to: '/pitanja' },
  { label: 'Kontakt', to: '/kontakt' },
];

const footerColumns = [
  {
    naslov: 'Usluga',
    linkovi: [
      { label: 'Proizvod', to: '/proizvod' },
      { label: 'Cijene i paketi', to: '/cjenovnik' },
      { label: 'Cjenovnik radova', to: '/cjenovnik#cjenovnik-radova' },
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
    <header class="site-header">
      <div class="container site-header-inner">
        <RouterLink to="/" style="display:block;flex:none">
          <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="176" height="38" class="brand-logo">
        </RouterLink>
        <nav class="nav-desktop" aria-label="Glavna navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            style="padding:10px 12px;font-size:14px;color:var(--ink);white-space:nowrap;border-bottom:2px solid transparent;text-decoration:none"
            :style="{ fontWeight: isActive(item.to) ? 600 : 400, borderBottomColor: isActive(item.to) ? 'var(--ember)' : 'transparent' }"
          >{{ item.label }}</RouterLink>
        </nav>
        <div class="site-header-actions">
          <RouterLink to="/prijava" class="btn btn-ghost-ink nav-desktop" style="padding:11px 18px;font-size:14px">Prijava</RouterLink>
          <RouterLink to="/registracija" class="btn btn-ember header-cta" style="padding:12px 20px;font-size:14px">Pretplati se</RouterLink>
          <button
            type="button"
            class="nav-toggle"
            aria-controls="public-nav-panel"
            :aria-expanded="menuOpen ? 'true' : 'false'"
            @click="toggleMenu"
          >
            <span class="nav-toggle-bars"><span></span><span></span><span></span></span>
            <span class="nav-toggle-label">Meni</span>
          </button>
        </div>
      </div>
      <div id="public-nav-panel" class="nav-panel" :class="{ 'is-open': menuOpen }">
        <nav class="nav-panel-list" aria-label="Mobilna navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="nav-panel-link"
            :class="{ active: isActive(item.to) }"
            @click="closeMenu"
          >{{ item.label }}</RouterLink>
        </nav>
        <div class="nav-panel-extra">
          <RouterLink to="/prijava" class="btn btn-ghost-ivory btn-block" @click="closeMenu">Prijava</RouterLink>
        </div>
      </div>
    </header>

    <main style="flex:1">
      <slot />
    </main>

    <footer style="background:var(--ink);padding:64px 0 32px">
      <div class="container">
        <div class="footer-grid">
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
        <div class="footer-baseline">
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

<style scoped>
.site-header {
  border-bottom: 1px solid var(--sand);
  background: var(--white);
  position: sticky;
  top: 0;
  z-index: 50;
}
.site-header-inner {
  display: flex;
  align-items: center;
  gap: 48px;
  height: 88px;
}
.nav-desktop {
  display: flex;
  gap: 2px;
  flex: 1;
  min-width: 0;
}
.site-header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: none;
}
.footer-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr 1fr;
  gap: 48px;
  padding-bottom: 48px;
  border-bottom: 1px solid var(--bark);
}
.footer-baseline {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding-top: 28px;
  gap: 24px;
  flex-wrap: wrap;
}
@media (max-width: 900px) {
  .site-header-inner { gap: 20px; height: 72px; }
}
@media (max-width: 768px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 40px 32px; }
}
@media (max-width: 480px) {
  .footer-grid { grid-template-columns: 1fr; gap: 36px; }
  .site-header-inner { gap: 12px; }
  .site-header-actions { gap: 10px; }
  .header-cta { padding: 10px 14px !important; font-size: 13px !important; }
}
</style>
