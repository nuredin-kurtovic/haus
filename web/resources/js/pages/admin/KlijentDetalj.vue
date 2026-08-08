<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../layouts/AdminLayout.vue';
import StateChip from '../../components/StateChip.vue';
import { fetchClientDetail } from './api';

const route = useRoute();
const router = useRouter();
const detail = ref(null);
const loading = ref(true);

function goJob(id) {
  router.push({ path: '/admin/zahtjevi', query: { sel: id } });
}

async function load() {
  loading.value = true;
  detail.value = await fetchClientDetail(route.params.id);
  loading.value = false;
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="admin-main">
      <RouterLink to="/admin/klijenti" style="display:inline-block;margin-bottom:20px;font-size:14px">Nazad na klijente</RouterLink>

      <p v-if="loading">Učitavanje...</p>

      <template v-else-if="detail">
        <div style="margin-bottom:32px">
          <h1 class="admin-h1" style="margin-bottom:6px">{{ detail.client.name }}</h1>
          <p style="font-size:15px;color:var(--bark)">{{ detail.client.email }} · Klijent od {{ new Date(detail.client.created_at).toLocaleDateString('bs-BA') }}</p>
        </div>

        <h2 class="detail-section-label">Pretplate</h2>
        <div v-if="detail.subscriptions.length" class="hairline-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:32px">
          <div v-for="sub in detail.subscriptions" :key="sub.id" style="background:var(--white);padding:22px">
            <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px;gap:10px">
              <span style="font-size:18px;font-weight:700">{{ sub.package }}</span>
              <StateChip :state="sub.status === 'aktivna' ? 'zavrseno' : 'novo'" :label="sub.status" />
            </div>
            <dl style="margin:0">
              <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><dt>Počinje</dt><dd class="num">{{ sub.starts_at ? new Date(sub.starts_at).toLocaleDateString('bs-BA') : 'Nije aktivirano' }}</dd></div>
              <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><dt>Ističe</dt><dd class="num">{{ sub.ends_at ? new Date(sub.ends_at).toLocaleDateString('bs-BA') : 'Nema' }}</dd></div>
              <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><dt>Obnova</dt><dd>{{ sub.auto_renew ? 'Automatska' : 'Isključena' }}</dd></div>
              <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:14px"><dt>Plaćeno</dt><dd class="num">{{ sub.price_paid ?? 'Nema' }} KM</dd></div>
            </dl>
          </div>
        </div>
        <div v-else class="empty-state" style="margin-bottom:32px">Klijent nema upisanu pretplatu.</div>

        <h2 class="detail-section-label">Adrese</h2>
        <div class="admin-table-scroll">
          <table class="table-haus" style="border:1px solid var(--sand);margin-bottom:32px">
          <thead><tr><th>Grad</th><th>Ulica</th><th>Korištenje</th><th class="num" style="text-align:right">Preostali izlasci</th><th class="num" style="text-align:right">Preostali pregledi</th></tr></thead>
          <tbody>
            <tr v-if="detail.properties.length === 0"><td colspan="5" style="color:var(--bark)">Nema upisanih adresa.</td></tr>
            <tr v-for="(p, i) in detail.properties" :key="p.id" :class="{ zebra: i % 2 === 1 }">
              <td>{{ p.city }}</td>
              <td>{{ p.street }}</td>
              <td style="color:var(--bark)">{{ p.use }}</td>
              <td class="num" style="text-align:right">{{ p.remaining_visits }}</td>
              <td class="num" style="text-align:right">{{ p.remaining_inspections }}</td>
            </tr>
          </tbody>
        </table>
        </div>

        <h2 class="detail-section-label">Nalozi</h2>
        <div class="admin-table-scroll">
          <table class="table-haus" style="border:1px solid var(--sand);margin-bottom:32px">
          <thead><tr><th>Broj</th><th>Kategorija</th><th>Stanje</th><th>Majstor</th><th>Prijavljen</th></tr></thead>
          <tbody>
            <tr v-if="detail.jobs.length === 0"><td colspan="5" style="color:var(--bark)">Klijent nema naloge.</td></tr>
            <tr v-for="(job, i) in detail.jobs" :key="job.id" :class="{ zebra: i % 2 === 1 }" style="cursor:pointer" @click="goJob(job.id)">
              <td class="num" style="font-weight:600">{{ job.number }}</td>
              <td>{{ job.category }}</td>
              <td><StateChip :state="job.status" /></td>
              <td>{{ job.technician?.name || 'Nije dodijeljen' }}</td>
              <td class="num">{{ new Date(job.created_at).toLocaleDateString('bs-BA') }}</td>
            </tr>
          </tbody>
        </table>
        </div>

        <h2 class="detail-section-label">Fakture</h2>
        <div class="admin-table-scroll">
          <table class="table-haus" style="border:1px solid var(--sand);margin-bottom:32px">
          <thead><tr><th>Broj</th><th>Tip</th><th>Stanje</th><th class="num" style="text-align:right">Ukupno</th><th>Plaćeno</th></tr></thead>
          <tbody>
            <tr v-if="detail.invoices.length === 0"><td colspan="5" style="color:var(--bark)">Nema faktura.</td></tr>
            <tr v-for="(inv, i) in detail.invoices" :key="inv.id" :class="{ zebra: i % 2 === 1 }">
              <td class="num" style="font-weight:600">{{ inv.number }}</td>
              <td style="color:var(--bark)">{{ inv.type }}</td>
              <td>{{ inv.status }}</td>
              <td class="num" style="text-align:right;font-weight:700">{{ inv.total }} KM</td>
              <td class="num">{{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString('bs-BA') : 'Nije plaćeno' }}</td>
            </tr>
          </tbody>
        </table>
        </div>

        <h2 class="detail-section-label">HAUS Karton</h2>
        <div v-if="detail.home_records.length === 0" class="empty-state">Karton doma je prazan. Popunjava se automatski nakon svake intervencije.</div>
        <ul v-else style="display:flex;flex-direction:column;gap:1px;background:var(--sand);border:1px solid var(--sand);list-style:none;padding:0;margin:0">
          <li v-for="rec in detail.home_records" :key="rec.id" style="background:var(--white);padding:16px 18px">
            <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px">
              <span style="font-weight:600;font-size:15px">{{ rec.title }}</span>
              <span class="num" style="font-size:13px;color:var(--bark)">{{ new Date(rec.recorded_at).toLocaleDateString('bs-BA') }}</span>
            </div>
            <p style="font-size:14px;color:var(--bark);line-height:1.5;margin-bottom:4px">{{ rec.body }}</p>
            <p v-if="rec.job_number" class="num" style="font-size:12px;color:var(--bark)">{{ rec.job_number }} · {{ rec.street }}</p>
          </li>
        </ul>
      </template>
    </div>
  </AdminLayout>
</template>
