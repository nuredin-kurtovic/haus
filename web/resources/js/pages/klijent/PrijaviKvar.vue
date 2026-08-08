<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import { createJob, fetchSubscription, ApiError } from './api';
import { fetchPriceList, fetchSurcharges } from '../../api/catalog';
import { formatDateTime } from './format';
import { sati } from '../../utils/format';

const NEZNA_PREFIX = 'Ne znam kako se zove:';

const loading = ref(true);
const loadError = ref('');
const subscription = ref(null);
const categories = ref([]);
const surcharges = ref([]);

async function load() {
  loadError.value = '';
  try {
    const [sub, cats, sur] = await Promise.all([
      fetchSubscription().catch(() => null),
      fetchPriceList(),
      fetchSurcharges(),
    ]);
    subscription.value = sub;
    categories.value = cats;
    surcharges.value = sur;
  } catch (e) {
    loadError.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}
onMounted(load);

const online = ref(navigator.onLine);
function setOnline() { online.value = true; }
function setOffline() { online.value = false; }
onMounted(() => {
  window.addEventListener('online', setOnline);
  window.addEventListener('offline', setOffline);
});
onBeforeUnmount(() => {
  window.removeEventListener('online', setOnline);
  window.removeEventListener('offline', setOffline);
  if (photoUrl.value) URL.revokeObjectURL(photoUrl.value);
});

const pkg = computed(() => subscription.value?.package || null);
const properties = computed(() => subscription.value?.properties || []);
const needsAddressChoice = computed(() => Boolean(pkg.value?.is_per_apartment) && properties.value.length > 1);

const sitniPoslovi = computed(() => categories.value.find((c) => c.slug === 'sitni-poslovi') || null);
const katOpcije = computed(() => [
  ...categories.value.map((c) => ({ id: c.id, label: c.name })),
  { id: 'nezna', label: 'Ne znam kako se zove' },
]);

const terminOpcije = [
  { value: 'prijepodne 08-12', label: 'Prijepodne, 08:00–12:00' },
  { value: 'poslijepodne 12-16', label: 'Poslijepodne, 12:00–16:00' },
  { value: 'kasno 16-18', label: 'Kasno, 16:00–18:00' },
  { value: 'svejedno', label: 'Svejedno' },
];

const step = ref(1);
const submitting = ref(false);
const submitError = ref('');
const forbidden = ref(null);
const done = ref(false);
const result = ref(null);

const form = reactive({
  subscription_property_id: '',
  price_category_id: null,
  nezna: false,
  description: '',
  photo: null,
  is_emergency: false,
  preferred_window: '',
  pristup: '',
});

const selectedTileId = computed(() => (form.nezna ? 'nezna' : form.price_category_id));

function pickCategory(opt) {
  if (opt.id === 'nezna') {
    form.nezna = true;
    form.price_category_id = sitniPoslovi.value?.id || null;
  } else {
    form.nezna = false;
    form.price_category_id = opt.id;
  }
}

const emergencyPct = computed(() => {
  const s = surcharges.value.find((x) => x.key === 'hitno_radni_dan');
  return s ? Math.round(s.value) : null;
});

const hitnoNota = computed(() => {
  if (!pkg.value) return '';
  if (pkg.value.emergency_included) {
    return `Vaš hitni rok je ${sati(pkg.value.emergency_deadline_hours)}, bez doplate. Ako možete, zatvorite ventil ili isključite osigurač prije nego majstor stigne.`;
  }
  const doplata = emergencyPct.value !== null ? ` uz doplatu od ${emergencyPct.value}% na rad` : ' uz doplatu';
  return `Vaš hitni rok je ${sati(pkg.value.emergency_deadline_hours)}. Vaš paket ne uključuje hitno bez doplate: hitna intervencija ide${doplata}.`;
});

const step1Blocked = computed(() => !form.price_category_id || (needsAddressChoice.value && !form.subscription_property_id));

const step2Touched = ref(false);
const step2Error = computed(() => {
  if (!step2Touched.value) return '';
  if (form.description.trim().length < 10) return 'Opišite kvar sa najmanje 10 znakova.';
  return '';
});
function goStep3() {
  step2Touched.value = true;
  if (form.description.trim().length < 10) return;
  step.value = 3;
}

const step3Blocked = computed(() => !form.preferred_window);

const photoUrl = ref('');
function onPhotoChange(e) {
  const file = e.target.files?.[0] || null;
  form.photo = file;
  if (photoUrl.value) URL.revokeObjectURL(photoUrl.value);
  photoUrl.value = file ? URL.createObjectURL(file) : '';
}

const rokTekst = computed(() => (form.is_emergency && pkg.value ? `Vaš hitni rok je ${sati(pkg.value.emergency_deadline_hours)}.` : pkg.value ? `Vaš rok je ${sati(pkg.value.deadline_hours)}.` : ''));

const forbiddenCta = computed(() => {
  const s = forbidden.value?.subscriptionStatus;
  if (s === 'istekla' || s === 'otkazana') return { to: '/klijent/pretplata', label: 'Idite na pretplatu' };
  if (s === 'cekanje_uplate' || s === 'ponuda') return { to: '/klijent/pretplata', label: 'Provjerite stanje pretplate' };
  return { to: '/cjenovnik', label: 'Pogledajte pakete' };
});

async function submit() {
  submitError.value = '';
  forbidden.value = null;
  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append('price_category_id', String(form.price_category_id));

    let description = form.description.trim();
    if (form.nezna) description = `${NEZNA_PREFIX} ${description}`;
    if (form.pristup.trim()) description = `${description} Pristup: ${form.pristup.trim()}.`;
    fd.append('description', description);

    fd.append('is_emergency', form.is_emergency ? '1' : '0');
    if (form.preferred_window) fd.append('preferred_window', form.preferred_window);
    if (needsAddressChoice.value && form.subscription_property_id) {
      fd.append('subscription_property_id', String(form.subscription_property_id));
    }
    if (form.photo) fd.append('photo', form.photo);

    const body = await createJob(fd);
    result.value = body.job;
    done.value = true;
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      forbidden.value = { message: e.message, subscriptionStatus: e.subscriptionStatus };
    } else if (e instanceof ApiError) {
      submitError.value = e.message;
      if (e.errors?.subscription_property_id) step.value = 1;
    } else {
      submitError.value = 'Prijava nije uspjela. Provjerite internet konekciju.';
    }
  } finally {
    submitting.value = false;
  }
}

