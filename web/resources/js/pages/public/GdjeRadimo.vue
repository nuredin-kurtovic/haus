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
    // Aktivan grad (ember pin) mora biti iznad grada u pripremi kad se pinovi
    // sudare na tijesnijem zumu, inače mu labela nestane iza susjedne.
    const zIndexOffset = city.status === 'aktivan' ? 1000 : 0;
    L.marker([city.lat, city.lng], { icon: pinIcon(city), keyboard: false, title: city.name, zIndexOffset }).addTo(markers);
  });

  if (valid.length > 0) {
    const bounds = L.latLngBounds(valid.map((c) => [c.lat, c.lng]));
    // Padding je manji od design/haus-map.js ([70,90]) i kontejner je viši
    // (640px, ne 520px): BiH je uže-visoka nego što je ovaj sadržajni stub
    // širok (1240px), pa ista vrijednost iz reference ovdje ostavlja pola
    // Balkana u kadru jer visina, ne širina, ograničava zum. Ovim mjera
    // stane u okvir na maxZoom 9 bez sječenja gradova.
    map.fitBounds(bounds, { padding: [50, 30], maxZoom: 9 });
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
  // invalidateSize prije fitBounds, radi sigurnosti: fitBounds računa zum na
  // trenutnoj veličini kontejnera, pa ako se ona promijeni (layout, font),
  // želimo da mjeri tačnu veličinu prije nego što računa kadar.
  map.invalidateSize();
  drawMarkers();
  window.setTimeout(() => {
    if (!map) return;
    map.invalidateSize();
    drawMarkers();
  }, 60);

  window.addEventListener('resize', onWindowResize);
});

// Kontejner mijenja visinu na 768px media query (vidi <style> ispod): Leaflet
// mora ponovo izmjeriti kontejner poslije promjene, inače ostaje kadar
// izračunat na staroj visini. fitBounds/pinovi se ne diraju, samo veličina.
let resizeTimer = null;
function onWindowResize() {
  if (!map) return;
  window.clearTimeout(resizeTimer);
  resizeTimer = window.setTimeout(() => {
    if (map) map.invalidateSize();
  }, 120);
}

onBeforeUnmount(() => {
  window.removeEventListener('resize', onWindowResize);
  window.clearTimeout(resizeTimer);
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
        <div ref="mapEl" class="map-el"></div>
      </div>

      <div v-if="cities.length" class="hairline-grid grid-cols-3" style="margin-bottom:32px">
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
        <div class="coverage-cta">
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

<style scoped>
/* OSM pločice su šarene i tuku se sa brendom: smirene bojom, ember pinovi
   ostaju puni ton pošto su izvan tile pane-a. Atribucija ostaje čitljiva. */
:deep(.leaflet-tile-pane) {
  filter: grayscale(1) contrast(1.05) brightness(1.02);
}
:deep(.leaflet-container img) {
  max-width: none;
}
.map-el {
  width: 100%;
  height: 640px;
}
.coverage-cta {
  background: var(--ivory);
  padding: 32px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 32px;
  align-items: center;
}
@media (max-width: 768px) {
  .map-el { height: 420px; }
  .coverage-cta { grid-template-columns: 1fr; padding: 24px; }
}
</style>
