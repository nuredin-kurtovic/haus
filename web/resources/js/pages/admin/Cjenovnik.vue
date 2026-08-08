<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useToastStore } from '../../stores/toast';
import { fetchPackages } from '../../api/catalog';
import { fetchPriceList, updatePriceItem, publishPriceList, ApiError } from './api';

const toast = useToastStore();

const categories = ref([]);
const packages = ref([]);
const activeCategoryId = ref(null);
const publishing = ref(false);
const savingIds = ref(new Set());

const activeCategory = computed(() => categories.value.find((c) => c.id === activeCategoryId.value) || null);

const dirtyCount = computed(() => categories.value.reduce((sum, cat) => sum + cat.items.filter((i) => i.dirty).length, 0));

function packageDiscount(slug) {
  const pkg = packages.value.find((p) => p.slug === slug);
  return pkg ? pkg.labor_discount_pct : 0;
}

function derivedPrice(basePrice, slug) {
  const pct = packageDiscount(slug);
  return Math.round(basePrice * (1 - pct / 100));
}

async function onPriceChange(item, value) {
  const num = Number(value);
  if (Number.isNaN(num) || num < 0) return;
  savingIds.value.add(item.id);
  try {
    const body = await updatePriceItem(item.id, { draft_base_price: num });
    item.draft_base_price = body.data.draft_base_price;
    item.dirty = body.data.dirty;
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Čuvanje nacrta nije uspjelo.');
  } finally {
    savingIds.value.delete(item.id);
  }
}

async function publish() {
  if (dirtyCount.value === 0) return;
  const ok = window.confirm('Objava ide na web i na telefone majstora istovremeno.');
  if (!ok) return;
  publishing.value = true;
  try {
    const body = await publishPriceList();
    toast.show(`Objavljeno izmjena: ${body.data.published}.`);
    await load();
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Objava nije uspjela.');
  } finally {
    publishing.value = false;
  }
}

async function load() {
  const [priceData, pkgData] = await Promise.all([fetchPriceList(), fetchPackages()]);
  categories.value = priceData;
  packages.value = pkgData;
  if (!activeCategoryId.value && priceData.length > 0) activeCategoryId.value = priceData[0].id;
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:32px;margin-bottom:24px">
        <div>
          <h1 class="admin-h1" style="margin-bottom:8px">Cjenovnik: uređivanje</h1>
          <p style="font-size:15px;font-weight:400;color:var(--bark);max-width:640px;line-height:1.55">Ovaj cjenovnik je javan i isti za sve. Cijena bez pretplate mora biti realna tržišna cijena.</p>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex:none">
          <span v-if="dirtyCount > 0" class="pill-unpublished">{{ dirtyCount }} pozicija izmijenjeno</span>
          <button
            v-if="dirtyCount > 0"
            type="button"
            class="btn btn-ember"
            :disabled="publishing"
            @click="publish"
          >{{ publishing ? 'Objavljivanje...' : 'Objavite izmjene' }}</button>
          <button v-else type="button" class="btn-sand-disabled" disabled>Objavite izmjene</button>
        </div>
      </div>

      <nav class="filter-chip-row" aria-label="Kategorije" style="margin-bottom:20px">
        <button
          v-for="cat in categories"
          :key="cat.id"
          type="button"
          class="filter-chip"
          :class="{ active: activeCategoryId === cat.id }"
          @click="activeCategoryId = cat.id"
        >{{ cat.name }}</button>
      </nav>

      <div class="admin-table-scroll">
        <table v-if="activeCategory" class="table-haus" style="border:1px solid var(--sand)">
        <thead>
          <tr>
            <th>Pozicija</th>
            <th class="num" style="text-align:right;width:150px">Bez pretplate</th>
            <th class="num" style="text-align:right;width:150px">HAUS Mini</th>
            <th class="num" style="text-align:right;width:150px">HAUS Plus</th>
            <th class="num" style="text-align:right;width:150px">HAUS Pro</th>
            <th style="width:150px">Stanje</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in activeCategory.items" :key="item.id" :class="{ zebra: i % 2 === 1, 'price-row-dirty': item.dirty }">
            <td>{{ item.name }}</td>
            <td style="padding:8px 16px;text-align:right">
              <input
                type="number"
                min="0"
                :value="item.draft_base_price ?? item.base_price"
                class="price-input"
                :class="{ dirty: item.dirty }"
                aria-label="Cijena bez pretplate"
                @change="onPriceChange(item, $event.target.value)"
              >
            </td>
            <td class="num" style="text-align:right;color:var(--bark)">{{ derivedPrice(item.draft_base_price ?? item.base_price, 'haus-mini') }} KM</td>
            <td class="num" style="text-align:right;color:var(--bark)">{{ derivedPrice(item.draft_base_price ?? item.base_price, 'haus-plus') }} KM</td>
            <td class="num" style="text-align:right;color:var(--bark)">{{ derivedPrice(item.draft_base_price ?? item.base_price, 'haus-pro') }} KM</td>
            <td :style="{ fontWeight: 600, fontSize: '13px', color: item.dirty ? 'var(--ink)' : 'var(--bark)' }">{{ item.dirty ? 'Nije objavljeno' : 'Objavljeno' }}</td>
          </tr>
        </tbody>
      </table>
      </div>

      <p style="margin-top:18px;font-size:14px;font-weight:400;color:var(--bark);max-width:760px;line-height:1.55">Popust se računa iz cijene bez pretplate i ne uređuje se ručno. Objavljivanje mijenja cjenovnik na sajtu i na telefonima majstora u isto vrijeme, nikad samo na jednom mjestu.</p>
    </div>
  </AdminLayout>
</template>
