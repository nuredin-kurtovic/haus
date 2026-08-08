<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { apiGet, ApiError } from '../api/client';
import '../../css/admin.css';

const route = useRoute();
const auth = useAuthStore();

const navItems = [
  { label: 'Pregled', to: '/admin' },
  { label: 'Zahtjevi', to: '/admin/zahtjevi' },
  { label: 'Klijenti', to: '/admin/klijenti' },
  { label: 'Pretplate', to: '/admin/pretplate' },
  { label: 'Naplata', to: '/admin/naplata' },
  { label: 'Cjenovnik', to: '/admin/cjenovnik' },
  { label: 'Gradovi', to: '/admin/gradovi' },
  { label: 'Postavke', to: '/admin/postavke' },
];

function isActive(to) {
  if (to === '/admin') return route.path === '/admin';
  return route.path.startsWith(to);
}

const now = ref(new Date());
let clockTimer = null;

function formatClock(d) {
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yyyy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${dd}.${mm}.${yyyy}. · ${hh}:${mi}`;
}

// /me hidrira ime i ulogu poslije reloada (token perzistira, user/role ne).
onMounted(async () => {
  clockTimer = window.setInterval(() => { now.value = new Date(); }, 1000 * 30);
  try {
    const body = await apiGet('/me');
    if (body.user) auth.user = body.user;
    if (body.role) auth.role = body.role;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      auth.clearSession();
    }
  }
});

onBeforeUnmount(() => {
  if (clockTimer) window.clearInterval(clockTimer);
});
</script>

<template>
  <div style="min-height:100vh;background:var(--white);display:flex;flex-direction:column">
    <header class="admin-header">
      <div class="admin-header-inner">
        <div class="admin-brand">
          <img :src="'/assets/logo-ivory.svg'" alt="HAUS" width="138" height="30">
          <span class="admin-brand-label">Dispečer</span>
        </div>
        <nav class="admin-nav" aria-label="Admin navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="admin-nav-link"
            :class="{ active: isActive(item.to) }"
          >{{ item.label }}</RouterLink>
        </nav>
        <div class="admin-meta">
          <span class="admin-clock num">{{ formatClock(now) }}</span>
          <span class="admin-operator-chip">{{ auth.user?.name || '' }}</span>
          <button type="button" class="btn btn-ghost-ivory" style="padding:9px 14px;font-size:13px" @click="auth.logout()">Odjava</button>
        </div>
      </div>
    </header>

    <p class="admin-mobile-notice">Admin je namijenjen radu na računaru.</p>

    <main style="flex:1">
      <slot />
    </main>
  </div>
</template>
