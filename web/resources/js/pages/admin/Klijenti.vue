<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { fetchClients } from './api';

const router = useRouter();
const clients = ref([]);
const meta = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 });
const q = ref('');
const page = ref(1);
const loading = ref(true);
let qDebounce = null;

async function load() {
  loading.value = true;
  try {
    const body = await fetchClients({ q: q.value || undefined, per_page: 20, page: page.value });
    clients.value = body.data;
    meta.value = body.meta;
  } finally {
    loading.value = false;
  }
}

function openClient(id) {
  router.push(`/admin/klijenti/${id}`);
}

watch(q, () => {
  page.value = 1;
  if (qDebounce) window.clearTimeout(qDebounce);
  qDebounce = window.setTimeout(load, 300);
});
watch(page, load);

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:24px;gap:16px">
        <h1 class="admin-h1">Klijenti</h1>
        <input
          v-model="q"
          type="text"
          placeholder="Pretražite po imenu ili mejlu"
          style="border:1px solid var(--ink);background:var(--white);padding:11px 14px;font-size:15px;width:320px"
        >
      </div>

      <div class="admin-table-scroll">
        <table class="table-haus" style="border:1px solid var(--sand)">
        <thead>
          <tr>
            <th>Klijent</th>
            <th>Paket</th>
            <th>Status pretplate</th>
            <th class="num" style="text-align:right">Adrese</th>
            <th class="num" style="text-align:right">Nalozi</th>
            <th class="num" style="text-align:right">Aktivni nalozi</th>
            <th>Ističe</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && clients.length === 0">
            <td colspan="8" style="color:var(--bark)">Nema klijenata za ovu pretragu.</td>
          </tr>
          <tr v-for="(c, i) in clients" :key="c.id" :class="{ zebra: i % 2 === 1 }">
            <td>
              <span style="display:block;font-weight:500">{{ c.name }}</span>
              <span style="display:block;font-size:13px;color:var(--bark)">{{ c.email }}</span>
            </td>
            <td style="font-weight:500;letter-spacing:.02em">{{ c.package || 'Bez pretplate' }}</td>
            <td style="color:var(--bark)">{{ c.subscription_status || 'Nema' }}</td>
            <td class="num" style="text-align:right">{{ c.properties_count }}</td>
            <td class="num" style="text-align:right">{{ c.jobs_count }}</td>
            <td class="num" style="text-align:right">{{ c.active_jobs_count }}</td>
            <td class="num">{{ c.ends_at ? new Date(c.ends_at).toLocaleDateString('bs-BA') : 'Nema' }}</td>
            <td>
              <button type="button" class="row-action-btn" @click="openClient(c.id)">Otvorite</button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <div v-if="meta.last_page > 1" style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <button type="button" class="row-action-btn muted" :disabled="page <= 1" @click="page -= 1">Prethodna</button>
        <span class="num" style="font-size:14px;color:var(--bark)">Stranica {{ meta.current_page }} od {{ meta.last_page }}</span>
        <button type="button" class="row-action-btn muted" :disabled="page >= meta.last_page" @click="page += 1">Sljedeća</button>
      </div>
    </div>
  </AdminLayout>
</template>
