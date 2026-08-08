<script setup>
import { onMounted, ref, computed } from 'vue';
import PublicLayout from '../../layouts/PublicLayout.vue';
import PackageCard from '../../components/PackageCard.vue';
import CjenovnikRadovaSekcija from './dijelovi/CjenovnikRadovaSekcija.vue';
import { fetchPackages, fetchSettings } from '../../api/catalog';
import { sati, mjeseci } from '../../utils/format';

const packages = ref([]);
const settings = ref(null);

onMounted(async () => {
  const [pkgs, s] = await Promise.all([fetchPackages(), fetchSettings()]);
  packages.value = pkgs;
  settings.value = s;
});

const rowDefs = [
  { label: 'Cijena godišnje', get: (p) => (p.is_per_apartment ? `${Math.round(p.price_year)} KM po stanu` : `${Math.round(p.price_year)} KM`) },
  { label: 'Uključene intervencije godišnje', get: (p) => `${p.visits_per_year}` },
  { label: 'Popust na rad iznad uključenog', get: (p) => `${p.labor_discount_pct}%` },
  { label: 'Popust na materijal', get: (p) => (p.material_discount_pct > 0 ? `${p.material_discount_pct}%` : 'Nema') },
  { label: 'Rok izlaska (radni dani)', get: (p) => sati(p.deadline_hours) },
  { label: 'Hitno: poplava, struja, plin', get: (p) => `${sati(p.emergency_deadline_hours)}${p.emergency_included ? ', bez doplate' : ', uz doplatu'}` },
  { label: 'Godišnji pregled instalacija', get: (p) => (p.inspections_per_year > 0 ? `${p.inspections_per_year}×` : 'Nema') },
  { label: 'Garancija na rad', get: (p) => mjeseci(p.warranty_months) },
];

const rows = computed(() => rowDefs.map((row, i) => ({ ...row, zebra: i % 2 === 1 })));

const proPackage = computed(() => packages.value.find((p) => p.slug === 'haus-pro'));

const volumeNote = computed(() => {
  const pro = proPackage.value;
  if (!pro || !pro.volume_discount_tiers || pro.volume_discount_tiers.length === 0) return '';
  const linije = pro.volume_discount_tiers.map((tier) => {
    const rijec = tier.max && tier.max <= 4 ? 'stana' : 'stanova';
    const raspon = tier.max ? `${tier.min}–${tier.max}` : `${tier.min}+`;
    return tier.pct > 0 ? `${raspon} ${rijec} −${tier.pct}%.` : `${raspon} ${rijec} po dogovoru.`;
  });
  const zadnji = pro.volume_discount_tiers[pro.volume_discount_tiers.length - 1];
  if (zadnji && zadnji.max) {
    linije.push(`${zadnji.max + 1} i više po dogovoru.`);
  }
  return `${linije.join(' ')} Ugovor na godinu, faktura odjednom.`;
});

const bezPretplateNote = computed(() => {
  if (!settings.value) return '';
  const izlazak = Math.round(settings.value.izlazak_bez_pretplate);
  return `Izlazak je ${izlazak} KM plus rad po cijeni iz kolone „Bez pretplate" u cjenovniku radova. Pretplata se aktivira odmah nakon uplate. Prvu prijavu možete poslati istog dana.`;
});
</script>

<template>
  <PublicLayout>
    <div class="container section-narrow">
      <h1 class="page-h1" style="margin-bottom:20px">Cjenovnik</h1>
      <p class="lead" style="max-width:680px;margin-bottom:56px">Godišnje plaćanje, automatska obnova. Cijene su sa PDV-om. Popust ide na rad, nikad na materijal.</p>

      <div v-if="packages.length" class="grid-cols-3" style="gap:24px;align-items:stretch;margin-bottom:80px">
        <PackageCard v-for="pkg in packages" :key="pkg.id" :pkg="pkg" :recommended="pkg.slug === 'haus-plus'" title-size="26px" />
      </div>

      <h2 class="eyebrow" style="margin-bottom:24px">Uporedba paketa</h2>
      <div v-if="packages.length" class="table-scroll" style="margin-bottom:32px">
        <table class="table-haus">
          <thead>
            <tr>
              <th style="width:38%">&nbsp;</th>
              <th
                v-for="pkg in packages"
                :key="pkg.id"
                :class="pkg.slug === 'haus-plus' ? 'col-plus-head' : ''"
                style="font-size:14px;letter-spacing:.06em;text-transform:none"
              >{{ pkg.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.label" :class="{ zebra: row.zebra }">
              <th scope="row">{{ row.label }}</th>
              <td
                v-for="pkg in packages"
                :key="pkg.id"
                :class="pkg.slug === 'haus-plus' ? 'col-plus-cell num' : 'num'"
                :style="pkg.slug !== 'haus-plus' ? { color: 'var(--bark)' } : {}"
              >{{ row.get(pkg) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="grid-cols-2" style="gap:32px">
        <div v-if="volumeNote" style="border:1px solid var(--sand);background:var(--ivory);padding:28px">
          <h3 style="font-size:18px;font-weight:600;margin-bottom:12px">Više stanova na HAUS Pro</h3>
          <p class="num" style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">{{ volumeNote }}</p>
        </div>
        <div v-if="bezPretplateNote" style="border:1px solid var(--sand);background:var(--ivory);padding:28px">
          <h3 style="font-size:18px;font-weight:600;margin-bottom:12px">Bez pretplate</h3>
          <p class="num" style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">{{ bezPretplateNote }}</p>
        </div>
      </div>

      <div class="radova-wrap">
        <CjenovnikRadovaSekcija />
      </div>
    </div>
  </PublicLayout>
</template>

<style scoped>
.radova-wrap {
  border-top: 1px solid var(--sand);
  margin-top: 96px;
  padding-top: 96px;
}
@media (max-width: 768px) {
  .radova-wrap { margin-top: 56px; padding-top: 56px; }
}
@media (max-width: 480px) {
  .radova-wrap { margin-top: 40px; padding-top: 40px; }
}
</style>
