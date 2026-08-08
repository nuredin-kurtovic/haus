<script setup>
// Promocija mobilne aplikacije, jedna komponenta za sve plasmane.
// Aplikacije još nisu objavljene: appLinks.js drži prazne URL-ove, pa badge-ovi
// ovdje sami prelaze sa toasta na prave linkove čim se store URL upiše.
import { computed } from 'vue';
import { APP_STORE_URL, PLAY_STORE_URL, hasStoreLinks } from '../config/appLinks';
import { useToastStore } from '../stores/toast';
import StoreBadge from './StoreBadge.vue';

const props = defineProps({
  // 'band' puna sekcija (Naslovna), 'inline' kompaktan red (potvrde), 'footer' mini dugmad.
  variant: { type: String, default: 'inline' },
  // Kad je null, izvodi se iz varijante: band i footer po defaultu sjede na ink pozadini.
  dark: { type: Boolean, default: null },
  title: { type: String, default: 'Prijava kvara je u aplikaciji.' },
  description: {
    type: String,
    default: 'Prijavite kvar u tri koraka. Termin i obavještenja stižu na telefon. Garancije i historija stoje u HAUS Kartonu.',
  },
  message: { type: String, default: 'Za iPhone i Android.' },
});

const toast = useToastStore();
const storesLive = hasStoreLinks();

const onDark = computed(() => (props.dark === null ? props.variant !== 'inline' : props.dark));

const badges = computed(() => [
  { key: 'apple', label: 'App Store', url: APP_STORE_URL },
  { key: 'google', label: 'Google Play', url: PLAY_STORE_URL },
]);

const badgeHeight = computed(() => (props.variant === 'footer' ? 40 : 52));

function onBadgeClick(url) {
  if (url) return;
  toast.show('Aplikacija stiže u App Store i Google Play. Vaš nalog sa weba vrijedi i u aplikaciji.');
}
</script>

<template>
  <section v-if="variant === 'band'" class="app-promo-band">
    <div class="container" style="display:grid;grid-template-columns:1fr 420px;gap:64px;align-items:center">
      <div>
        <div style="display:inline-block;background:var(--ember);padding:2px;margin-bottom:24px">
          <span style="display:block;background:var(--ivory);color:var(--ink);padding:6px 14px;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase">HAUS aplikacija</span>
        </div>
        <h2 class="section-h2" style="color:var(--ivory);margin-bottom:16px">{{ title }}</h2>
        <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--sand);max-width:520px">{{ description }}</p>
      </div>
      <div style="display:flex;flex-direction:column;gap:16px">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <component
            :is="b.url ? 'a' : 'button'"
            v-for="b in badges"
            :key="b.key"
            v-bind="b.url ? { href: b.url, target: '_blank', rel: 'noopener' } : { type: 'button' }"
            class="store-badge-wrap"
            :aria-label="b.url ? `Preuzmite HAUS: ${b.label}` : `HAUS još nije objavljen: ${b.label}`"
            @click="onBadgeClick(b.url)"
          >
            <StoreBadge :store="b.key" :height="badgeHeight" />
          </component>
        </div>
        <p v-if="message" style="font-size:14px;font-weight:400;color:var(--sand)">{{ message }}</p>
        <span v-if="!storesLive" class="visually-hidden">Klik na dugme otvara obavještenje o objavljivanju aplikacije.</span>
      </div>
    </div>
  </section>

  <div v-else-if="variant === 'footer'" style="display:flex;flex-direction:column;gap:14px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <component
        :is="b.url ? 'a' : 'button'"
        v-for="b in badges"
        :key="b.key"
        v-bind="b.url ? { href: b.url, target: '_blank', rel: 'noopener' } : { type: 'button' }"
        class="store-badge-wrap"
        :aria-label="b.url ? `Preuzmite HAUS: ${b.label}` : `HAUS još nije objavljen: ${b.label}`"
        @click="onBadgeClick(b.url)"
      >
        <StoreBadge :store="b.key" :height="badgeHeight" />
      </component>
    </div>
  </div>

  <div
    v-else
    style="display:flex;align-items:center;gap:20px;flex-wrap:wrap"
    :style="onDark ? {} : { background: 'var(--white)', border: '1px solid var(--sand)', padding: '20px 22px' }"
  >
    <p style="font-size:15px;font-weight:400;line-height:1.5;flex:1;min-width:220px" :style="{ color: onDark ? 'var(--sand)' : 'var(--bark)' }">{{ message }}</p>
    <div style="display:flex;gap:10px;flex:none;flex-wrap:wrap">
      <component
        :is="b.url ? 'a' : 'button'"
        v-for="b in badges"
        :key="b.key"
        v-bind="b.url ? { href: b.url, target: '_blank', rel: 'noopener' } : { type: 'button' }"
        class="store-badge-wrap"
        :aria-label="b.url ? `Preuzmite HAUS: ${b.label}` : `HAUS još nije objavljen: ${b.label}`"
        @click="onBadgeClick(b.url)"
      >
        <StoreBadge :store="b.key" :height="badgeHeight" />
      </component>
    </div>
  </div>
</template>

<style scoped>
.app-promo-band {
  background: var(--ink);
  padding: 96px 0;
}
.store-badge-wrap {
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  line-height: 0;
  transition: opacity .2s ease;
}
.store-badge-wrap:hover {
  opacity: .8;
}
</style>
