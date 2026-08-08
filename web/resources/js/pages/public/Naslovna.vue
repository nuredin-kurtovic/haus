<script setup>
import { onMounted, ref, computed } from 'vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import PackageCard from '../../components/PackageCard.vue';
import AppPromo from '../../components/AppPromo.vue';
import { fetchPackages, fetchCities } from '../../api/catalog';
import { nabrojiGradove } from '../../utils/format';

const packages = ref([]);
const cities = ref([]);

const aktivniGradovi = computed(() => cities.value.filter((c) => c.status === 'aktivan').map((c) => c.name));
const najnizaCijena = computed(() => (packages.value.length ? Math.min(...packages.value.map((p) => p.price_year)) : null));

const heroLinija = computed(() => {
  if (najnizaCijena.value === null || aktivniGradovi.value.length === 0) return '';
  return `Od ${Math.round(najnizaCijena.value)} KM godišnje. ${nabrojiGradove(aktivniGradovi.value)}. Zaposleni majstori, ne posrednici.`;
});

const ctaLinija = computed(() => {
  if (aktivniGradovi.value.length === 0) return 'Pretplata na održavanje doma. Zaposleni majstori, javni cjenovnik, garancija na rad do 12 mjeseci.';
  return `Pretplata na održavanje doma. ${nabrojiGradove(aktivniGradovi.value)}. Zaposleni majstori, javni cjenovnik, garancija na rad do 12 mjeseci.`;
});

const obecanje = [
  { t: 'Poznata cijena', d: 'Cijenu vidite prije nego što majstor uzme alat. On otvori cjenovnik, okrene ekran ka vama i čeka da potvrdite. Cjenovnik je javan i isti za sve.' },
  { t: 'Dogovoren rok', d: 'Dobijete termin u prozoru od dva sata, ne „poslije podne". Ako ne dođemo u roku iz vašeg paketa, sljedeća intervencija je besplatna.' },
  { t: 'Pisana garancija', d: 'Na rad dajemo garanciju od 6 do 12 mjeseci, sa datumom napismeno. Ako se isti kvar ponovi u tom roku, dolazimo bez naplate.' },
];

const ICONS = {
  voda: 'M12 3 C12 3 6 10 6 14 a6 6 0 0 0 12 0 C18 10 12 3 12 3 Z',
  elek: 'M13 2 L5 13 h5 l-1 9 8-12 h-5 z',
  grij: 'M4 5 h16 M4 5 v14 M8 5 v14 M12 5 v14 M16 5 v14 M20 5 v14 M4 19 h16',
  klim: 'M3 5 h18 v7 H3 z M6 15 v3 M12 15 v4 M18 15 v3',
  stol: 'M4 3 h16 v18 H4 z M4 12 h16 M16 7 h2',
  mol: 'M4 3 h11 v6 H4 z M9 9 v4 h3 v8 h-3',
  brav: 'M6 11 V8 a6 6 0 0 1 12 0 v3 M4 11 h16 v10 H4 z',
  plus: 'M12 5 v14 M5 12 h14',
};
const usluge = [
  { t: 'Vodovod', d: ICONS.voda },
  { t: 'Elektrika', d: ICONS.elek },
  { t: 'Grijanje', d: ICONS.grij },
  { t: 'Klimatizacija', d: ICONS.klim },
  { t: 'Stolarija', d: ICONS.stol },
  { t: 'Molerski radovi', d: ICONS.mol },
  { t: 'Bravarija', d: ICONS.brav },
  { t: 'I još mnogo toga', d: ICONS.plus },
];

onMounted(async () => {
  const [pkgs, gradovi] = await Promise.all([fetchPackages(), fetchCities()]);
  packages.value = pkgs;
  cities.value = gradovi;
});
</script>

