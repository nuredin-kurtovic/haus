<script setup>
import { paketBullets, paketJedinica, paketOpis } from '../utils/format';

const props = defineProps({
  pkg: { type: Object, required: true },
  recommended: { type: Boolean, default: false },
  titleSize: { type: String, default: '24px' },
});
</script>

<template>
  <div
    :style="{
      border: `1px solid ${recommended ? 'var(--ink)' : 'var(--sand)'}`,
      background: recommended ? 'var(--ivory)' : 'var(--white)',
      padding: '32px 28px 28px',
      display: 'flex',
      flexDirection: 'column',
      gap: '22px',
    }"
  >
    <div>
      <h3 :style="{ fontSize: titleSize, fontWeight: 700, letterSpacing: '.04em', marginBottom: '6px' }">{{ pkg.name }}</h3>
      <p style="font-size:14px;font-weight:400;color:var(--bark);line-height:1.5">{{ paketOpis(pkg) }}</p>
    </div>
    <div style="display:flex;align-items:baseline;gap:8px;border-top:1px solid var(--sand);border-bottom:1px solid var(--sand);padding:18px 0">
      <span class="num" style="font-size:46px;font-weight:700;line-height:1">{{ Math.round(pkg.price_year) }}</span>
      <span style="font-size:16px;font-weight:400;color:var(--bark)">KM / {{ paketJedinica(pkg) }}</span>
    </div>
    <ul style="display:flex;flex-direction:column;gap:11px">
      <li v-for="stavka in paketBullets(pkg)" :key="stavka" style="display:grid;grid-template-columns:14px 1fr;gap:12px;font-size:15px;font-weight:400;line-height:1.45">
        <span style="width:7px;height:7px;background:var(--ink);display:block;margin-top:8px"></span>
        <span>{{ stavka }}</span>
      </li>
    </ul>
    <RouterLink
      :to="{ path: '/registracija', query: { paket: pkg.slug } }"
      class="btn"
      :class="recommended ? 'btn-ember' : 'btn-ghost-ink'"
      style="margin-top:auto;padding:15px 20px;font-size:15px"
    >Pretplati se na {{ pkg.name }}</RouterLink>
  </div>
</template>
