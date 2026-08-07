<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { fetchSubscriptions } from './api';

const STATUS_OPTIONS = [
  { value: '', label: 'Sve pretplate' },
  { value: 'aktivna', label: 'Aktivna' },
  { value: 'cekanje_uplate', label: 'Čekanje uplate' },
  { value: 'istekla', label: 'Istekla' },
  { value: 'otkazana', label: 'Otkazana' },
  { value: 'ponuda', label: 'Ponuda' },
];

const rows = ref([]);
const status = ref('');
const q = ref('');
const loading = ref(true);
let qDebounce = null;

function daysUntil(dateStr) {
  if (!dateStr) return null;
  const diff = new Date(dateStr).getTime() - Date.now();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

async function load() {
  loading.value = true;
  try {
    const body = await fetchSubscriptions({ status: status.value || undefined, q: q.value || undefined });
    rows.value = body.data;
  } finally {
    loading.value = false;
  }
}

watch(status, load);
watch(q, () => {
  if (qDebounce) window.clearTimeout(qDebounce);
  qDebounce = window.setTimeout(load, 300);
});

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;gap:16px">
        <h1 class="admin-h1">Pretplate</h1>
        <div style="display:flex;gap:12px">
          <select v-model="status" style="border:1px solid var(--ink);background:var(--white);padding:11px 14px;font-size:14px">
            <option v-for="o in STATUS_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <input v-model="q" type="text" placeholder="Pretražite klijenta" style="border:1px solid var(--ink);background:var(--white);padding:11px 14px;font-size:15px;width:260px">
        </div>
      </div>

      <table class="table-haus" style="border:1px solid var(--sand)">
        <thead>
          <tr>
            <th>Klijent</th>
            <th>Paket</th>
            <th>Stanje</th>
            <th class="num" style="text-align:right">Izlasci</th>
            <th class="num" style="text-align:right">Pregledi</th>
            <th>Ističe</th>
            <th>Obnova</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="7" style="color:var(--bark)">Nema pretplata za ovaj filter.</td>
          </tr>
          <tr v-for="(sub, i) in rows" :key="sub.id" :class="{ zebra: i % 2 === 1 }">
            <td>
              <span style="display:block;font-weight:500">{{ sub.client?.name }}</span>
              <span style="display:block;font-size:13px;color:var(--bark)">{{ sub.client?.email }}</span>
            </td>
            <td style="font-weight:500;letter-spacing:.02em">{{ sub.package?.name }}</td>
            <td style="color:var(--bark)">{{ sub.status }}</td>
            <td class="num" style="text-align:right">{{ sub.usage?.visits_remaining }} / {{ sub.usage?.visits_total }}</td>
            <td class="num" style="text-align:right">{{ sub.usage?.inspections_remaining }} / {{ sub.usage?.inspections_total }}</td>
            <td
              class="num"
              :style="{ fontWeight: daysUntil(sub.ends_at) !== null && daysUntil(sub.ends_at) < 60 ? 600 : 400, color: 'var(--ink)' }"
            >{{ sub.ends_at ? new Date(sub.ends_at).toLocaleDateString('bs-BA') : 'Nema' }}</td>
            <td>{{ sub.auto_renew ? 'Automatska' : 'Isključena' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AdminLayout>
</template>