function resetForm() {
  step.value = 1;
  done.value = false;
  result.value = null;
  forbidden.value = null;
  submitError.value = '';
  step2Touched.value = false;
  form.subscription_property_id = '';
  form.price_category_id = null;
  form.nezna = false;
  form.description = '';
  form.photo = null;
  form.is_emergency = false;
  form.preferred_window = '';
  form.pristup = '';
  if (photoUrl.value) URL.revokeObjectURL(photoUrl.value);
  photoUrl.value = '';
}
</script>

<template>
  <KlijentLayout>
    <div style="width:1320px;max-width:100%;margin:0 auto;padding:56px 28px 96px">
      <div v-if="loading" style="padding:80px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="loadError" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ loadError }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <div v-else-if="forbidden" style="max-width:640px">
        <h1 style="font-size:44px;font-weight:700;line-height:1.08;letter-spacing:-0.015em;margin-bottom:20px">Prijava kvara nije moguća.</h1>
        <p class="error-panel" style="margin-bottom:28px">{{ forbidden.message }}</p>
        <div style="display:flex;gap:12px">
          <RouterLink :to="forbiddenCta.to" class="btn btn-ember">{{ forbiddenCta.label }}</RouterLink>
          <RouterLink to="/klijent" class="btn btn-ghost-ink">Na Početnu</RouterLink>
        </div>
      </div>

      <div v-else-if="done && result" style="max-width:760px">
        <h1 style="font-size:44px;font-weight:700;line-height:1.08;letter-spacing:-0.015em;margin-bottom:16px">Primljeno.</h1>
        <p style="font-size:19px;font-weight:400;line-height:1.55;margin-bottom:36px">
          Nalog <strong class="num" style="font-weight:600">{{ result.number }}</strong> je otvoren. Rok je <strong class="num" style="font-weight:600">{{ formatDateTime(result.deadline_at) }}</strong>. Dispečer vam u aplikaciji potvrđuje termin u prozoru od dva sata.
        </p>
        <div style="display:flex;gap:12px">
          <RouterLink to="/klijent" class="btn btn-ember">Na Početnu</RouterLink>
          <button type="button" class="btn btn-ghost-ink" @click="resetForm">Prijavite još jedan kvar</button>
        </div>
      </div>

      <div v-else style="max-width:820px">
        <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em;margin-bottom:32px">Prijavi kvar</h1>

        <nav style="display:flex;gap:2px;margin-bottom:44px" aria-label="Koraci">
          <div
            v-for="(t, i) in ['Šta se pokvarilo', 'Opis i fotografija', 'Termin']"
            :key="t"
            style="flex:1;padding-top:12px"
            :style="{ borderTop: `3px solid ${step === i + 1 ? 'var(--ember)' : step > i + 1 ? 'var(--ink)' : 'var(--sand)'}` }"
          >
            <span class="num" style="display:block;font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--bark)">Korak {{ i + 1 }}</span>
            <span style="display:block;font-size:15px" :style="{ fontWeight: step === i + 1 ? 600 : 400, color: step === i + 1 ? 'var(--ink)' : 'var(--bark)' }">{{ t }}</span>
          </div>
        </nav>

        <!-- KORAK 1 -->
        <div v-if="step === 1">
          <div v-if="needsAddressChoice" style="margin-bottom:32px">
            <h2 style="font-size:26px;font-weight:600;margin-bottom:8px">Za koju adresu prijavljujete?</h2>
            <p style="font-size:16px;font-weight:400;color:var(--bark);margin-bottom:20px">Vaša pretplata pokriva više adresa.</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:8px">
              <label
                v-for="p in properties"
                :key="p.id"
                style="display:block;cursor:pointer;padding:16px 18px"
                :style="{ border: `1px solid ${String(form.subscription_property_id) === String(p.id) ? 'var(--ink)' : 'var(--sand)'}`, background: String(form.subscription_property_id) === String(p.id) ? 'var(--ivory)' : 'var(--white)' }"
              >
                <input type="radio" name="adresa" :value="p.id" v-model="form.subscription_property_id" style="width:16px;height:16px;accent-color:var(--ember);margin:0 10px 0 0">
                <span style="font-size:15px;font-weight:500">{{ p.street }}, {{ p.city }}</span>
              </label>
            </div>
          </div>

          <h2 style="font-size:26px;font-weight:600;margin-bottom:8px">Šta se pokvarilo?</h2>
          <p style="font-size:16px;font-weight:400;color:var(--bark);margin-bottom:24px">Ako ne znate kako se to zove, zadnja stavka je za to.</p>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:36px">
            <button
              v-for="opt in katOpcije"
              :key="opt.id"
              type="button"
              style="padding:20px 18px;font-size:16px;text-align:left;cursor:pointer"
              :style="{
                border: `1px solid ${selectedTileId === opt.id ? 'var(--ink)' : 'var(--sand)'}`,
                background: selectedTileId === opt.id ? 'var(--ivory)' : 'var(--white)',
                color: 'var(--ink)',
                fontWeight: selectedTileId === opt.id ? 600 : 400,
              }"
              @click="pickCategory(opt)"
            >{{ opt.label }}</button>
          </div>
          <button type="button" class="btn" :class="step1Blocked ? '' : 'btn-ember'" :disabled="step1Blocked" :style="step1Blocked ? { background: 'var(--sand)', color: 'var(--ink)' } : {}" @click="step = 2">Dalje</button>
        </div>

        <!-- KORAK 2 -->
        <div v-else-if="step === 2">
          <h2 style="font-size:26px;font-weight:600;margin-bottom:8px">Opišite šta se dešava</h2>
          <p style="font-size:16px;font-weight:400;color:var(--bark);margin-bottom:24px">
            Kratko je dovoljno. Dispečer se javlja u aplikaciji ako mu nešto nije jasno.
            <template v-if="form.nezna"> Prijava ide pod „Sitni poslovi", slobodno opišite svojim riječima.</template>
          </p>
          <div class="field" style="margin-bottom:24px">
            <label for="rk-opis" class="field-label">Opis kvara</label>
            <textarea id="rk-opis" v-model="form.description" rows="4" placeholder="npr. Curi ispod sudopere, kaplje na pod." :class="{ 'field-error': step2Error }" @input="step2Touched = false"></textarea>
            <p v-if="step2Error" class="field-error-text">{{ step2Error }}</p>
          </div>
          <div style="border:1px solid var(--sand);background:var(--ivory);padding:20px;margin-bottom:24px">
            <span class="field-label" style="display:block;margin-bottom:12px">Fotografija (nije obavezna)</span>
            <div v-if="photoUrl" style="margin-bottom:12px">
              <img :src="photoUrl" alt="Pregled fotografije kvara" style="max-height:220px;border:1px solid var(--sand)">
            </div>
            <input type="file" accept="image/*" @change="onPhotoChange">
          </div>
          <label
            style="display:grid;grid-template-columns:22px 1fr;gap:12px;align-items:start;cursor:pointer;padding:18px 20px;margin-bottom:12px"
            :style="{ border: `${form.is_emergency ? '3px' : '1px'} solid ${form.is_emergency ? 'var(--ember)' : 'var(--sand)'}`, background: form.is_emergency ? 'var(--ivory)' : 'var(--white)' }"
          >
            <input type="checkbox" v-model="form.is_emergency" style="width:18px;height:18px;accent-color:var(--ember);margin:2px 0 0">
            <span>
              <span style="display:block;font-size:16px;font-weight:600;margin-bottom:3px">Ovo je hitno: poplava, nema struje, plin</span>
              <span style="display:block;font-size:14px;font-weight:400;color:var(--bark);line-height:1.5">{{ hitnoNota }}</span>
            </span>
          </label>
          <div style="display:flex;gap:12px;margin-top:24px">
            <button type="button" class="btn btn-ember" @click="goStep3">Dalje</button>
            <button type="button" class="btn btn-ghost-ink" @click="step = 1">Nazad</button>
          </div>
        </div>

        <!-- KORAK 3 -->
        <div v-else>
          <h2 style="font-size:26px;font-weight:600;margin-bottom:8px">Kad vam odgovara?</h2>
          <p style="font-size:16px;font-weight:400;color:var(--bark);margin-bottom:24px">{{ rokTekst }} Termin je u prozoru od dva sata i potvrđujemo ga u aplikaciji.</p>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:32px">
            <button
              v-for="t in terminOpcije"
              :key="t.value"
              type="button"
              class="num"
              style="padding:18px 20px;font-size:16px;text-align:left;cursor:pointer"
              :style="{
                border: `1px solid ${form.preferred_window === t.value ? 'var(--ink)' : 'var(--sand)'}`,
                background: form.preferred_window === t.value ? 'var(--ivory)' : 'var(--white)',
                fontWeight: form.preferred_window === t.value ? 600 : 400,
              }"
              @click="form.preferred_window = t.value"
            >{{ t.label }}</button>
          </div>
          <div class="field" style="margin-bottom:24px;max-width:520px">
            <label for="rk-pristup" class="field-label">Pristup stanu (nije obavezno)</label>
            <input id="rk-pristup" v-model="form.pristup" type="text" placeholder="npr. Interfon ne radi, javite u aplikaciji kad dođete">
          </div>

          <p v-if="!online" class="field-error-text" style="margin-bottom:16px">Prijava kvara zahtijeva internet konekciju. Provjerite vezu i pokušajte ponovo.</p>
          <p v-if="submitError" class="error-panel" style="margin-bottom:16px">{{ submitError }}</p>

          <div style="display:flex;gap:12px">
            <button type="button" class="btn btn-ember" :disabled="step3Blocked || submitting || !online" :style="(step3Blocked || !online) ? { background: 'var(--sand)', color: 'var(--ink)' } : {}" @click="submit">
              {{ submitting ? 'Slanje...' : 'Prijavite kvar' }}
            </button>
            <button type="button" class="btn btn-ghost-ink" @click="step = 2">Nazad</button>
          </div>
        </div>
      </div>
    </div>
  </KlijentLayout>
</template>
