<script setup>
import { onMounted, ref, computed } from 'vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import AppPromo from '../../components/AppPromo.vue';
import { fetchPackages } from '../../api/catalog';
import { sati } from '../../utils/format';

const packages = ref([]);

onMounted(async () => {
  packages.value = await fetchPackages();
});

const hitnoRecenica = computed(() => {
  if (!packages.value.length) return '';
  const dio = packages.value
    .map((p) => `${p.name}: ${sati(p.emergency_deadline_hours)}${p.emergency_included ? ', bez doplate' : ', uz doplatu'}.`)
    .join(' ');
  return `Poplava, struja i plin idu u poseban kanal. ${dio} Zatvorite ventil ako možete. U aplikaciji vidite kad majstor kreće.`;
});

const dijelovi = [
  { t: 'HAUS Karton', d: 'Historija doma: šta je ugrađeno, kad je zadnji put rađeno, šta dolazi na red.', ink: false },
  { t: 'HAUS Pregled', d: 'Godišnji pregled instalacija sa fotografijama i pisanim nalazom. Dokaz vrijednosti i onda kad nije bilo kvara.', ink: false },
  { t: 'HAUS Rok', d: 'Obećani rok izlaska po paketu. Padne li, sljedeća intervencija je besplatna, automatski.', ink: true },
  { t: 'HAUS Trag', d: 'Naljepnica koja ostaje u razvodnoj tabli i ispod sudopere: datum, majstor, broj, QR na karton.', ink: false },
  { t: 'HAUS Cjenovnik', d: 'Javan, isti za sve, na telefonu kod majstora. Cijena prije alata.', ink: false },
  { t: 'HAUS Hitno', d: 'Kanal za poplavu, struju i plin. Vlastiti rok i vlastiti ton.', ink: true },
];

const ukljuceno = [
  'Izlazak na adresu u roku iz vašeg paketa',
  'Dijagnostika kvara',
  'Do 45 minuta rada majstora',
  'Pisana konstatacija stanja i ponuda ako je potreban veći rad',
];

const nijeUkljuceno = [
  'Materijal i rezervni dijelovi: nabavna cijena + 20%, uz popust po paketu',
  'Rad iznad 45 minuta, po sniženoj satnici iz cjenovnika',
  'Adaptacije, renoviranje, ugradnja nove opreme, keramika, molovanje',
  'Zajednički dijelovi zgrade: vertikale, krov, fasada, stubište',
  'Radovi koji zahtijevaju građevinsku dozvolu, dizalicu ili skelu',
  'Oprema pod garancijom proizvođača gdje bi naša intervencija ukinula garanciju',
  'Posljedične štete. Popravljamo kvar, ne saniramo parket koji je nabujao',
];
</script>

<template>
  <PublicLayout>
    <div class="container section-narrow">
      <h1 class="page-h1" style="max-width:840px;margin-bottom:24px">HAUS je pretplata na održavanje doma.</h1>
      <p class="lead" style="max-width:720px;margin-bottom:72px">Platite jednom godišnje i imate jednu aplikaciju za vodu, struju, grijanje, klimu, stolariju i brave. Cijena se zna prije rada. Rok izlaska je garantovan.</p>

      <h2 class="eyebrow" style="margin-bottom:32px">Imenovani dijelovi usluge</h2>
      <div class="hairline-grid grid-cols-3 dijelovi-grid" style="margin-bottom:96px">
        <div
          v-for="d in dijelovi"
          :key="d.t"
          :style="{ background: d.ink ? 'var(--ink)' : 'var(--white)', padding: '32px 28px', minHeight: '210px', display: 'flex', flexDirection: 'column', gap: '14px' }"
        >
          <h3 :style="{ fontSize: '22px', fontWeight: 700, color: d.ink ? 'var(--ivory)' : 'var(--ink)', letterSpacing: '.01em' }">{{ d.t }}</h3>
          <p :style="{ fontSize: '15px', fontWeight: 400, lineHeight: 1.55, color: d.ink ? 'var(--sand)' : 'var(--bark)' }">{{ d.d }}</p>
        </div>
      </div>

      <h2 class="eyebrow" style="margin-bottom:32px">Šta je uključeno u jednu intervenciju</h2>
      <div class="grid-cols-2" style="gap:32px;margin-bottom:32px">
        <div style="border:1px solid var(--ink);padding:32px">
          <h3 style="font-size:26px;font-weight:700;margin-bottom:20px">Uključeno</h3>
          <ul style="display:flex;flex-direction:column;gap:12px">
            <li v-for="u in ukljuceno" :key="u" style="display:grid;grid-template-columns:16px 1fr;gap:12px;font-size:16px;font-weight:400;line-height:1.5">
              <span style="width:7px;height:7px;background:var(--ink);display:block;margin-top:9px"></span>
              <span>{{ u }}</span>
            </li>
          </ul>
        </div>
        <div style="border:1px solid var(--sand);background:var(--ivory);padding:32px">
          <h3 style="font-size:26px;font-weight:700;margin-bottom:20px">Nije uključeno ni u jednom paketu</h3>
          <ul style="display:flex;flex-direction:column;gap:12px">
            <li v-for="u in nijeUkljuceno" :key="u" style="display:grid;grid-template-columns:16px 1fr;gap:12px;font-size:16px;font-weight:400;line-height:1.5;color:var(--bark)">
              <span style="width:7px;height:7px;background:var(--bark);display:block;margin-top:9px"></span>
              <span>{{ u }}</span>
            </li>
          </ul>
        </div>
      </div>
      <p style="font-size:16px;font-weight:400;color:var(--bark);margin-bottom:96px;max-width:820px">Ovo pišemo unaprijed jer je devedeset posto sporova zato što ovo nije bilo napisano. Ako nešto nije uključeno, kažemo prije, ne poslije.</p>

      <div class="hitno-band">
        <div>
          <h2 style="font-size:40px;font-weight:700;color:var(--ivory);line-height:1.1;margin-bottom:16px">HAUS Hitno</h2>
          <div v-if="hitnoRecenica" style="background:var(--ivory);padding:22px 26px">
            <p class="num" style="font-size:16px;font-weight:400;line-height:1.55">{{ hitnoRecenica }}</p>
          </div>
        </div>
        <div style="background:var(--ink);padding:28px">
          <span style="display:block;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--sand);margin-bottom:8px">Kanal</span>
          <span style="display:block;font-size:26px;font-weight:700;color:var(--ivory);line-height:1.2">HAUS aplikacija<br>Hitno</span>
        </div>
      </div>

      <AppPromo message="Hitne prijave idu kroz aplikaciju." style="margin-top:40px" />
    </div>
  </PublicLayout>
</template>

<style scoped>
.dijelovi-grid > div { min-height: 210px; }
.hitno-band {
  background: var(--ember);
  padding: 56px;
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 56px;
  align-items: center;
}
@media (max-width: 768px) {
  .dijelovi-grid > div { min-height: 0; }
  .hitno-band { grid-template-columns: 1fr; padding: 32px 24px; gap: 24px; }
}
</style>