<template>
  <PublicLayout>
    <section class="section-top container hero-grid">
      <div>
        <h1 class="hero-h1" style="margin-bottom:28px">Pukla cijev.<br>Nestalo struje.<br>Vrata se ne zatvaraju.</h1>
        <p class="hero-sub" style="max-width:520px;margin-bottom:40px">Ne tražite majstora. Ne pregovarate cijenu. Ne čekate cijeli dan.</p>
        <div style="display:flex;gap:12px;margin-bottom:32px;flex-wrap:wrap">
          <RouterLink to="/cjenovnik" class="btn btn-ember">Pogledajte pakete</RouterLink>
          <RouterLink to="/cjenovnik#cjenovnik-radova" class="btn btn-ghost-ink">Cjenovnik radova</RouterLink>
        </div>
        <p v-if="heroLinija" class="num hero-note" style="font-size:14px;font-weight:400;color:var(--bark)">{{ heroLinija }}</p>
      </div>
      <div class="hero-image-wrap">
        <img :src="'/assets/majstor.png'" alt="HAUS majstor" class="hero-image">
      </div>
    </section>

    <section class="promise-band">
      <div class="container hairline-grid grid-cols-3" style="background:transparent;border:0">
        <div v-for="o in obecanje" :key="o.t" style="background:var(--ivory);padding:36px 32px">
          <h3 style="font-size:26px;font-weight:700;line-height:1.15;margin-bottom:14px">{{ o.t }}</h3>
          <p style="font-size:15px;font-weight:400;color:var(--bark);line-height:1.55">{{ o.d }}</p>
        </div>
      </div>
      <p class="container promise-master">Jedna prijava. Poznata cijena. Dogovoren rok. Pisana garancija.</p>
    </section>

    <AppPromo variant="band" />

    <section class="container section-top">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:40px;gap:24px;flex-wrap:wrap">
        <h2 class="section-h2">Tri paketa. Godišnje plaćanje.</h2>
        <RouterLink to="/cjenovnik" style="font-size:15px;font-weight:600;text-decoration-thickness:2px">Detaljna uporedba</RouterLink>
      </div>
      <div v-if="packages.length" class="grid-cols-3" style="gap:24px;align-items:stretch">
        <PackageCard v-for="pkg in packages" :key="pkg.id" :pkg="pkg" :recommended="pkg.slug === 'haus-plus'" />
      </div>
    </section>

    <section class="container section">
      <h2 class="eyebrow" style="margin-bottom:40px">Šta pokrivamo</h2>
      <div class="hairline-grid grid-cols-4">
        <div
          v-for="(u, i) in usluge"
          :key="u.t"
          :style="{
            background: i === usluge.length - 1 ? 'var(--ink)' : 'var(--white)',
            padding: '28px 24px',
            display: 'flex',
            flexDirection: 'column',
            gap: '16px',
            minHeight: '150px',
          }"
        >
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" :stroke="i === usluge.length - 1 ? 'var(--ember)' : 'var(--ink)'" stroke-width="1.5" stroke-linecap="square" aria-hidden="true"><path :d="u.d"></path></svg>
          <span :style="{ fontSize: '17px', fontWeight: i === usluge.length - 1 ? 600 : 400, color: i === usluge.length - 1 ? 'var(--ivory)' : 'var(--ink)', lineHeight: 1.3 }">{{ u.t }}</span>
        </div>
      </div>
    </section>

    <section class="container" style="padding:0 20px 96px">
      <div class="hairline-grid grid-cols-2 beforeafter-grid">
        <div class="beforeafter-photo">
          <p class="small-print" style="text-align:center;max-width:340px">Fotografija PRIJE: stvarna tabla sa osiguračima, prirodno svjetlo, bez blica</p>
        </div>
        <div class="beforeafter-photo">
          <p class="small-print" style="text-align:center;max-width:340px">Fotografija POSLIJE: isti kadar, ruke u radu, naljepnica HAUS Trag u tabli</p>
        </div>
        <div style="background:var(--ivory);padding:32px;grid-column:1 / -1">
          <p style="font-size:20px;font-weight:500;line-height:1.4;max-width:760px">Fotografija prije i poslije ide u vaš HAUS Karton, historiju doma. Šta je ugrađeno, kad je zadnji put rađeno, šta dolazi na red.</p>
        </div>
      </div>
    </section>

    <section class="closing-band">
      <div class="container closing-grid">
        <div>
          <p style="font-size:44px;font-weight:700;color:var(--ivory);line-height:1.1;letter-spacing:-0.015em;margin-bottom:16px" class="closing-title">Vi živite. HAUS održava.</p>
          <p style="font-size:17px;font-weight:400;color:var(--sand);line-height:1.55;max-width:520px">{{ ctaLinija }}</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px">
          <RouterLink to="/registracija" class="btn btn-ember" style="padding:19px 30px;font-size:17px">Pretplati se</RouterLink>
          <RouterLink to="/kontakt" class="btn btn-ghost-ivory" style="padding:18px 28px;font-size:17px">Pošaljite pitanje</RouterLink>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<style scoped>
.hero-grid {
  display: grid;
  grid-template-columns: 1fr 440px;
  gap: 64px;
  align-items: end;
}
.hero-note { padding-bottom: 80px; }
/* min-width: 0 nuli automatski minimum grid stavki: bez toga slika
   (prirodno 701px) naduva kolonu preko viewporta na mobilnom. */
.hero-grid > * {
  min-width: 0;
}
.hero-image-wrap {
  background: var(--white);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  height: 560px;
}
.hero-image {
  height: 100%;
  max-width: 100%;
  width: auto;
  object-fit: contain;
  object-position: bottom;
  margin-bottom: -1px;
}
.promise-band {
  background: var(--ember);
  padding: 72px 0;
}
.promise-master {
  margin-top: 40px;
  font-size: 34px;
  font-weight: 600;
  color: var(--ivory);
  line-height: 1.2;
}
.beforeafter-photo {
  background: var(--white);
  height: 420px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
}
.closing-band {
  background: var(--ink);
  padding: 80px 0;
}
.closing-grid {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 80px;
  align-items: center;
}

@media (max-width: 1024px) {
  .hero-grid { grid-template-columns: 1fr; gap: 32px; align-items: center; }
  .hero-note { padding-bottom: 0; }
  .hero-image-wrap { height: auto; max-height: 420px; order: 2; }
  .hero-image { max-height: 420px; }
  .closing-grid { grid-template-columns: 1fr; gap: 32px; text-align: left; }
}
@media (max-width: 768px) {
  .promise-band { padding: 48px 0; }
  .promise-master { font-size: 24px; margin-top: 28px; }
  .beforeafter-photo { height: 260px; }
  .closing-band { padding: 56px 0; }
  .closing-title { font-size: 30px !important; }
}
@media (max-width: 480px) {
  .beforeafter-photo { height: 200px; }
}
</style>
