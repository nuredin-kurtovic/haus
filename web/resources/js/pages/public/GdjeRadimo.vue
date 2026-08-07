<script setup>
import { onMounted, onBeforeUnmount, ref, computed, nextTick } from 'vue';
import L from 'leaflet';
import PublicLayout from '../../layouts/PublicLayout.vue';
import { fetchCities } from '../../api/catalog';
import { grada } from '../../utils/format';

const cities = ref([]);
const mapEl = ref(null);
let map = null;
let markers = null;

const aktivni = computed(() => cities.value.filter((c) => c.status === 'aktivan'));
const pripremi = computed(() => cities.value.filter((c) => c.status !== 'aktivan'));

const uvod = computed(() => {
  if (cities.value.length === 0) return '';
  let text = `Radimo u ${grada(aktivni.value.length)}.`;
  if (pripremi.value.length > 0) {
    text += ` ${pripremi.value.length} ${pripremi.value.length === 1 ? 'je' : 'su'} u pripremi.`;
  }
  text += ' Grad otvaramo kad na terenu imamo vlastite majstore koji mogu držati rok iz paketa, ne prije.';
  return text;
});

// Pin stil po design/haus-map.js: 20px kvadrat, 2px ink border, ember fill za
// aktivan grad, ivory fill za grad u pripremi. Label pločica offset 27px desno.
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
    L.marker([city.lat, city.lng], { icon: pinIcon(city), keyboard: false, title: city.name }).addTo(markers);
  });

  if (valid.length > 0) {
    const bounds = L.latLngBounds(valid.map((c) => [c.lat, c.lng]));
    map.fitBounds(bounds, { padding: [70, 90], maxZoom: 9 });
  }
}

onMounted(async () => {
  map = L.map(mapEl.value, { scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 6 });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> saradnici',
    maxZoom: 18,
  }).addTo(map);
  map.setView([44.1, 17.7], 7);

  cities.value = await fetchCities();
  await nextTick();
  drawMarkers();
  window.setTimeout(() => map && map.invalidateSize(), 60);
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
  }
});

</script>

<template>
  <PublicLayout>
    <div class="container section-narrow">
      <h1 class="page-h1" style="margin-bottom:20px">Gdje radimo</h1>
      <p v-if="uvod" class="lead num" style="max-width:760px;margin-bottom:40px">{{ uvod }}</p>

      <div style="border:1px solid var(--ink);margin-bottom:32px">
        <div ref="mapEl" style="width:100%;height:520px"></div>
      </div>

      <div v-if="cities.length" class="hairline-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:32px">
        <div v-for="c in cities" :key="c.id" style="background:var(--white);padding:28px 26px;min-height:170px">
          <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:12px">
            <h2 style="font-size:24px;font-weight:700;line-height:1.2">{{ c.name }}</h2>
            <span class="chip" :class="{ 'chip-ink': c.status === 'aktivan' }">{{ c.status === 'aktivan' ? 'Aktivan' : 'U pripremi' }}</span>
          </div>
          <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">
            {{ c.status === 'aktivan'
              ? 'Rok izlaska iz vašeg paketa vrijedi u cijelom gradu. Cjenovnik je isti kao u svim ostalim gradovima.'
              : 'U pripremi. Prijavite adresu i javljamo se prvi dan kad krenemo.' }}
          </p>
        </div>
      </div>

      <div style="background:var(--ember);padding:2px">
        <div style="background:var(--ivory);padding:32px;display:grid;grid-template-columns:1fr auto;gap:32px;align-items:center">
          <div>
            <h2 style="font-size:26px;font-weight:700;margin-bottom:8px">Nema vašeg grada?</h2>
            <p style="font-size:16px;font-weight:400;line-height:1.55;color:var(--bark);max-width:600px">Javite nam grad i adresu. Grad otvaramo kad na terenu imamo vlastite majstore koji mogu držati rok iz paketa. Javimo se prvi dan kad krenemo.</p>
          </div>
          <RouterLink to="/kontakt" class="btn btn-ember" style="white-space:nowrap">Prijavite svoj grad</RouterLink>
        </div>
      </div>
    </div>
  </PublicLayout>
</template>
