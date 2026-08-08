<script setup>
import { onMounted, ref } from 'vue';
import { useToastStore } from '../../stores/toast';
import AdminLayout from '../../layouts/AdminLayout.vue';
import {
  fetchSettings, updateSettings, fetchSurcharges, updateSurcharge,
  fetchTechnicians, createTechnician, updateTechnician, deleteTechnician, ApiError,
} from './api';

const toast = useToastStore();

const settings = ref(null);
const savingTime = ref(false);
const savingRates = ref(false);
const savingTiers = ref(false);
const savingTemplates = ref(false);

const surcharges = ref([]);
const surchargeSaving = ref({});

const templateAllowedVars = ref({});
const TEMPLATE_LABELS = {
  prijava_primljena: 'Prijava primljena',
  termin_potvrdjen: 'Termin potvrđen',
  majstor_krenuo: 'Majstor krenuo',
  kasnjenje: 'Kašnjenje',
  rok_probijen: 'Rok probijen',
  zavrseno: 'Završeno',
  pretplata_aktivna: 'Pretplata aktivna',
  obnova_podsjetnik: 'Obnova, podsjetnik',
};

const technicians = ref([]);
const newTech = ref({ name: '', trade: '', active: true, email: '', password: '' });
const newTechErrors = ref({});
const techSubmitting = ref(false);

function extractVars(text) {
  const matches = (text || '').match(/\{[a-z_]+\}/g) || [];
  return [...new Set(matches)];
}

function errText(err) {
  return err ? (Array.isArray(err) ? err[0] : err) : '';
}

async function saveErr(fn, label) {
  try {
    await fn();
    toast.show(`${label} su sačuvane.`);
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : `Čuvanje (${label}) nije uspjelo.`);
  }
}

async function saveRadnoVrijeme() {
  savingTime.value = true;
  await saveErr(async () => {
    const body = await updateSettings({ radno_vrijeme: settings.value.radno_vrijeme });
    settings.value = body.data;
  }, 'Postavke radnog vremena');
  savingTime.value = false;
}

async function saveSatnice() {
  savingRates.value = true;
  await saveErr(async () => {
    const body = await updateSettings({
      satnica_redovna: Number(settings.value.satnica_redovna),
      satnica_hitna: Number(settings.value.satnica_hitna),
      izlazak_bez_pretplate: Number(settings.value.izlazak_bez_pretplate),
      ukljuceno_minuta: Number(settings.value.ukljuceno_minuta),
      materijal_marza_pct: Number(settings.value.materijal_marza_pct),
    });
    settings.value = body.data;
  }, 'Satnice i vrijednosti');
  savingRates.value = false;
}

function addTier() {
  settings.value.pro_volume_tiers.push({ min: 0, max: null, pct: 0 });
}
function removeTier(idx) {
  settings.value.pro_volume_tiers.splice(idx, 1);
}
async function saveTiers() {
  savingTiers.value = true;
  await saveErr(async () => {
    const body = await updateSettings({ pro_volume_tiers: settings.value.pro_volume_tiers });
    settings.value = body.data;
  }, 'Pro tiers');
  savingTiers.value = false;
}

async function saveTemplates() {
  savingTemplates.value = true;
  await saveErr(async () => {
    const body = await updateSettings({ notification_templates: settings.value.notification_templates });
    settings.value = body.data;
  }, 'Predlošci obavještenja');
  savingTemplates.value = false;
}

async function onSurchargeChange(s) {
  surchargeSaving.value = { ...surchargeSaving.value, [s.id]: true };
  try {
    await updateSurcharge(s.id, { value: Number(s.value), active: s.active });
    toast.show(`${s.label} je sačuvana.`);
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Čuvanje doplate nije uspjelo.');
  } finally {
    surchargeSaving.value = { ...surchargeSaving.value, [s.id]: false };
  }
}

async function loadTechnicians() {
  technicians.value = await fetchTechnicians();
}

