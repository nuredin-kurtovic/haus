<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import StateChip from '../../components/StateChip.vue';
import { fetchJob, fetchJobs } from './api';
import { formatDate, formatDateTime, jobChipState } from './format';

const route = useRoute();

const loading = ref(true);
const error = ref('');
const jobs = ref([]);
const expanded = reactive({});
const details = reactive({});
const articleRefs = {};

function setArticleRef(id, el) {
  if (el) articleRefs[id] = el;
}

async function toggle(job) {
  if (job.status !== 'zavrseno') return;
  const isOpen = Boolean(expanded[job.id]);
  expanded[job.id] = !isOpen;
  if (!isOpen && !details[job.id]) {
    details[job.id] = { loading: true, error: '', data: null };
    try {
      const data = await fetchJob(job.id);
      details[job.id] = { loading: false, error: '', data };
    } catch (e) {
      details[job.id] = { loading: false, error: (e && e.message) || 'Nalaz nije učitan. Pokušajte ponovo.', data: null };
    }
  }
}

async function focusFromQuery() {
  const id = route.query.nalog;
  if (!id) return;
  const job = jobs.value.find((j) => String(j.id) === String(id));
  if (!job) return;
  if (job.status === 'zavrseno' && !expanded[job.id]) await toggle(job);
  await nextTick();
  articleRefs[job.id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function load() {
  error.value = '';
  try {
    jobs.value = await fetchJobs();
    await focusFromQuery();
  } catch (e) {
    error.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}
onMounted(load);

function garancijaInfo(job) {
  if (job.status === 'zavrseno') {
    return { label: 'Garancija do', value: job.warranty_until ? formatDate(job.warranty_until) : 'Nema' };
  }
  return { label: 'Rok izlaska', value: formatDateTime(job.deadline_at) };
}

function photoOfType(photos, type) {
  return (photos || []).find((p) => p.type === type) || null;
}
</script>

<template>
  <KlijentLayout>
    <div class="klijent-shell">
      <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em;margin-bottom:12px">Moje intervencije</h1>
      <p style="font-size:17px;font-weight:400;color:var(--bark);margin-bottom:40px">Svaka intervencija ima nalaz, fotografije prije i poslije, i datum do kojeg traje garancija.</p>

      <div v-if="!jobs.length && loading" style="padding:60px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="!jobs.length && error" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ error }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <div v-else-if="!jobs.length" style="border:1px solid var(--sand);background:var(--ivory);padding:40px;max-width:640px">
        <h2 style="font-size:22px;font-weight:600;margin-bottom:10px">Nemate prijavljenih intervencija</h2>
        <p style="font-size:16px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:20px">Kad nešto zatreba u domu, prijavite kvar i dobijate termin u prozoru od dva sata.</p>
        <RouterLink to="/klijent/prijavi-kvar" class="btn btn-ember">Prijavite kvar</RouterLink>
      </div>

      <template v-else>
        <p v-if="error" class="error-panel" style="margin-bottom:24px">{{ error }} Prikazujemo zadnje učitane podatke.</p>

        <div style="display:flex;flex-direction:column;gap:24px">
          <article v-for="job in jobs" :key="job.id" :ref="(el) => setArticleRef(job.id, el)" style="border:1px solid var(--sand);background:var(--white)">
            <div
              class="job-head"
              :style="{ borderBottom: expanded[job.id] ? '1px solid var(--sand)' : '0', cursor: job.status === 'zavrseno' ? 'pointer' : 'default' }"
              @click="toggle(job)"
            >
              <div>
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;flex-wrap:wrap">
                  <span class="num" style="font-size:13px;font-weight:600;letter-spacing:.06em;color:var(--bark)">Nalog {{ job.number }}</span>
                  <StateChip :state="jobChipState(job)" />
                  <span v-if="job.is_emergency" class="chip chip-ink">Hitno</span>
                  <span style="font-size:13px;font-weight:400;color:var(--bark)">{{ job.category }}</span>
                </div>
                <h2 style="font-size:22px;font-weight:600;line-height:1.3;margin-bottom:6px">{{ job.title }}</h2>
                <p class="num" style="font-size:15px;font-weight:400;color:var(--bark)">Prijavljeno {{ formatDate(job.created_at) }} · Majstor {{ job.technician?.name || 'Nije dodijeljen' }}</p>
              </div>
              <div class="job-head-deadline">
                <span style="display:block;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--bark)">{{ garancijaInfo(job).label }}</span>
                <span class="num" style="display:block;font-size:20px;font-weight:600">{{ garancijaInfo(job).value }}</span>
              </div>
            </div>

            <div v-if="expanded[job.id]">
              <div v-if="details[job.id]?.loading" style="padding:24px 28px;color:var(--bark)">Učitavanje nalaza...</div>
              <p v-else-if="details[job.id]?.error" class="error-panel" style="margin:24px 28px">{{ details[job.id].error }}</p>
              <div v-else-if="details[job.id]?.data" class="job-detail-grid">
                <div class="job-detail-nalaz" style="background:var(--ivory);padding:24px 28px">
                  <h3 style="font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--bark);margin-bottom:10px">Nalaz majstora</h3>
                  <p style="font-size:15px;font-weight:400;line-height:1.6;margin-bottom:16px">{{ details[job.id].data.findings || 'Nalaz još nije upisan.' }}</p>
                  <table v-if="details[job.id].data.invoice" style="width:100%">
                    <tbody>
                      <tr v-for="item in details[job.id].data.invoice.labor_items" :key="'l' + item.name" style="border-bottom:1px solid var(--sand)">
                        <td style="padding:8px 0;font-size:14px;font-weight:400">Rad: {{ item.name }} <span class="num">× {{ item.qty }}</span></td>
                        <td class="num" style="padding:8px 0;font-size:14px;font-weight:600;text-align:right">{{ Math.round(item.line_total) }} KM</td>
                      </tr>
                      <tr v-for="material in details[job.id].data.invoice.materials" :key="'m' + material.name" style="border-bottom:1px solid var(--sand)">
                        <td style="padding:8px 0;font-size:14px;font-weight:400">Materijal: {{ material.name }} <span class="num">× {{ material.qty }}</span></td>
                        <td class="num" style="padding:8px 0;font-size:14px;font-weight:600;text-align:right">{{ Math.round(material.line_total) }} KM</td>
                      </tr>
                      <tr>
                        <td style="padding:8px 0;font-size:14px;font-weight:600">Ukupno sa PDV-om</td>
                        <td class="num" style="padding:8px 0;font-size:14px;font-weight:700;text-align:right">{{ Math.round(details[job.id].data.invoice.total) }} KM</td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-else style="font-size:14px;font-weight:400;color:var(--bark)">Bez računa. Pokriveno izlaskom, kreditom ili garancijom.</p>
                </div>
                <div class="job-detail-photo job-detail-foto1">
                  <img v-if="photoOfType(details[job.id].data.photos, 'prije')" :src="photoOfType(details[job.id].data.photos, 'prije').url" alt="Fotografija prije" style="width:100%;height:100%;object-fit:cover">
                  <span v-else style="font-size:13px;font-weight:400;color:var(--bark);text-align:center;padding:0 16px">Nema fotografije prije</span>
                </div>
                <div class="job-detail-photo job-detail-foto2">
                  <img v-if="photoOfType(details[job.id].data.photos, 'poslije')" :src="photoOfType(details[job.id].data.photos, 'poslije').url" alt="Fotografija poslije" style="width:100%;height:100%;object-fit:cover">
                  <span v-else style="font-size:13px;font-weight:400;color:var(--bark);text-align:center;padding:0 16px">Nema fotografije poslije</span>
                </div>
              </div>
            </div>
          </article>
        </div>
      </template>
    </div>
  </KlijentLayout>
</template>

<style scoped>
.job-head {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 24px;
  padding: 24px 28px;
  align-items: start;
}
.job-head-deadline {
  text-align: right;
}
.job-detail-grid {
  display: grid;
  grid-template-columns: 1fr 240px 240px;
  gap: 1px;
  background: var(--sand);
}
.job-detail-photo {
  background: var(--white);
  height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
@media (max-width: 1024px) {
  .job-detail-grid {
    grid-template-columns: 1fr 1fr;
    grid-template-areas: "nalaz nalaz" "foto1 foto2";
  }
  .job-detail-nalaz { grid-area: nalaz; }
  .job-detail-foto1 { grid-area: foto1; }
  .job-detail-foto2 { grid-area: foto2; }
}
@media (max-width: 640px) {
  .job-head { grid-template-columns: 1fr; gap: 8px; }
  .job-head-deadline { text-align: left; }
}
@media (max-width: 480px) {
  .job-detail-grid {
    grid-template-columns: 1fr;
    grid-template-areas: "nalaz" "foto1" "foto2";
  }
}
</style>
