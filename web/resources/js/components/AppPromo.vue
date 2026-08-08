<script setup>
// Promocija mobilne aplikacije, jedna komponenta za sve plasmane.
// Aplikacije još nisu objavljene: appLinks.js drži prazne URL-ove, pa dugmad
// ovdje same prelaze sa toasta na prave linkove čim se store URL upiše.
import { computed } from 'vue';
import { APP_STORE_URL, PLAY_STORE_URL, hasStoreLinks } from '../config/appLinks';
import { useToastStore } from '../stores/toast';

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
  { key: 'ios', big: 'iPhone', url: APP_STORE_URL },
  { key: 'android', big: 'Android', url: PLAY_STORE_URL },
]);

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
            class="app-badge"
            :class="onDark ? 'app-badge-ivory' : 'app-badge-ink'"
            :aria-label="b.url ? `Preuzmite HAUS za ${b.big}` : `HAUS za ${b.big} još nije u prodavnici aplikacija`"
            @click="onBadgeClick(b.url)"
          >
            <span class="app-badge-small">Preuzmite za</span>
            <span class="app-badge-big">{{ b.big }}</span>
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
        class="app-badge app-badge-mini"
        :class="onDark ? 'app-badge-ivory' : 'app-badge-ink'"
        :aria-label="b.url ? `Preuzmite HAUS za ${b.big}` : `HAUS za ${b.big} još nije u prodavnici aplikacija`"
        @click="onBadgeClick(b.url)"
      >
        <span class="app-badge-small">Za</span>
        <span class="app-badge-big">{{ b.big }}</span>
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
        class="app-badge"
        :class="onDark ? 'app-badge-ivory' : 'app-badge-ink'"
        :aria-label="b.url ? `Preuzmite HAUS za ${b.big}` : `HAUS za ${b.big} još nije u prodavnici aplikacija`"
        @click="onBadgeClick(b.url)"
      >
        <span class="app-badge-small">Preuzmite za</span>
        <span class="app-badge-big">{{ b.big }}</span>
      </component>
    </div>
  </div>
</template>

<style scoped>
.app-promo-band {
  background: var(--ink);
  padding: 96px 0;
}
.app-badge {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 2px;
  border: 0;
  padding: 10px 18px;
  min-width: 132px;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  transition: background .2s ease, color .2s ease, opacity .2s ease;
}
.app-badge-mini {
  padding: 8px 14px;
  min-width: 104px;
}
.app-badge-ink {
  background: var(--ink);
  color: var(--ivory);
}
.app-badge-ink:hover {
  background: var(--bark);
}
.app-badge-ivory {
  background: var(--ivory);
  color: var(--ink);
}
.app-badge-ivory:hover {
  background: var(--sand);
}
.app-badge-small {
  font-size: 11px;
  font-weight: 400;
  line-height: 1;
  opacity: .85;
}
.app-badge-mini .app-badge-small {
  font-size: 11px;
}
.app-badge-big {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: .01em;
}
.app-badge-mini .app-badge-big {
  font-size: 14px;
}
</style>
