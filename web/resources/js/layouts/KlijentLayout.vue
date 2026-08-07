<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { apiGet, ApiError } from '../api/client';

const route = useRoute();
const auth = useAuthStore();

const subscription = ref(null);

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
    <header style="border-bottom:1px solid var(--sand);background:var(--white);position:sticky;top:0;z-index:50">
      <div style="width:1320px;max-width:100%;margin:0 auto;display:flex;align-items:center;gap:40px;height:80px;padding:0 28px">
        <RouterLink to="/klijent" style="display:block;flex:none">
          <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="152" height="33">
        </RouterLink>
        <nav style="display:flex;gap:2px;flex:1;min-width:0" aria-label="Klijentska navigacija">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            style="padding:10px 14px;font-size:14px;color:var(--ink);white-space:nowrap;border-bottom:2px solid transparent;text-decoration:none"
            :style="{ fontWeight: isActive(item.to) ? 600 : 400, borderBottomColor: isActive(item.to) ? 'var(--ember)' : 'transparent' }"
          >{{ item.label }}</RouterLink>
        </nav>
        <div style="display:flex;align-items:center;gap:16px;flex:none">
          <div style="text-align:right;line-height:1.25">
            <span style="display:block;font-size:14px;font-weight:600">{{ auth.user?.name || '' }}</span>
            <span style="display:block;font-size:12px;font-weight:400;color:var(--bark)">{{ subscription ? subscription.package.name : 'Bez pretplate' }}</span>
          </div>
          <RouterLink to="/klijent/prijavi-kvar" class="btn btn-ember" style="padding:13px 20px;font-size:14px">Prijavi kvar</RouterLink>
          <button type="button" class="btn btn-ghost-ink" style="padding:12px 14px;font-size:13px" @click="auth.logout()">Odjava</button>
        </div>
      </div>
    </header>

    <main style="flex:1">
      <slot />
    </main>

    <footer style="background:var(--ink);padding:18px 0">
      <div style="width:1320px;max-width:100%;margin:0 auto;padding:0 28px;display:flex;align-items:center;justify-content:space-between;gap:16px">
        <span style="font-size:13px;font-weight:400;color:var(--grey)">HAUS d.o.o. Sarajevo</span>
        <span class="num" style="font-size:13px;font-weight:400;color:var(--grey)">© 2026</span>
      </div>
    </footer>
  </div>
</template>
