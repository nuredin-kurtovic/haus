<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../layouts/AdminLayout.vue';
import StateChip from '../../components/StateChip.vue';
import JobCompleteModal from '../../components/JobCompleteModal.vue';
import { useToastStore } from '../../stores/toast';
import {
  fetchJobs, fetchJob, updateJob, fetchNotificationPreview, fetchTechnicians, createWarrantyJob, ApiError,
} from './api';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const FILTERS = [
  { value: '', label: 'Svi', countKey: 'ukupno' },
  { value: 'novo', label: 'Novo', countKey: 'novo' },
  { value: 'zakazano', label: 'Zakazano', countKey: 'zakazano' },
  { value: 'u_toku', label: 'U toku', countKey: 'u_toku' },
  { value: 'zavrseno', label: 'Završeno', countKey: 'zavrseno' },
];

const jobs = ref([]);
const meta = ref({ counts: {}, total: 0 });
const loadingList = ref(true);
const filterStatus = ref('');
const q = ref('');

const selectedId = ref(route.query.sel ? Number(route.query.sel) : null);
const selectedJob = ref(null);
const loadingDetail = ref(false);
const technicians = ref([]);

const formTechnicianId = ref(null);
const formDate = ref('');
const formStartTime = ref('');
const activeAction = ref('zakazano');
const savingAction = ref(false);

const previewText = ref('');
const previewLoading = ref(false);
let previewDebounce = null;

const showCompleteModal = ref(false);
const warrantyLoading = ref(false);

let qDebounce = null;

async function loadJobs() {
  loadingList.value = true;
  try {
    const body = await fetchJobs({ status: filterStatus.value || undefined, q: q.value || undefined, per_page: 50 });
    jobs.value = body.data;
    meta.value = body.meta;
  } finally {
    loadingList.value = false;
  }
}

