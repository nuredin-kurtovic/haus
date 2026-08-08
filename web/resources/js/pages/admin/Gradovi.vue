<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useToastStore } from '../../stores/toast';
import { fetchAdminCities, createCity, updateCity, deleteCity, ApiError } from './api';

const toast = useToastStore();

const cities = ref([]);
const loading = ref(true);
const mapEl = ref(null);
let map = null;
let markers = null;

const form = ref({ name: '', lat: '', lng: '' });
const formErrors = ref({});
const submitting = ref(false);

function pinIcon(city) {
  const active = city.status === 'aktivan';
  const fill = active ? '#FE5100' : '#FFFCF2';
  const safeName = city.name.replace(/[<>&]/g, '');
  const html = `
    <div style="position:relative;width:20px;height:20px">
      <span style="position:absolute;inset:0;background:${fill};border:2px solid #252422;display:block"></span>
      <span style="position:absolute;left:27px;top:1px;background:#252422;color:#FFFCF2;font:600 12px/1.35 Poppins,system-ui,sans-serif;padding:4px 8px;white-space:nowrap">
        ${safeName}${active ? '' : ' · u pripremi'}
      </span>
    </div>`;
  return L.divIcon({ className: 'haus-pin', html, iconSize: [20, 20], iconAnchor: [10, 10] });
}

function drawMarkers() {
  if (!map) return;
  if (markers) markers.clearLayers();
  markers = markers || L.layerGroup().addTo(map);
  const valid = cities.value.filter((c) => typeof c.lat === 'number' && typeof c.lng === 'number');
  valid.forEach((city) => {
    // Aktivan grad (ember pin) iznad grada u pripremi, isti razlog kao na javnoj mapi.
    const zIndexOffset = city.status === 'aktivan' ? 1000 : 0;
    L.marker([city.lat, city.lng], { icon: pinIcon(city), keyboard: false, title: city.name, zIndexOffset }).addTo(markers);
  });
  if (valid.length > 0) {
    const bounds = L.latLngBounds(valid.map((c) => [c.lat, c.lng]));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });
  }
}

async function load() {
  loading.value = true;
  try {
    const body = await fetchAdminCities();
    cities.value = body.data;
    await nextTick();
    if (map) map.invalidateSize();
    drawMarkers();
  } finally {
    loading.value = false;
  }
}

async function toggleStatus(city) {
  const nextStatus = city.status === 'aktivan' ? 'u_pripremi' : 'aktivan';
  try {
    await updateCity(city.id, { status: nextStatus });
    toast.show(nextStatus === 'aktivan' ? `${city.name} je aktiviran.` : `${city.name} je pauziran.`);
    await load();
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Izmjena stanja nije uspjela.');
  }
}

async function removeCity(city) {
  const ok = window.confirm(`Uklonite ${city.name}?`);
  if (!ok) return;
  try {
    const body = await deleteCity(city.id);
    toast.show(body?.message || `${city.name} je uklonjen.`);
    await load();
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Grad se ne može ukloniti.');
  }
}

function validateForm() {
  const errors = {};
  if (!form.value.name.trim()) errors.name = 'Ime grada je obavezno.';
  const lat = Number(form.value.lat);
  const lng = Number(form.value.lng);
  if (form.value.lat === '' || Number.isNaN(lat)) {
    errors.lat = 'Latituda je obavezna.';
  } else if (lat < 42 || lat > 46) {
    errors.lat = 'Geografska širina mora biti između 42 i 46, unutar granica BiH.';
  }
  if (form.value.lng === '' || Number.isNaN(lng)) {
    errors.lng = 'Longituda je obavezna.';
  } else if (lng < 15 || lng > 20) {
    errors.lng = 'Geografska dužina mora biti između 15 i 20, unutar granica BiH.';
  }
  formErrors.value = errors;
  return Object.keys(errors).length === 0;
}