async function submitNewTech() {
  newTechErrors.value = {};
  techSubmitting.value = true;
  try {
    const payload = { name: newTech.value.name, trade: newTech.value.trade, active: newTech.value.active };
    if (newTech.value.email) {
      payload.email = newTech.value.email;
      payload.password = newTech.value.password;
    }
    await createTechnician(payload);
    toast.show('Majstor je upisan.');
    newTech.value = { name: '', trade: '', active: true, email: '', password: '' };
    await loadTechnicians();
  } catch (error) {
    if (error instanceof ApiError) {
      newTechErrors.value = error.errors || {};
      toast.show(error.message);
    } else {
      toast.show('Upisivanje majstora nije uspjelo.');
    }
  } finally {
    techSubmitting.value = false;
  }
}

async function toggleTechActive(tech) {
  try {
    await updateTechnician(tech.id, { active: !tech.active });
    await loadTechnicians();
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Izmjena nije uspjela.');
  }
}

async function removeTech(tech) {
  const ok = window.confirm(`Uklonite majstora ${tech.name}?`);
  if (!ok) return;
  try {
    const body = await deleteTechnician(tech.id);
    toast.show(body?.message || 'Majstor je obrisan.');
    await loadTechnicians();
  } catch (error) {
    toast.show(error instanceof ApiError ? error.message : 'Majstor ima naloge i ne može se obrisati.');
  }
}

onMounted(async () => {
  const [settingsData, surchargeData] = await Promise.all([fetchSettings(), fetchSurcharges()]);
  settings.value = settingsData;
  Object.entries(settingsData.notification_templates || {}).forEach(([key, text]) => {
    templateAllowedVars.value[key] = extractVars(text);
  });
  surcharges.value = surchargeData;
  await loadTechnicians();
});
</script>