function addTwoHours(timeStr) {
  const [h, m] = timeStr.split(':').map(Number);
  const total = h * 60 + m + 120;
  const eh = Math.floor(total / 60) % 24;
  const em = total % 60;
  return `${String(eh).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
}

const formEndTime = computed(() => (formStartTime.value ? addTwoHours(formStartTime.value) : ''));
const windowStartIso = computed(() => (formDate.value && formStartTime.value ? `${formDate.value}T${formStartTime.value}:00` : ''));
const windowEndIso = computed(() => (formDate.value && formEndTime.value ? `${formDate.value}T${formEndTime.value}:00` : ''));

function isoDatePart(iso) {
  return iso ? iso.slice(0, 10) : '';
}
function isoTimePart(iso) {
  return iso ? iso.slice(11, 16) : '';
}

function resetFormFromJob(job) {
  formTechnicianId.value = job.technician ? job.technician.id : null;
  formDate.value = isoDatePart(job.scheduled_window_start);
  formStartTime.value = isoTimePart(job.scheduled_window_start);
  activeAction.value = job.status === 'zakazano' ? 'u_toku' : 'zakazano';
}

async function openJob(id) {
  selectedId.value = id;
  router.replace({ query: { ...route.query, sel: id } });
}

function closeDetail() {
  selectedId.value = null;
  selectedJob.value = null;
  const query = { ...route.query };
  delete query.sel;
  router.replace({ query });
}

async function loadDetail() {
  if (!selectedId.value) {
    selectedJob.value = null;
    return;
  }
  loadingDetail.value = true;
  try {
    const job = await fetchJob(selectedId.value);
    selectedJob.value = job;
    resetFormFromJob(job);
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Nalog nije pronađen.');
    closeDetail();
  } finally {
    loadingDetail.value = false;
  }
}

function previewParams() {
  if (!selectedJob.value) return null;
  if (activeAction.value === 'zakazano') {
    return {
      status: 'zakazano',
      technician_id: formTechnicianId.value || undefined,
      scheduled_window_start: windowStartIso.value || undefined,
      scheduled_window_end: windowEndIso.value || undefined,
    };
  }
  if (activeAction.value === 'u_toku') {
    return { status: 'u_toku' };
  }
  return null;
}

function canPreview() {
  if (!selectedJob.value) return false;
  if (activeAction.value === 'zakazano') {
    return Boolean(formTechnicianId.value && windowStartIso.value);
  }
  return activeAction.value === 'u_toku';
}

async function refreshPreview() {
  if (!selectedJob.value || selectedJob.value.status === 'zavrseno') {
    previewText.value = '';
    return;
  }
  if (!canPreview()) {
    previewText.value = '';
    return;
  }
  previewLoading.value = true;
  try {
    const data = await fetchNotificationPreview(selectedJob.value.id, previewParams());
    previewText.value = data.body;
  } catch (error) {
    previewText.value = '';
  } finally {
    previewLoading.value = false;
  }
}

function schedulePreview() {
  if (previewDebounce) window.clearTimeout(previewDebounce);
  previewDebounce = window.setTimeout(refreshPreview, 250);
}

async function confirmAction() {
  if (!selectedJob.value) return;
  savingAction.value = true;
  try {
    const payload = { status: activeAction.value };
    if (activeAction.value === 'zakazano') {
      payload.technician_id = formTechnicianId.value;
      payload.scheduled_window_start = windowStartIso.value;
      payload.scheduled_window_end = windowEndIso.value;
    }
    const body = await updateJob(selectedJob.value.id, payload);
    toast.show(body.message || 'Nalog je ažuriran.');
    await Promise.all([loadJobs(), loadDetail()]);
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Izmjena nije uspjela.');
  } finally {
    savingAction.value = false;
  }
}

function selectTechnician(techId) {
  // Bira majstora u formi. Sama dodjela ide tek uz potvrdu termina (PATCH sa status poljem),
  // po pravilu: dodjela bez stanja je dozvoljena i ne šalje obavještenje, ali ovaj ekran
  // dodjeljuje majstora uvijek zajedno sa terminom, da preview odmah pokaže tačnu poruku.
  formTechnicianId.value = techId;
}

async function onCompleted() {
  showCompleteModal.value = false;
  toast.show('Nalog je završen.');
  await Promise.all([loadJobs(), loadDetail()]);
}

async function openWarrantyJob() {
  if (!selectedJob.value) return;
  warrantyLoading.value = true;
  try {
    const body = await createWarrantyJob(selectedJob.value.id, {});
    toast.show(body.message || 'Garancijski nalog je otvoren.');
    await loadJobs();
    await openJob(body.data.id);
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Otvaranje garancijskog naloga nije uspjelo.');
  } finally {
    warrantyLoading.value = false;
  }
}

watch([filterStatus], loadJobs);
watch(q, () => {
  if (qDebounce) window.clearTimeout(qDebounce);
  qDebounce = window.setTimeout(loadJobs, 300);
});
watch(selectedId, loadDetail);
watch([formTechnicianId, windowStartIso, activeAction], schedulePreview);

onMounted(async () => {
  await loadJobs();
  technicians.value = await fetchTechnicians();
  if (selectedId.value) await loadDetail();
});
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;gap:16px">
        <h1 class="admin-h1">Zahtjevi</h1>
        <p class="num" style="font-size:14px;font-weight:400;color:var(--bark)">{{ jobs.length }} od {{ meta.counts?.ukupno ?? 0 }} naloga</p>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;margin-bottom:20px;justify-content:space-between">
        <nav class="filter-chip-row" aria-label="Filter po stanju">
          <button
            v-for="f in FILTERS"
            :key="f.value"
            type="button"
            class="filter-chip"
            :class="{ active: filterStatus === f.value }"
            @click="filterStatus = f.value"
          >{{ f.label }} · {{ meta.counts?.[f.countKey] ?? 0 }}</button>
        </nav>
        <input
          v-model="q"
          type="text"
          placeholder="Pretražite po broju, opisu, klijentu ili ulici"
          style="border:1px solid var(--ink);background:var(--white);padding:11px 14px;font-size:15px;width:340px"
        >
      </div>

      <div style="display:grid;gap:24px;align-items:start" :style="{ gridTemplateColumns: `1fr ${selectedId ? '420px' : '0px'}` }">
        <table class="table-haus" style="border:1px solid var(--sand)">
          <thead>
            <tr>
              <th>Broj</th>
              <th>Klijent</th>
              <th>Kategorija</th>
              <th>Stanje</th>
              <th>Hitno</th>
              <th>Rok</th>
              <th>Termin</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!loadingList && jobs.length === 0">
              <td colspan="7" style="color:var(--bark)">Nema naloga za ovaj filter. Promijenite pretragu ili stanje.</td>
            </tr>
            <tr
              v-for="(job, i) in jobs"
              :key="job.id"
              style="cursor:pointer"
              :style="{ background: selectedId === job.id ? 'var(--ivory)' : (i % 2 === 1 ? 'var(--zebra)' : 'var(--white)') }"
              @click="openJob(job.id)"
            >
              <td class="num" style="font-weight:600">{{ job.number }}</td>
              <td>{{ job.client?.name }}</td>
              <td style="color:var(--bark)">{{ job.category }}</td>
              <td><StateChip :state="job.status" /></td>
              <td>
                <span v-if="job.is_emergency" style="font-size:12px;font-weight:600;color:var(--ink)">HITNO</span>
                <span v-else style="color:var(--bark)">Ne</span>
              </td>
              <td class="num" :style="{ fontWeight: job.deadline_missed_at ? 600 : 400 }">{{ new Date(job.deadline_at).toLocaleString('bs-BA', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}</td>
              <td class="num">
                <span v-if="job.scheduled_window_start">{{ new Date(job.scheduled_window_start).toLocaleString('bs-BA', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }}</span>
                <span v-else style="color:var(--bark)">Nezakazano</span>
              </td>
            </tr>
          </tbody>
        </table>

        <aside v-if="selectedId" class="detail-aside">
          <div v-if="loadingDetail || !selectedJob" class="detail-aside-body">Učitavanje...</div>
          <template v-else>
            <div class="detail-aside-head">
              <span class="detail-aside-head-title">
                <span class="num">{{ selectedJob.number }}</span>
                <StateChip :state="selectedJob.status" />
              </span>
              <button type="button" class="btn btn-ghost-ivory" style="padding:5px 10px;font-size:12px" @click="closeDetail">Zatvorite</button>
            </div>
            <div class="detail-aside-body">
              <dl class="detail-dl">
                <div class="detail-dl-row"><dt>Klijent</dt><dd>{{ selectedJob.client?.name }} · {{ selectedJob.client?.email }}</dd></div>
                <div class="detail-dl-row"><dt>Adresa</dt><dd>{{ selectedJob.address?.city }}, {{ selectedJob.address?.street }}</dd></div>
                <div class="detail-dl-row"><dt>Kategorija</dt><dd>{{ selectedJob.category }}</dd></div>
                <div class="detail-dl-row"><dt>Opis</dt><dd>{{ selectedJob.description }}</dd></div>
                <div class="detail-dl-row"><dt>Rok</dt><dd class="num">{{ new Date(selectedJob.deadline_at).toLocaleString('bs-BA') }}</dd></div>
                <div v-if="selectedJob.photos?.some(p => p.type === 'prije')" class="detail-dl-row">
                  <dt>Foto prijave</dt>
                  <dd><a :href="selectedJob.photos.find(p => p.type === 'prije').url" target="_blank" rel="noopener">Pogledajte fotografiju</a></dd>
                </div>
              </dl>

              <template v-if="selectedJob.status !== 'zavrseno'">
                <h3 class="detail-section-label">Dodijelite majstora</h3>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px">
                  <button
                    v-for="tech in technicians"
                    :key="tech.id"
                    type="button"
                    class="choice-btn"
                    :class="{ selected: formTechnicianId === tech.id }"
                    @click="selectTechnician(tech.id)"
                  >
                    <span>{{ tech.name }}</span>
                    <span class="choice-btn-note">{{ tech.trade }}</span>
                  </button>
                  <p v-if="technicians.length === 0" style="font-size:14px;color:var(--bark)">Nema upisanih majstora. Dodajte ih u Postavkama.</p>
                </div>

                <h3 class="detail-section-label">Prozor termina, tačno 2 sata</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px">
                  <div class="field">
                    <label class="field-label" for="win-date">Datum</label>
                    <input id="win-date" v-model="formDate" type="date">
                  </div>
                  <div class="field">
                    <label class="field-label" for="win-time">Od</label>
                    <input id="win-time" v-model="formStartTime" type="time" step="900">
                  </div>
                </div>
                <p v-if="formEndTime" class="num" style="font-size:14px;color:var(--bark);margin-bottom:24px">Do {{ formEndTime }}, automatski izračunato.</p>
                <p v-else style="font-size:14px;color:var(--bark);margin-bottom:24px">Izaberite datum i vrijeme početka.</p>

                <h3 class="detail-section-label">Stanje naloga</h3>
                <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
                  <button
                    v-if="selectedJob.status === 'novo' || selectedJob.status === 'zakazano'"
                    type="button"
                    class="choice-btn"
                    style="flex:1"
                    :class="{ selected: activeAction === 'zakazano' }"
                    @click="activeAction = 'zakazano'"
                  ><span>{{ selectedJob.status === 'zakazano' ? 'Ažurirajte termin' : 'Zakazano' }}</span></button>
                  <button
                    v-if="selectedJob.status === 'zakazano' || selectedJob.status === 'u_toku'"
                    type="button"
                    class="choice-btn"
                    style="flex:1"
                    :class="{ selected: activeAction === 'u_toku' }"
                    :disabled="selectedJob.status === 'u_toku'"
                    @click="activeAction = 'u_toku'"
                  ><span>{{ selectedJob.status === 'u_toku' ? 'Već u toku' : 'Pokrenite izlazak' }}</span></button>
                </div>

                <div class="ember-panel" style="margin-bottom:20px">
                  <div class="ember-panel-head">
                    <span class="ember-panel-head-inner" style="display:block">Obavještenje koje ide klijentu</span>
                  </div>
                  <div class="ember-panel-body">
                    <p v-if="previewLoading" style="font-size:14px;color:var(--bark)">Učitavanje pregleda...</p>
                    <p v-else-if="previewText" style="font-size:14px;line-height:1.55">{{ previewText }}</p>
                    <p v-else style="font-size:14px;color:var(--bark)">Izaberite majstora i termin da vidite pregled poruke.</p>
                  </div>
                </div>

                <button
                  v-if="selectedJob.status === 'novo' || (selectedJob.status === 'zakazano' && activeAction === 'zakazano')"
                  type="button"
                  class="btn btn-ember btn-block"
                  :disabled="!formTechnicianId || !windowStartIso || savingAction"
                  @click="confirmAction"
                >{{ savingAction ? 'Slanje...' : 'Potvrdite termin' }}</button>

                <button
                  v-if="selectedJob.status === 'zakazano' && activeAction === 'u_toku'"
                  type="button"
                  class="btn btn-ember btn-block"
                  :disabled="savingAction"
                  @click="confirmAction"
                >{{ savingAction ? 'Slanje...' : 'Pokrenite izlazak' }}</button>

                <button
                  v-if="selectedJob.status === 'u_toku'"
                  type="button"
                  class="btn btn-ember btn-block"
                  @click="showCompleteModal = true"
                >Završite nalog</button>
              </template>

              <template v-else>
                <h3 class="detail-section-label">Nalaz</h3>
                <p style="font-size:15px;line-height:1.55;margin-bottom:20px">{{ selectedJob.findings || 'Nema unesenog nalaza.' }}</p>

                <template v-if="selectedJob.invoice">
                  <h3 class="detail-section-label">Faktura {{ selectedJob.invoice.number }}</h3>
                  <dl class="detail-dl">
                    <div class="detail-dl-row"><dt>Rad</dt><dd class="num">{{ selectedJob.invoice.labor_total }} KM</dd></div>
                    <div class="detail-dl-row"><dt>Materijal</dt><dd class="num">{{ selectedJob.invoice.material_total }} KM</dd></div>
                    <div class="detail-dl-row"><dt>Ukupno</dt><dd class="num" style="font-weight:700">{{ selectedJob.invoice.total }} KM</dd></div>
                    <div class="detail-dl-row"><dt>Stanje</dt><dd><StateChip :state="selectedJob.invoice.status === 'placeno' ? 'zavrseno' : 'novo'" :label="selectedJob.invoice.status" /></dd></div>
                  </dl>
                </template>

                <div v-if="selectedJob.photos?.length" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px">
                  <a v-for="photo in selectedJob.photos" :key="photo.url" :href="photo.url" target="_blank" rel="noopener" style="font-size:13px">
                    Foto {{ photo.type === 'prije' ? 'prije' : 'poslije' }}
                  </a>
                </div>

                <p style="font-size:13px;color:var(--bark);margin-bottom:12px">Garancija do {{ selectedJob.warranty_until ? new Date(selectedJob.warranty_until).toLocaleDateString('bs-BA') : 'nema' }}.</p>

                <button type="button" class="btn btn-ghost-ink btn-block" :disabled="warrantyLoading" @click="openWarrantyJob">
                  {{ warrantyLoading ? 'Otvaranje...' : 'Garancijski nalog' }}
                </button>
              </template>
            </div>
          </template>
        </aside>
      </div>
    </div>

    <JobCompleteModal
      v-if="showCompleteModal && selectedJob"
      :job-id="selectedJob.id"
      @close="showCompleteModal = false"
      @completed="onCompleted"
    />
  </AdminLayout>
</template>
