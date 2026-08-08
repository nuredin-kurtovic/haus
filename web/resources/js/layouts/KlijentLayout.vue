<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { apiGet, ApiError } from '../api/client';

const route = useRoute();
const auth = useAuthStore();

const subscription = ref(null);
const menuOpen = ref(false);

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}
function closeMenu() {
  menuOpen.value = false;
}
watch(() => route.path, closeMenu);

const navItems = [
  { label: 'Početna', to: '/klijent' },
  { label: 'Moje intervencije', to: '/klijent/intervencije' },
  { label: 'Moja pretplata', to: '/klijent/pretplata' },
  { label: 'Cjenovnik', to: '/klijent/cjenovnik' },
  { label: 'Profil', to: '/klijent/profil' },
];

function isActive(to) {
  return route.path === to;
}

// /me hidrira ime i ulogu poslije reloada (token perzistira, user/role ne).
// Isti poziv nosi i sažetak pretplate za desni klaster u zaglavlju.
onMounted(async () => {
  try {
    const body = await apiGet('/me');
    if (body.user) auth.user = body.user;
    if (body.role) auth.role = body.role;
    subscription.value = body.subscription || null;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      auth.clearSession();
    }
  }
});
</script>

<template>
  <div style="min-height:100vh;background:var(--white);display:flex;flex-direction:column">
    <header class="k-header">
      <div class="k-header-inner">
        <RouterLink to="/klijent" style="display:block;flex:none">
          <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="152" height="33" class="k-logo">
        </RouterLink>
        <nav class="nav-desktop k-nav" aria-label="Klijentska navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            style="padding:10px 14px;font-size:14px;color:var(--ink);white-space:nowrap;border-bottom:2px solid transparent;text-decoration:none"
            :style="{ fontWeight: isActive(item.to) ? 600 : 400, borderBottomColor: isActive(item.to) ? 'var(--ember)' : 'transparent' }"
          >{{ item.label }}</RouterLink>
        </nav>
        <div class="k-header-actions">
          <div class="nav-desktop k-user-info" style="text-align:right;line-height:1.25">
            <span style="display:block;font-size:14px;font-weight:600">{{ auth.user?.name || '' }}</span>
            <span style="display:block;font-size:12px;font-weight:400;color:var(--bark)">{{ subscription ? subscription.package.name : 'Bez pretplate' }}</span>
          </div>
          <RouterLink to="/klijent/prijavi-kvar" class="btn btn-ember header-cta" style="padding:13px 20px;font-size:14px">Prijavi kvar</RouterLink>
          <button type="button" class="btn btn-ghost-ink nav-desktop" style="padding:12px 14px;font-size:13px" @click="auth.logout()">Odjava</button>
          <button
            type="button"
            class="nav-toggle"
            aria-controls="klijent-nav-panel"
            :aria-expanded="menuOpen ? 'true' : 'false'"
            @click="toggleMenu"
          >
            <span class="nav-toggle-bars"><span></span><span></span><span></span></span>
            <span class="nav-toggle-label">Meni</span>
          </button>
        </div>
      </div>
      <div id="klijent-nav-panel" class="nav-panel" :class="{ 'is-open': menuOpen }">
        <nav class="nav-panel-list" aria-label="Mobilna klijentska navigacija">
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
          <p style="font-size:14px;font-weight:600;color:var(--ivory)">{{ auth.user?.name || '' }}</p>
          <p style="font-size:13px;font-weight:400;color:var(--sand);margin-top:-8px">{{ subscription ? subscription.package.name : 'Bez pretplate' }}</p>
          <button type="button" class="btn btn-ghost-ivory btn-block" @click="auth.logout()">Odjava</button>
        </div>
      </div>
    </header>

    <main style="flex:1">
      <slot />
    </main>

    <footer style="background:var(--ink);padding:18px 0">
      <div class="k-footer-inner">
        <span style="font-size:13px;font-weight:400;color:var(--grey)">HAUS d.o.o. Sarajevo</span>
        <span class="num" style="font-size:13px;font-weight:400;color:var(--grey)">© 2026</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.k-header {
  border-bottom: 1px solid var(--sand);
  background: var(--white);
  position: sticky;
  top: 0;
  z-index: 50;
}
.k-header-inner {
  width: 1320px;
  max-width: 100%;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 40px;
  height: 80px;
  padding: 0 28px;
}
.k-nav {
  display: flex;
  gap: 2px;
  flex: 1;
  min-width: 0;
}
.k-header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: none;
}
.k-footer-inner {
  width: 1320px;
  max-width: 100%;
  margin: 0 auto;
  padding: 0 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.k-logo {
  width: 152px;
  height: auto;
}
@media (max-width: 900px) {
  .k-header-inner { gap: 20px; height: 72px; padding: 0 20px; }
}
@media (max-width: 480px) {
  .k-header-inner { gap: 10px; padding: 0 16px; }
  .k-header-actions { gap: 8px; }
  .k-logo { width: 116px; }
  .header-cta { padding: 10px 14px !important; font-size: 13px !important; }
}
</style>
