<script setup>
import { computed, onMounted, ref } from 'vue';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import { fetchClientPriceList } from './api';

const loading = ref(true);
const error = ref('');
const categories = ref([]);
const myPackage = ref(null);
const q = ref('');
const kat = ref('Sve');

async function load() {
  error.value = '';
  try {
    const body = await fetchClientPriceList();
    categories.value = body.data;
    myPackage.value = body.meta?.my_package || null;
  } catch (e) {
    error.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}
onMounted(load);

function normalize(text) {
  return (text || '').toLowerCase();
}

const filteredCategories = computed(() => {
  const query = normalize(q.value.trim());
  return categories.value
    .filter((c) => kat.value === 'Sve' || c.name === kat.value)
    .map((c) => ({ ...c, items: c.items.filter((item) => normalize(item.name).includes(query)) }))
    .filter((c) => c.items.length > 0);
});

const brojPozicija = computed(() => filteredCategories.value.reduce((n, c) => n + c.items.length, 0));

const uvodTekst = computed(() => {
  if (!myPackage.value) return 'Isti cjenovnik koji majstor otvori na telefonu kod vas. Materijal se naplaćuje odvojeno, po nabavnoj cijeni + 20%.';
  return `Isti cjenovnik koji majstor otvori na telefonu kod vas. Vaše cijene su sa popustom od ${myPackage.value.labor_discount_pct}% na rad. Materijal se naplaćuje odvojeno, po nabavnoj cijeni + 20%${myPackage.value.material_discount_pct > 0 ? `, uz vaš popust od ${myPackage.value.material_discount_pct}%` : ''}.`;
});
</script>

<template>
  <KlijentLayout>
    <div style="width:1320px;max-width:100%;margin:0 auto;padding:56px 28px 96px">
      <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em;margin-bottom:12px">Cjenovnik</h1>
      <p style="font-size:17px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:32px;max-width:720px">{{ uvodTekst }}</p>

      <div v-if="!categories.length && loading" style="padding:60px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="!categories.length && error" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ error }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <template v-else>
        <p v-if="error" class="error-panel" style="margin-bottom:24px">{{ error }} Prikazujemo zadnje učitane podatke.</p>

        <div style="display:flex;align-items:flex-end;gap:24px;margin-bottom:24px;flex-wrap:wrap">
          <div class="field" style="width:360px">
            <label for="ck-q" class="field-label">Pretraga po poslu</label>
            <input id="ck-q" v-model="q" type="search" placeholder="npr. baterija, bojler, brava">
          </div>
          <nav style="display:flex;gap:2px;flex-wrap:wrap" aria-label="Kategorije">
            <button type="button" class="chip" :class="{ 'chip-ink': kat === 'Sve' }" style="cursor:pointer;font-weight:600" @click="kat = 'Sve'">Sve</button>
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

        <section v-for="g in filteredCategories" :key="g.id" style="margin-bottom:44px">
          <h2 style="font-size:22px;font-weight:700;margin-bottom:14px">{{ g.name }}</h2>
          <div style="overflow-x:auto">
            <table class="table-haus">
              <thead>
                <tr>
                  <th>Posao</th>
                  <th class="num" style="text-align:right;width:170px">Bez pretplate</th>
                  <th class="num" style="text-align:right;width:170px;background:var(--sand);color:var(--ink);text-transform:none;letter-spacing:.02em">Vaša cijena</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in g.items" :key="item.id">
                  <td>{{ item.name }}</td>
                  <td class="num" style="text-align:right;color:var(--bark);text-decoration:line-through">{{ Math.round(item.base_price) }} KM</td>
                  <td class="num" style="text-align:right;font-weight:600;background:var(--ivory)">{{ Math.round(item.my_price ?? item.base_price) }} KM</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <div v-if="filteredCategories.length === 0" style="border:1px solid var(--sand);background:var(--ivory);padding:36px;max-width:720px">
          <h2 style="font-size:20px;font-weight:600;margin-bottom:10px">Nema pozicije pod tim imenom</h2>
          <p style="font-size:16px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:20px">Prijavite kvar i opišite šta se dešava. Ako pozicije nema u cjenovniku, majstor je provjerava u kancelariji pred vama.</p>
          <RouterLink to="/klijent/prijavi-kvar" class="btn btn-ember">Prijavite kvar</RouterLink>
        </div>
      </template>
    </div>
  </KlijentLayout>
</template>
