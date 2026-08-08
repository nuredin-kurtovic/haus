<script setup>
import { computed, onMounted, ref } from 'vue';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import StateChip from '../../components/StateChip.vue';
import AppPromo from '../../components/AppPromo.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchDashboard, fetchSubscription } from './api';
import { formatDate, formatDateTime, jobChipState } from './format';
import { sati } from '../../utils/format';

const auth = useAuthStore();

const loading = ref(true);
const error = ref('');
const dashboard = ref(null);
const subscription = ref(null);

async function load() {
  error.value = '';
  try {
    const [dash, sub] = await Promise.all([
      fetchDashboard(),
      fetchSubscription().catch(() => null),
    ]);
    dashboard.value = dash;
    subscription.value = sub;
  } catch (e) {
    error.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}

onMounted(load);

const prvoIme = computed(() => (auth.user?.name || '').split(' ')[0] || '');

const sub = computed(() => dashboard.value?.subscription || null);
const pkg = computed(() => subscription.value?.package || null);

const istekLinija = computed(() => {
  if (!sub.value) return 'Nemate pretplatu.';
  if (sub.value.status === 'aktivna' && sub.value.ends_at) return `Pretplata aktivna do ${formatDate(sub.value.ends_at)}`;
  if (sub.value.status === 'cekanje_uplate') return 'Pretplata čeka uplatu.';
  if (sub.value.status === 'ponuda') return 'Zahtjev za ponudu je u obradi.';
  if (sub.value.status === 'istekla') return 'Pretplata je istekla.';
  if (sub.value.status === 'otkazana') return 'Pretplata je otkazana.';
  return '';
});

const statusKartice = computed(() => {
  if (!sub.value) return [];
  return [
    { k: 'Paket', v: sub.value.package.name, d: pkg.value ? `${pkg.value.labor_discount_pct}% popusta na rad` : '', num: false },
    { k: 'Izlasci preostalo', v: String(sub.value.remaining_visits), d: pkg.value ? `Od ${pkg.value.visits_per_year} godišnje${pkg.value.is_per_apartment ? ' po stanu' : ''}.` : '', num: true },
    { k: 'Besplatne intervencije', v: String(sub.value.free_interventions), d: 'Upisuju se automatski kad rok padne.', num: true },
    { k: 'Rok paketa', v: sub.value.ends_at ? formatDate(sub.value.ends_at) : 'Nema', d: subscription.value ? (subscription.value.auto_renew ? 'Automatska obnova je uključena.' : 'Automatska obnova je isključena.') : '', num: true },
  ];
});

const ctaTekst = computed(() => {
  if (!pkg.value) return 'Prijavite kvar i dobijate termin u prozoru od dva sata.';
  const rok = `Vaš rok je ${sati(pkg.value.deadline_hours)}.`;
  const hitno = pkg.value.emergency_included
    ? `Za poplavu, struju i plin rok je ${sati(pkg.value.emergency_deadline_hours)}, bez doplate.`
    : `Hitne slučajeve rješavamo u ${sati(pkg.value.emergency_deadline_hours)}, uz doplatu.`;
  return `Prijavite kvar i dobijate termin u prozoru od dva sata. ${rok} ${hitno}`;
});

const pregledTekst = computed(() => {
  if (!pkg.value) return '';
  if (pkg.value.inspections_per_year === 0) return 'Nije uključen u vaš paket.';
  const preostalo = sub.value?.remaining_inspections ?? 0;
  if (preostalo > 0) return `Preostalo ${preostalo} od ${pkg.value.inspections_per_year} ove godine.`;
  return 'Iskorišten za ovu godinu.';
});

const kartonStavke = computed(() => (dashboard.value?.recent_jobs || []).slice(0, 5));
</script>

<template>
  <KlijentLayout>
    <div style="width:1320px;max-width:100%;margin:0 auto;padding:56px 28px 96px">
      <div v-if="!dashboard && loading" style="padding:80px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="!dashboard && error" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ error }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <template v-else>
        <p v-if="error" class="error-panel" style="margin-bottom:24px">{{ error }} Prikazujemo zadnje učitane podatke.</p>

        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:36px;gap:24px;flex-wrap:wrap">
          <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em">Dobar dan{{ prvoIme ? ', ' + prvoIme : '' }}.</h1>
          <p class="num" style="font-size:13px;font-weight:400;color:var(--bark)">{{ istekLinija }}</p>
        </div>

        <div v-if="sub" class="hairline-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:32px">
          <div v-for="k in statusKartice" :key="k.k" style="background:var(--white);padding:26px 24px">
            <span style="display:block;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--bark);margin-bottom:12px">{{ k.k }}</span>
            <span :class="{ num: k.num }" style="display:block;font-size:34px;font-weight:700;line-height:1.05;margin-bottom:6px">{{ k.v }}</span>
            <span style="display:block;font-size:14px;font-weight:400;color:var(--bark);line-height:1.45">{{ k.d }}</span>
          </div>
        </div>
        <div v-else style="border:1px solid var(--sand);background:var(--ivory);padding:32px;margin-bottom:32px">
          <h2 style="font-size:20px;font-weight:600;margin-bottom:10px">Nemate pretplatu</h2>
          <p style="font-size:15px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:16px">Da biste prijavili kvar i vidjeli HAUS Karton, prvo je potrebna aktivna pretplata.</p>
          <RouterLink to="/cjenovnik" class="btn btn-ember">Pogledajte pakete</RouterLink>
        </div>

        <div style="display:grid;grid-template-columns:1fr 400px;gap:32px;align-items:start">
          <div>
            <div v-if="dashboard.active_job" style="border:1px solid var(--ink);padding:26px;margin-bottom:32px">
              <div style="display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:6px;flex-wrap:wrap">
                <span style="font-size:20px;font-weight:700">Nalog u toku</span>
                <StateChip :state="jobChipState(dashboard.active_job)" />
              </div>
              <p class="num" style="font-size:14px;font-weight:400;color:var(--bark);margin-bottom:18px">{{ dashboard.active_job.number }} · {{ dashboard.active_job.category }} · Rok {{ formatDateTime(dashboard.active_job.deadline_at) }}</p>
              <ul style="display:flex;flex-direction:column;gap:10px">
                <li v-for="step in dashboard.active_job.steps" :key="step.key" style="display:flex;align-items:center;gap:12px;font-size:15px;font-weight:400">
                  <span :style="{ width: '12px', height: '12px', flex: 'none', background: step.done ? 'var(--ember)' : 'var(--white)', border: step.done ? '0' : '1px solid var(--sand)' }"></span>
                  <span :style="{ fontWeight: step.done ? 500 : 400, color: step.done ? 'var(--ink)' : 'var(--bark)' }">{{ step.label }}</span>
                </li>
              </ul>
            </div>

            <div v-if="sub" style="background:var(--ember);padding:2px;margin-bottom:32px">
              <div style="background:var(--ivory);padding:32px;display:grid;grid-template-columns:1fr auto;gap:32px;align-items:center">
                <div>
                  <h2 style="font-size:28px;font-weight:700;margin-bottom:8px">Nešto se pokvarilo?</h2>
                  <p style="font-size:16px;font-weight:400;line-height:1.55;color:var(--bark);max-width:520px">{{ ctaTekst }}</p>
                </div>
                <RouterLink to="/klijent/prijavi-kvar" class="btn btn-ember" style="padding:17px 26px;font-size:16px">Prijavite kvar</RouterLink>
              </div>
            </div>

            <AppPromo message="Brže je u aplikaciji." style="margin-bottom:32px" />

            <h2 style="font-size:15px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--bark);margin-bottom:16px">Zadnje intervencije</h2>
            <div v-if="(dashboard.recent_jobs || []).length" style="overflow-x:auto">
              <table class="table-haus" style="border:1px solid var(--sand)">
                <thead>
                  <tr><th>Nalog</th><th>Šta</th><th>Prijavljeno</th><th>Stanje</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(job, i) in dashboard.recent_jobs" :key="job.id" :class="{ zebra: i % 2 === 1 }">
                    <td class="num" style="font-weight:500">{{ job.number }}</td>
                    <td>{{ job.category }}</td>
                    <td class="num" style="color:var(--bark)">{{ formatDate(job.created_at) }}</td>
                    <td><StateChip :state="jobChipState(job)" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else style="font-size:15px;font-weight:400;color:var(--bark);border:1px solid var(--sand);background:var(--ivory);padding:24px">Nemate prijavljenih intervencija. Kad nešto zatreba, prijavite kvar iznad.</p>
            <RouterLink to="/klijent/intervencije" class="btn btn-ghost-ink" style="margin-top:16px;padding:13px 20px;font-size:15px">Sve intervencije</RouterLink>
          </div>

          <aside style="display:flex;flex-direction:column;gap:24px">
            <div style="border:1px solid var(--ink);padding:26px">
              <h2 style="font-size:20px;font-weight:700;margin-bottom:10px">HAUS Karton</h2>
              <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:20px">Historija vašeg stana. Šta je ugrađeno, kad je zadnji put rađeno, šta dolazi na red.</p>
              <ul v-if="kartonStavke.length" style="display:flex;flex-direction:column;gap:12px;border-top:1px solid var(--sand);padding-top:16px">
                <li v-for="job in kartonStavke" :key="job.id">
                  <RouterLink
                    :to="{ path: '/klijent/intervencije', query: { nalog: job.id } }"
                    style="display:grid;grid-template-columns:1fr auto;gap:14px;font-size:14px;font-weight:400;line-height:1.4;text-decoration:none"
                  ><span>{{ job.category }}</span><span class="num" style="color:var(--bark);white-space:nowrap">{{ formatDate(job.created_at) }}</span></RouterLink>
                </li>
              </ul>
              <p v-else style="font-size:14px;font-weight:400;color:var(--bark);border-top:1px solid var(--sand);padding-top:16px">Karton se puni kad prijavite prvi kvar.</p>
            </div>
            <div v-if="sub" style="border:1px solid var(--sand);background:var(--ivory);padding:26px">
              <h2 style="font-size:20px;font-weight:700;margin-bottom:10px">Godišnji pregled</h2>
              <p style="font-size:15px;font-weight:400;line-height:1.55;color:var(--bark)">{{ pregledTekst }}</p>
            </div>
          </aside>
        </div>
      </template>
    </div>
  </KlijentLayout>
</template>
