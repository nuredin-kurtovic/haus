<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../../layouts/AdminLayout.vue';
import StateChip from '../../components/StateChip.vue';
import { fetchDashboard } from './api';

const router = useRouter();
const loading = ref(true);
const dashboard = ref(null);

const KPI_LABELS = {
  novi_danas: 'Novi danas',
  aktivni_nalozi: 'Aktivni nalozi',
  rokovi_danas: 'Rokovi danas',
  prosjek_zavrsetka_h: 'Prosjek završetka',
  aktivne_pretplate: 'Aktivne pretplate',
};

const KPI_NOTES = {
  novi_danas: 'Nalozi primljeni danas',
  aktivni_nalozi: 'Novo, zakazano i u toku',
  rokovi_danas: 'Nezavršeni nalozi kojima rok pada danas',
  prosjek_zavrsetka_h: 'Sati od prijave do završetka, zadnjih 30 dana',
  aktivne_pretplate: 'Pretplate u stanju aktivna',
};

function kpiRows(kpi) {
  if (!kpi) return [];
  return Object.keys(KPI_LABELS).map((key) => ({
    key,
    label: KPI_LABELS[key],
    value: kpi[key] === null || kpi[key] === undefined
      ? (key === 'prosjek_zavrsetka_h' ? 'Nema podataka' : '0')
      : (key === 'prosjek_zavrsetka_h' ? Number(kpi[key]).toFixed(1) : String(kpi[key])),
    note: KPI_NOTES[key],
  }));
}

function scheduleRows(schedule) {
  const rows = [];
  (schedule || []).forEach((block) => {
    (block.jobs || []).forEach((job) => {
      rows.push({
        prozor: block.window,
        broj: job.number,
        majstor: job.technician ? job.technician.name : 'Nije dodijeljen',
        klijent: job.client ? job.client.name : '',
        status: job.status,
      });
    });
  });
  return rows;
}

function openJob(id) {
  router.push({ path: '/admin/zahtjevi', query: { sel: id } });
}

function packageName(pkg) {
  if (!pkg) return '';
  return typeof pkg === 'string' ? pkg : pkg.name;
}

onMounted(async () => {
  dashboard.value = await fetchDashboard();
  loading.value = false;
});
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:28px;gap:24px">
        <h1 class="admin-h1">Pregled</h1>
        <p style="font-size:14px;font-weight:400;color:var(--bark)">Šta se danas dešava i šta gori, ne metrike.</p>
      </div>

      <p v-if="loading" style="font-size:15px;color:var(--bark)">Učitavanje...</p>

      <template v-else-if="dashboard">
        <div class="kpi-grid" style="grid-template-columns:repeat(5,1fr)">
          <div v-for="m in kpiRows(dashboard.kpi)" :key="m.key" class="kpi-cell">
            <span class="kpi-label">{{ m.label }}</span>
            <span class="kpi-value num">{{ m.value }}</span>
            <span class="kpi-note">{{ m.note }}</span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 420px;gap:32px;align-items:start">
          <div>
            <h2 class="detail-section-label">Danas: raspored</h2>
            <table class="table-haus" style="border:1px solid var(--sand);margin-bottom:36px">
              <thead>
                <tr>
                  <th>Prozor</th>
                  <th>Nalog</th>
                  <th>Majstor</th>
                  <th>Klijent</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="scheduleRows(dashboard.schedule_today).length === 0">
                  <td colspan="4" style="color:var(--bark)">Danas nema zakazanih termina.</td>
                </tr>
                <tr
                  v-for="(r, i) in scheduleRows(dashboard.schedule_today)"
                  :key="i"
                  :class="{ zebra: i % 2 === 1 }"
                >
                  <td class="num" style="font-weight:600">{{ r.prozor }}</td>
                  <td class="num">{{ r.broj }}</td>
                  <td>{{ r.majstor }}</td>
                  <td>{{ r.klijent }}</td>
                </tr>
              </tbody>
            </table>

            <h2 class="detail-section-label">Obnove uskoro</h2>
            <table v-if="(dashboard.renewals_soon || []).length > 0" class="table-haus" style="border:1px solid var(--sand)">
              <thead>
                <tr>
                  <th>Klijent</th>
                  <th>Paket</th>
                  <th>Ističe</th>
                  <th>Obnova</th>
                  <th class="num" style="text-align:right">Dana do isteka</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in dashboard.renewals_soon" :key="r.id" :class="{ zebra: i % 2 === 1 }">
                  <td>{{ r.client?.name }}</td>
                  <td style="font-weight:500">{{ packageName(r.package) }}</td>
                  <td class="num">{{ new Date(r.ends_at).toLocaleDateString('bs-BA') }}</td>
                  <td :style="{ fontWeight: r.auto_renew ? 400 : 600, color: r.auto_renew ? 'var(--bark)' : 'var(--ink)' }">
                    {{ r.auto_renew ? 'Automatska' : 'Isključena' }}
                  </td>
                  <td class="num" style="text-align:right;font-weight:600">{{ r.dana_do_isteka }}</td>
                </tr>
              </tbody>
            </table>
            <div v-else class="empty-state">Nema pretplata koje ističu u sljedećih 60 dana.</div>
          </div>

          <aside style="display:flex;flex-direction:column;gap:20px">
            <div class="ember-panel">
              <div class="ember-panel-head">
                <h2 class="ember-panel-head-inner lg">Rokovi danas</h2>
              </div>
              <div class="ember-panel-body">
                <template v-if="(dashboard.deadlines_today || []).length > 0">
                  <ul style="display:flex;flex-direction:column;gap:14px;list-style:none;padding:0;margin:0 0 14px">
                    <li
                      v-for="job in dashboard.deadlines_today"
                      :key="job.id"
                      style="border-bottom:1px solid var(--sand);padding-bottom:12px;cursor:pointer"
                      @click="openJob(job.id)"
                    >
                      <span style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:4px">
                        <span style="font-size:15px;font-weight:600">{{ job.number }}</span>
                        <StateChip :state="job.status" />
                      </span>
                      <span class="num" style="display:block;font-size:14px;font-weight:400;color:var(--bark)">
                        Rok {{ new Date(job.deadline_at).toLocaleString('bs-BA', { hour: '2-digit', minute: '2-digit' }) }}, {{ job.category }}
                      </span>
                    </li>
                  </ul>
                  <p style="font-size:13px;font-weight:400;color:var(--bark);line-height:1.5">Ako rok padne, sljedeća intervencija se upisuje automatski i klijent dobija obavještenje prije nego pita.</p>
                </template>
                <p v-else style="font-size:15px;font-weight:400;line-height:1.5">Nema naloga kojima rok pada danas. To je dobro.</p>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>
