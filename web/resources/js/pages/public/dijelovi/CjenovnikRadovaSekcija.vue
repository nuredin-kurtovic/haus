<script setup>
// Cjenovnik radova, izvučen kao podsekcija stranice Cijene.vue (sidro
// #cjenovnik-radova). Ranije samostalna stranica /cjenovnik; ruta sada
// samo redirektuje ovamo, sadržaj i logika su nepromijenjeni.
import { onMounted, ref, computed } from 'vue';
import { fetchPackages, fetchPriceList, fetchSurcharges, fetchSettings } from '../../../api/catalog';

const packages = ref([]);
const categories = ref([]);
const surcharges = ref([]);
const settings = ref(null);
const q = ref('');
const kat = ref('Sve');

onMounted(async () => {
  const [pkgs, cats, sur, s] = await Promise.all([fetchPackages(), fetchPriceList(), fetchSurcharges(), fetchSettings()]);
  packages.value = pkgs;
  categories.value = cats;
  surcharges.value = sur;
  settings.value = s;
});

// Samo Mini i Plus se prikazuju u ovoj tabeli, kao u dizajnu. Naslov kolone
// uvijek dolazi sa /packages, nikad hardkodiran tekst.
const tableColumns = computed(() => packages.value
  .filter((p) => ['mini', 'plus'].includes(p.slug.replace('haus-', '')))
  .map((p) => ({ key: p.slug.replace('haus-', ''), label: `${p.name} · −${p.labor_discount_pct}%` })));

const satnicaLinija = computed(() => {
  if (!settings.value) return '';
  const r = Math.round(settings.value.satnica_redovna);
  const h = Math.round(settings.value.satnica_hitna);
  const i = Math.round(settings.value.izlazak_bez_pretplate);
  return `Satnica ${r} KM/h redovan rad · ${h} KM/h hitno i van radnog vremena · Izlazak bez pretplate ${i} KM`;
});

function normalize(text) {
  return text.toLowerCase();
}

const filteredCategories = computed(() => {
  const query = normalize(q.value.trim());
  return categories.value
    .filter((c) => kat.value === 'Sve' || c.name === kat.value)
    .map((c) => ({ ...c, items: c.items.filter((item) => normalize(item.name).includes(query)) }))
    .filter((c) => c.items.length > 0);
});

const brojPozicija = computed(() => filteredCategories.value.reduce((n, c) => n + c.items.length, 0));

function formatSurcharge(s) {
  if (s.type === 'percent') return `+${Math.round(s.value)}%`;
  if (s.type === 'per_km') return `${s.value.toFixed(2).replace('.', ',')} KM`;
  return `${Math.round(s.value)} KM`;
}

const pregledNota = computed(() => {
  const saUkljucenim = packages.value.filter((p) => p.inspections_per_year > 0);
  if (saUkljucenim.length === 0) return '';
  const dio = saUkljucenim.map((p) => `${p.name} (${p.inspections_per_year}×)`).join(' i ');
  return `Uključen u ${dio}. Za ostale pakete pregled se zakazuje posebno, po cijeni objavljenoj u aplikaciji. Traje 45–60 minuta, zakazuje se u april–maj i septembar–oktobar. Dobijate fotografije kritičnih tačaka i pisani nalaz sa listom preporučenih radova i procjenom cijene.`;
});
</script>

<template>
  <section id="cjenovnik-radova" style="scroll-margin-top:150px">
    <h2 class="section-h2" style="margin-bottom:20px">Cjenovnik radova</h2>
    <p class="lead" style="max-width:720px;margin-bottom:16px">Javan i isti za sve. Cijene su za rad, sa PDV-om. Materijal se naplaćuje odvojeno, po nabavnoj cijeni + 20%.</p>
    <p v-if="satnicaLinija" class="num" style="font-size:15px;font-weight:400;color:var(--bark);margin-bottom:48px">{{ satnicaLinija }}</p>

    <div style="display:flex;align-items:flex-end;gap:24px;margin-bottom:8px;flex-wrap:wrap">
      <div class="field" style="width:360px">
        <label for="cj-q" class="field-label">Pretraga po poslu</label>
        <input id="cj-q" v-model="q" type="search" placeholder="npr. baterija, bojler, brava">
      </div>
      <nav style="display:flex;gap:2px;flex-wrap:wrap" aria-label="Kategorije">
        <button
          type="button"
          class="chip"
          :class="{ 'chip-ink': kat === 'Sve' }"
          style="cursor:pointer;font-weight:600"
          @click="kat = 'Sve'"
        >Sve</button>
        <button
          v-for="c in categories"
          :key="c.id"
          type="button"
          class="chip"
          :class="{ 'chip-ink': kat === c.name }"
          style="cursor:pointer"
          :style="{ fontWeight: kat === c.name ? 600 : 400 }"
          @click="kat = c.name"
        >{{ c.name }}</button>
      </nav>
    </div>
    <p class="num" style="font-size:14px;font-weight:400;color:var(--bark);margin-bottom:20px">{{ brojPozicija }} pozicija</p>

    <section v-for="g in filteredCategories" :key="g.id" style="margin-bottom:48px">
      <h3 style="font-size:24px;font-weight:700;margin-bottom:14px">{{ g.name }}</h3>
      <div style="overflow-x:auto">
        <table class="table-haus">
          <thead>
            <tr>
              <th>Posao</th>
              <th class="num" style="text-align:right;width:150px">Bez pretplate</th>
              <th
                v-for="col in tableColumns"
                :key="col.key"
                class="num"
                :class="col.key === 'plus' ? 'col-plus-head' : ''"
                style="text-align:right;width:150px;text-transform:none;letter-spacing:.02em"
              >{{ col.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in g.items" :key="item.id">
              <td>{{ item.name }}</td>
              <td class="num" style="text-align:right;color:var(--bark)">{{ Math.round(item.base_price) }} KM</td>
              <td
                v-for="col in tableColumns"
                :key="col.key"
                class="num"
                :class="col.key === 'plus' ? 'col-plus-cell' : ''"
                style="text-align:right"
                :style="col.key !== 'plus' ? { fontWeight: 600 } : {}"
              >{{ Math.round(item.prices[col.key]) }} KM</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="filteredCategories.length === 0" style="border:1px solid var(--sand);background:var(--ivory);padding:40px;margin-bottom:48px">
      <h3 style="font-size:22px;font-weight:600;margin-bottom:10px">Nema pozicije pod tim imenom</h3>
      <p style="font-size:16px;font-weight:400;color:var(--bark);line-height:1.55">Klijent često ne zna kako se zove ono što se pokvarilo. Prijavite kvar u aplikaciji i opišite šta se dešava. Dispečer nađe poziciju. Ako pozicije nema u cjenovniku, majstor je provjerava u kancelariji pred vama.</p>
    </div>

    <h3 style="font-size:24px;font-weight:700;margin-bottom:14px">Doplate</h3>
    <div style="overflow-x:auto;margin-bottom:32px">
      <table class="table-haus">
        <tbody>
          <tr v-for="(s, i) in surcharges" :key="s.key" :class="{ zebra: i % 2 === 1 }">
            <td>{{ s.label }}</td>
            <td class="num" style="text-align:right;font-weight:600;width:180px">{{ formatSurcharge(s) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="pregledNota" style="border:1px solid var(--ink);padding:28px;max-width:820px">
      <h3 style="font-size:20px;font-weight:600;margin-bottom:12px">Godišnji pregled instalacija</h3>
      <p class="num" style="font-size:16px;font-weight:400;line-height:1.55;color:var(--bark)">{{ pregledNota }}</p>
    </div>
  </section>
</template>