async function submitForm() {
  if (!validateForm()) return;
  submitting.value = true;
  try {
    const body = await createCity({ name: form.value.name.trim(), lat: Number(form.value.lat), lng: Number(form.value.lng) });
    toast.show(body.message || 'Grad je dodan u stanju u pripremi.');
    form.value = { name: '', lat: '', lng: '' };
    formErrors.value = {};
    await load();
  } catch (error) {
    if (error instanceof ApiError) {
      formErrors.value = error.errors || {};
      toast.show(error.message);
    } else {
      toast.show('Dodavanje grada nije uspjelo.');
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  map = L.map(mapEl.value, { scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 6 });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> saradnici',
    maxZoom: 18,
  }).addTo(map);
  map.setView([44.1, 17.7], 7);
  await load();
  window.setTimeout(() => {
    if (!map) return;
    map.invalidateSize();
    drawMarkers();
  }, 60);
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px">
        <h1 class="admin-h1">Gradovi</h1>
        <p class="num" style="font-size:14px;font-weight:400;color:var(--bark)">{{ cities.length }} gradova</p>
      </div>

      <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
        <div>
          <div style="border:1px solid var(--sand);margin-bottom:24px">
            <div ref="mapEl" style="width:100%;height:340px"></div>
          </div>

          <div class="admin-table-scroll">
            <table class="table-haus" style="border:1px solid var(--sand)">
            <thead>
              <tr>
                <th>Grad</th>
                <th>Koordinate</th>
                <th>Stanje</th>
                <th class="num" style="text-align:right">Adrese</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loading && cities.length === 0">
                <td colspan="5" style="color:var(--bark)">Nema upisanih gradova. Dodajte prvi u formi desno.</td>
              </tr>
              <tr v-for="(city, i) in cities" :key="city.id" :class="{ zebra: i % 2 === 1 }">
                <td style="font-weight:600">{{ city.name }}</td>
                <td class="num" style="color:var(--bark)">{{ city.lat }}, {{ city.lng }}</td>
                <td>
                  <span class="chip" :class="{ 'chip-ink': city.status === 'aktivan' }">{{ city.status === 'aktivan' ? 'Aktivan' : 'U pripremi' }}</span>
                </td>
                <td class="num" style="text-align:right">{{ city.properties_count }}</td>
                <td>
                  <div class="row-actions">
                    <button type="button" class="row-action-btn" @click="toggleStatus(city)">{{ city.status === 'aktivan' ? 'Pauzirajte' : 'Aktivirajte' }}</button>
                    <button type="button" class="row-action-btn muted" @click="removeCity(city)">Uklonite</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
          <p style="margin-top:18px;font-size:14px;font-weight:400;color:var(--bark);max-width:760px;line-height:1.55">Aktivan grad se odmah pojavljuje u listi pri registraciji i na javnoj mapi. Grad u pripremi se vidi na mapi, ali se u njemu ne može pretplatiti.</p>
        </div>

        <aside class="form-aside">
          <div class="form-aside-head">
            <h2>Dodajte grad</h2>
          </div>
          <form class="form-aside-body" @submit.prevent="submitForm">
            <div class="field">
              <label class="field-label" for="g-ime">Ime grada</label>
              <input id="g-ime" v-model="form.name" type="text" placeholder="npr. Brčko" :class="{ 'field-error': formErrors.name }">
              <span v-if="formErrors.name" class="field-error-text">{{ formErrors.name[0] || formErrors.name }}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="field">
                <label class="field-label" for="g-lat">Latituda</label>
                <input id="g-lat" v-model="form.lat" type="text" inputmode="decimal" placeholder="44.8694" class="num" :class="{ 'field-error': formErrors.lat }">
              </div>
              <div class="field">
                <label class="field-label" for="g-lng">Longituda</label>
                <input id="g-lng" v-model="form.lng" type="text" inputmode="decimal" placeholder="18.8106" class="num" :class="{ 'field-error': formErrors.lng }">
              </div>
            </div>
            <span v-if="formErrors.lat" class="field-error-text">{{ formErrors.lat[0] || formErrors.lat }}</span>
            <span v-if="formErrors.lng" class="field-error-text">{{ formErrors.lng[0] || formErrors.lng }}</span>
            <button type="submit" class="btn btn-ember btn-block" :disabled="submitting">{{ submitting ? 'Dodavanje...' : 'Dodajte grad' }}</button>
            <p style="font-size:13px;font-weight:400;color:var(--bark);line-height:1.5">Koordinate su centar grada, u decimalnim stepenima. Novi grad ide u stanje u pripremi, pa se vidi na mapi ali se u njemu ne može pretplatiti dok ga ne aktivirate.</p>
          </form>
        </aside>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Isti tretman kao na javnoj mapi (/gdje-radimo): smirene OSM pločice, ember pinovi ostaju puni ton. */
:deep(.leaflet-tile-pane) {
  filter: grayscale(1) contrast(1.05) brightness(1.02);
}
:deep(.leaflet-container img) {
  max-width: none;
}
</style>