<template>
  <AdminLayout>
    <div class="admin-main" style="max-width:1100px">
      <h1 class="admin-h1" style="margin-bottom:32px">Postavke</h1>

      <template v-if="settings">
        <section style="border:1px solid var(--sand);margin-bottom:24px;padding:26px">
          <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Radno vrijeme</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
            <div class="field">
              <label class="field-label">Radni dani, od</label>
              <input v-model="settings.radno_vrijeme.pon_pet.od" type="time">
            </div>
            <div class="field">
              <label class="field-label">Radni dani, do</label>
              <input v-model="settings.radno_vrijeme.pon_pet.do" type="time">
            </div>
            <div></div>
            <div class="field">
              <label class="field-label">Subota, od</label>
              <input v-model="settings.radno_vrijeme.subota.od" type="time">
            </div>
            <div class="field">
              <label class="field-label">Subota, do</label>
              <input v-model="settings.radno_vrijeme.subota.do" type="time">
            </div>
            <div></div>
          </div>
          <label style="display:flex;align-items:center;gap:10px;margin-bottom:16px;font-size:14px">
            <input v-model="settings.radno_vrijeme.nedjelja.samo_hitno" type="checkbox">
            Nedjeljom samo hitne intervencije
          </label>
          <div class="field" style="margin-bottom:16px">
            <label class="field-label">Napomena klijentima</label>
            <input v-model="settings.radno_vrijeme.napomena" type="text">
          </div>
          <button type="button" class="btn btn-ink" :disabled="savingTime" @click="saveRadnoVrijeme">{{ savingTime ? 'Čuvanje...' : 'Sačuvajte radno vrijeme' }}</button>
        </section>

        <section style="border:1px solid var(--sand);margin-bottom:24px;padding:26px">
          <h2 style="font-size:20px;font-weight:700;margin-bottom:16px">Satnice i vrijednosti</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div class="field">
              <label class="field-label">Satnica, redovan rad (KM/h)</label>
              <input v-model="settings.satnica_redovna" type="number" min="0" class="num">
            </div>
            <div class="field">
              <label class="field-label">Satnica, hitno (KM/h)</label>
              <input v-model="settings.satnica_hitna" type="number" min="0" class="num">
            </div>
            <div class="field">
              <label class="field-label">Izlazak bez pretplate (KM)</label>
              <input v-model="settings.izlazak_bez_pretplate" type="number" min="0" class="num">
            </div>
            <div class="field">
              <label class="field-label">Uključeno minuta po izlasku</label>
              <input v-model="settings.ukljuceno_minuta" type="number" min="0" class="num">
            </div>
            <div class="field">
              <label class="field-label">Marža na materijal (%)</label>
              <input v-model="settings.materijal_marza_pct" type="number" min="0" class="num">
            </div>
          </div>
          <button type="button" class="btn btn-ink" :disabled="savingRates" @click="saveSatnice">{{ savingRates ? 'Čuvanje...' : 'Sačuvajte vrijednosti' }}</button>
        </section>

        <section style="border:1px solid var(--sand);margin-bottom:24px">
          <div style="background:var(--ivory);padding:20px 26px;border-bottom:1px solid var(--sand)">
            <h2 style="font-size:20px;font-weight:700">Doplate</h2>
          </div>
          <div class="admin-table-scroll">
            <table class="table-haus">
            <thead>
              <tr><th>Doplata</th><th class="num" style="text-align:right">Vrijednost</th><th>Tip</th><th>Aktivna</th></tr>
            </thead>
            <tbody>
              <tr v-for="(s, i) in surcharges" :key="s.id" :class="{ zebra: i % 2 === 1 }">
                <td>{{ s.label }}</td>
                <td style="text-align:right">
                  <input v-model.number="s.value" type="number" min="0" step="0.01" class="num" style="width:90px;border:1px solid var(--sand);padding:8px;text-align:right" @change="onSurchargeChange(s)">
                </td>
                <td style="color:var(--bark)">{{ s.type === 'percent' ? '%' : (s.type === 'per_km' ? 'po km' : 'fiksno') }}</td>
                <td>
                  <input v-model="s.active" type="checkbox" @change="onSurchargeChange(s)">
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </section>

        <section style="border:1px solid var(--sand);margin-bottom:24px;padding:26px">
          <h2 style="font-size:20px;font-weight:700;margin-bottom:6px">HAUS Pro, popust na broj stanova</h2>
          <p style="font-size:14px;color:var(--bark);margin-bottom:16px">Redovi se čitaju od najmanjeg broja stanova prema najvećem.</p>
          <div v-for="(tier, idx) in settings.pro_volume_tiers" :key="idx" style="display:grid;grid-template-columns:1fr 1fr 1fr 32px;gap:10px;margin-bottom:10px;align-items:center">
            <div class="field"><label class="field-label">Min stanova</label><input v-model.number="tier.min" type="number" min="0" class="num"></div>
            <div class="field"><label class="field-label">Max stanova</label><input v-model.number="tier.max" type="number" min="0" class="num" placeholder="Bez granice"></div>
            <div class="field"><label class="field-label">Popust (%)</label><input v-model.number="tier.pct" type="number" min="0" max="100" class="num"></div>
            <button type="button" aria-label="Uklonite" style="border:1px solid var(--sand);background:transparent;cursor:pointer;height:44px;margin-top:22px" @click="removeTier(idx)">×</button>
          </div>
          <div style="display:flex;gap:10px;margin-top:12px">
            <button type="button" class="btn btn-ghost-ink" @click="addTier">Dodajte red</button>
            <button type="button" class="btn btn-ink" :disabled="savingTiers" @click="saveTiers">{{ savingTiers ? 'Čuvanje...' : 'Sačuvajte tiers' }}</button>
          </div>
        </section>

        <section style="border:1px solid var(--sand);margin-bottom:24px;padding:26px">
          <h2 style="font-size:20px;font-weight:700;margin-bottom:6px">Predlošci obavještenja</h2>
          <p style="font-size:14px;color:var(--bark);margin-bottom:20px">Bosanski jezik, uvijek Vi. Em dash nije dozvoljen, server odbija tekst koji ga sadrži.</p>
          <div v-for="(key) in Object.keys(settings.notification_templates)" :key="key" style="margin-bottom:20px">
            <label class="field-label" style="display:block;margin-bottom:7px">{{ TEMPLATE_LABELS[key] || key }}</label>
            <textarea v-model="settings.notification_templates[key]" rows="2" style="width:100%;border:1px solid var(--ink);background:var(--white);padding:12px 13px;font-size:15px"></textarea>
            <p v-if="templateAllowedVars[key]?.length" style="font-size:12px;color:var(--bark);margin-top:6px" class="num">Dozvoljene varijable: {{ templateAllowedVars[key].join(', ') }}</p>
          </div>
          <button type="button" class="btn btn-ink" :disabled="savingTemplates" @click="saveTemplates">{{ savingTemplates ? 'Čuvanje...' : 'Sačuvajte predloške' }}</button>
        </section>

        <section style="border:1px solid var(--sand);margin-bottom:24px">
          <div style="background:var(--ivory);padding:20px 26px;border-bottom:1px solid var(--sand)">
            <h2 style="font-size:20px;font-weight:700">Majstori</h2>
          </div>
          <div class="admin-table-scroll">
            <table class="table-haus">
            <thead>
              <tr><th>Ime</th><th>Zanat</th><th>Aktivan</th><th>Nalog</th><th class="num" style="text-align:right">Nalozi</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-if="technicians.length === 0"><td colspan="6" style="color:var(--bark)">Nema upisanih majstora.</td></tr>
              <tr v-for="(tech, i) in technicians" :key="tech.id" :class="{ zebra: i % 2 === 1 }">
                <td style="font-weight:500">{{ tech.name }}</td>
                <td style="color:var(--bark)">{{ tech.trade }}</td>
                <td>{{ tech.active ? 'Da' : 'Ne' }}</td>
                <td style="color:var(--bark)">{{ tech.has_account ? tech.email : 'Nema' }}</td>
                <td class="num" style="text-align:right">{{ tech.jobs_count }}</td>
                <td>
                  <div class="row-actions">
                    <button type="button" class="row-action-btn" @click="toggleTechActive(tech)">{{ tech.active ? 'Isključite' : 'Uključite' }}</button>
                    <button type="button" class="row-action-btn muted" @click="removeTech(tech)">Uklonite</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
          <form style="padding:22px;display:grid;grid-template-columns:1fr 1fr;gap:14px;border-top:1px solid var(--sand)" @submit.prevent="submitNewTech">
            <div class="field">
              <label class="field-label">Ime i prezime</label>
              <input v-model="newTech.name" type="text" :class="{ 'field-error': newTechErrors.name }">
              <span v-if="newTechErrors.name" class="field-error-text">{{ errText(newTechErrors.name) }}</span>
            </div>
            <div class="field">
              <label class="field-label">Zanat</label>
              <input v-model="newTech.trade" type="text" :class="{ 'field-error': newTechErrors.trade }">
              <span v-if="newTechErrors.trade" class="field-error-text">{{ errText(newTechErrors.trade) }}</span>
            </div>
            <div class="field">
              <label class="field-label">Mejl za nalog (opciono)</label>
              <input v-model="newTech.email" type="email" :class="{ 'field-error': newTechErrors.email }">
              <span v-if="newTechErrors.email" class="field-error-text">{{ errText(newTechErrors.email) }}</span>
            </div>
            <div class="field">
              <label class="field-label">Lozinka (uz mejl)</label>
              <input v-model="newTech.password" type="password" :class="{ 'field-error': newTechErrors.password }">
              <span v-if="newTechErrors.password" class="field-error-text">{{ errText(newTechErrors.password) }}</span>
            </div>
            <label style="display:flex;align-items:center;gap:10px;font-size:14px;grid-column:1 / -1">
              <input v-model="newTech.active" type="checkbox">
              Aktivan
            </label>
            <button type="submit" class="btn btn-ember" style="grid-column:1 / -1" :disabled="techSubmitting">{{ techSubmitting ? 'Upisivanje...' : 'Dodajte majstora' }}</button>
          </form>
        </section>
      </template>
    </div>
  </AdminLayout>
</template>
