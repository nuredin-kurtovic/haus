<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { fetchCities, fetchPackages, fetchSettings } from '../../api/catalog';
import { apiPost, ApiError } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import AppPromo from '../../components/AppPromo.vue';
import {
  mjeseci,
  paketJedinicaGodisnje,
  paketKratko,
  popustNaKolicinu,
  proTotal,
  sati,
  stanova,
} from '../../utils/format';

// Prag od kojeg HAUS Pro postaje ponuda umjesto automatske naplate.
// Isti broj kao RegistrationService::PONUDA_OD_STANOVA, dokumentovan u docs/API.md.
const PONUDA_OD_STANOVA = 10;
const DRAFT_KEY = 'haus_reg_draft';

const route = useRoute();
const auth = useAuthStore();

const packages = ref([]);
const cities = ref([]);
const settings = ref(null);
const loaded = ref(false);

const phase = ref('form'); // 'form' | 'done'
const step = ref(1); // 1 | 2
const submitting = ref(false);
const serverError = ref('');
const retryNotice = ref('');
const errors = reactive({});
const result = ref(null);

const form = reactive({
  package_id: null,
  name: '',
  email: '',
  password: '',
  city_id: '',
  street: '',
  terms: false,
  payment_method: 'uplatnica',
});

const apartments = reactive([]);

function blankApartment() {
  return { city_id: '', street: '', use: 'izdaje_se', contact_name: '', contact_note: '' };
}

const activeCities = computed(() => cities.value.filter((c) => c.status === 'aktivan'));
const selectedPackage = computed(() => packages.value.find((p) => p.id === form.package_id) || null);
const isPro = computed(() => Boolean(selectedPackage.value?.is_per_apartment));
const isPonuda = computed(() => isPro.value && apartments.length >= PONUDA_OD_STANOVA);
const proPct = computed(() =>
  isPro.value ? popustNaKolicinu(apartments.length, selectedPackage.value?.volume_discount_tiers || []) : 0
);

const popustLinija = computed(() => {
  if (!isPro.value) return '';
  if (isPonuda.value) return 'Cijena po dogovoru, šaljemo ponudu.';
  if (proPct.value > 0) return `${proPct.value}% popusta na količinu.`;
  return 'Za 2 i više stanova ide popust na količinu.';
});

const displayPrice = computed(() => {
  if (!selectedPackage.value) return null;
  if (isPro.value) {
    if (isPonuda.value) return 'Dogovor';
    return proTotal(selectedPackage.value.price_year, apartments.length, selectedPackage.value.volume_discount_tiers || []);
  }
  return Math.round(selectedPackage.value.price_year);
});

watch(
  () => form.package_id,
  () => {
    delete errors.package_id;
    const pkg = selectedPackage.value;
    if (pkg && pkg.is_per_apartment && apartments.length < 2) {
      apartments.splice(0, apartments.length, blankApartment(), blankApartment());
    }
  }
);

function addApartment() {
  apartments.push(blankApartment());
}
function removeLastApartment() {
  if (apartments.length > 2) apartments.pop();
}
function removeApartment(i) {
  if (apartments.length > 2) apartments.splice(i, 1);
}

onMounted(async () => {
  try {
    const [pkgs, cts, sett] = await Promise.all([fetchPackages(), fetchCities(), fetchSettings()]);
    packages.value = pkgs;
    cities.value = cts;
    settings.value = sett;

    const querySlug = route.query.paket;
    const preselect =
      pkgs.find((p) => p.slug === querySlug) || pkgs.find((p) => p.slug === 'haus-plus') || pkgs[0];
    if (preselect) form.package_id = preselect.id;

    restoreDraftIfRetrying();
  } finally {
    loaded.value = true;
  }
});

function restoreDraftIfRetrying() {
  if (route.query.nastavak !== 'placanje') return;
  try {
    const raw = window.sessionStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    const draft = JSON.parse(raw);
    if (draft.package_id) form.package_id = draft.package_id;
    form.name = draft.name || '';
    form.email = draft.email || '';
    form.city_id = draft.city_id ?? '';
    form.street = draft.street || '';
    form.terms = Boolean(draft.terms);
    if (Array.isArray(draft.apartments) && draft.apartments.length >= 2) {
      apartments.splice(0, apartments.length, ...draft.apartments);
    }
    step.value = 2;
    retryNotice.value = 'Uplata karticom nije prošla. Izaberite način plaćanja i pokušajte ponovo.';
  } catch (e) {
    // sessionStorage nedostupan ili oštećen draft, nastavljamo od praznog obrasca.
  }
}

function persistDraftForRetry() {
  try {
    window.sessionStorage.setItem(
      DRAFT_KEY,
      JSON.stringify({
        package_id: form.package_id,
        name: form.name,
        email: form.email,
        city_id: form.city_id,
        street: form.street,
        terms: form.terms,
        apartments: JSON.parse(JSON.stringify(apartments)),
      })
    );
  } catch (e) {
    // Ako sessionStorage nije dostupan, korisnik i dalje ide na plaćanje, samo se povratak neće predlopuniti.
  }
}

function stanWord(n) {
  return stanova(n);
}

function validateStep1() {
  ['package_id', 'name', 'email', 'password', 'terms', 'properties'].forEach((k) => delete errors[k]);
  Object.keys(errors)
    .filter((k) => k.startsWith('properties.'))
    .forEach((k) => delete errors[k]);

  let ok = true;

  if (!form.package_id) {
    errors.package_id = 'Odaberite paket.';
    ok = false;
  }
  if (!form.name.trim()) {
    errors.name = 'Unesite ime i prezime.';
    ok = false;
  }
  if (!form.email.trim()) {
    errors.email = 'Unesite mejl adresu.';
    ok = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = 'Mejl adresa nije ispravna.';
    ok = false;
  }
  if (!form.password || form.password.length < 8) {
    errors.password = 'Lozinka mora imati najmanje 8 znakova.';
    ok = false;
  }

  if (isPro.value) {
    apartments.forEach((a, i) => {
      if (!a.city_id) {
        errors[`properties.${i}.city_id`] = 'Odaberite grad.';
        ok = false;
      }
      if (!a.street.trim()) {
        errors[`properties.${i}.street`] = 'Unesite ulicu i broj.';
        ok = false;
      }
    });
  } else {
    if (!form.city_id) {
      errors['properties.0.city_id'] = 'Odaberite grad.';
      ok = false;
    }
    if (!form.street.trim()) {
      errors['properties.0.street'] = 'Unesite ulicu i broj.';
      ok = false;
    }
  }

  if (!form.terms) {
    errors.terms = 'Morate prihvatiti uslove korištenja.';
    ok = false;
  }

  return ok;
}

function goToStep2() {
  if (validateStep1()) {
    step.value = 2;
  }
}

function buildProperties() {
  if (isPro.value) {
    return apartments.map((a) => ({
      city_id: Number(a.city_id),
      street: a.street.trim(),
      use: a.use || undefined,
      contact_name: a.contact_name?.trim() || undefined,
      contact_note: a.contact_note?.trim() || undefined,
    }));
  }
  return [{ city_id: Number(form.city_id), street: form.street.trim() }];
}

function applyServerErrors(errObj) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  let onlyPayment = true;
  Object.entries(errObj || {}).forEach(([key, msgs]) => {
    errors[key] = Array.isArray(msgs) ? msgs[0] : String(msgs);
    if (key !== 'payment_method') onlyPayment = false;
  });
  if (!onlyPayment) step.value = 1;
}

async function submitRegistration() {
  serverError.value = '';
  submitting.value = true;
  try {
    const payload = {
      package_id: form.package_id,
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      payment_method: form.payment_method,
      properties: buildProperties(),
    };
    const body = await apiPost('/auth/register', payload);
    auth.setSession({ token: body.token, user: body.user, role: 'klijent' });
    result.value = body;

    if (body.payment?.redirect_url) {
      persistDraftForRetry();
      window.location.href = body.payment.redirect_url;
      return;
    }

    phase.value = 'done';
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      applyServerErrors(e.errors);
    } else {
      serverError.value = (e && e.message) || 'Registracija nije uspjela. Provjerite internet konekciju.';
    }
  } finally {
    submitting.value = false;
  }
}

const paymentMethods = [
  {
    id: 'uplatnica',
    t: 'Uplatnica na mejl',
    d: 'Uplatnicu i račun šaljemo na mejl. Pretplata se aktivira kad uplata legne, obično isti ili sljedeći radni dan.',
  },
  {
    id: 'kartica',
    t: 'Kartica',
    d: 'Aktivacija odmah. Račun dobijate na mejl istog trenutka. Automatska obnova se može isključiti u profilu.',
  },
];

function stepState(n) {
  if (phase.value === 'done') return 'done';
  if (n < step.value) return 'done';
  if (n === step.value) return 'active';
  return 'upcoming';
}

const stepLabels = ['Paket i podaci', 'Plaćanje'];

// Desna kolona: cijena/naziv su lokalna procjena prije slanja, a serverski
// odgovor postaje izvor istine odmah nakon uspješne registracije.
const asidePackageName = computed(() => {
  if (phase.value === 'done' && result.value) return result.value.subscription.package.name;
  return selectedPackage.value ? selectedPackage.value.name : '';
});

const asideApartmentsCount = computed(() => {
  if (phase.value === 'done' && result.value) {
    return result.value.subscription.properties?.length || apartments.length;
  }
  return apartments.length;
});

const asidePackageIsPro = computed(() => {
  if (phase.value === 'done' && result.value) return Boolean(result.value.subscription.package.is_per_apartment);
  return isPro.value;
});

const asidePrice = computed(() => {
  if (phase.value === 'done' && result.value) {
    return result.value.status === 'ponuda' ? 'Dogovor' : result.value.subscription.price;
  }
  return displayPrice.value;
});

const asidePriceSize = computed(() => (asidePrice.value === 'Dogovor' ? '28px' : '40px'));

const asideUnit = computed(() => {
  if (!selectedPackage.value && !(phase.value === 'done' && result.value)) return '';
  if (asidePackageIsPro.value) {
    if (asidePrice.value === 'Dogovor') return `za ${asideApartmentsCount.value} ${stanWord(asideApartmentsCount.value)}`;
    return `KM godišnje · ${asideApartmentsCount.value} ${stanWord(asideApartmentsCount.value)}`;
  }
  return 'KM godišnje';
});

const summaryRows = computed(() => {
  const pkg = selectedPackage.value;
  if (!pkg) return [];
  const rows = [
    { k: 'Uključene intervencije', v: String(pkg.visits_per_year) },
    { k: 'Rok izlaska', v: sati(pkg.deadline_hours) },
    { k: 'Hitno', v: pkg.emergency_included ? sati(pkg.emergency_deadline_hours) : `${sati(pkg.emergency_deadline_hours)} uz doplatu` },
    { k: 'Popust na rad', v: `${pkg.labor_discount_pct}%` },
    { k: 'Garancija na rad', v: mjeseci(pkg.warranty_months) },
  ];
  if (pkg.inspections_per_year > 0) {
    rows.push({ k: 'Godišnji pregled', v: `${pkg.inspections_per_year}×` });
  }
  if (isPro.value) {
    rows.push({ k: 'Stanovi', v: `${apartments.length} ${stanWord(apartments.length)}` });
    rows.push({ k: 'Popust na količinu', v: isPonuda.value ? 'Po dogovoru' : proPct.value > 0 ? `${proPct.value}%` : 'Nema' });
  } else {
    const cityName = activeCities.value.find((c) => c.id === form.city_id)?.name;
    rows.push({ k: 'Grad', v: cityName || 'Nije izabran' });
    rows.push({ k: 'Adresa', v: form.street || 'Nije upisana' });
  }
  return rows;
});

const nijeUkljucenoText = computed(() => {
  if (!settings.value) return '';
  return `Materijal se naplaćuje odvojeno, po nabavnoj cijeni + ${settings.value.materijal_marza_pct}%, uz popust iz paketa. Rad iznad ${settings.value.ukljuceno_minuta} minuta ide po sniženoj satnici. Ovo kažemo prije uplate, ne poslije.`;
});

const submitLabel = computed(() => {
  if (submitting.value) return 'Obrađujemo...';
  if (isPro.value && isPonuda.value) return 'Zatražite ponudu';
  return null; // renderovano posebno, sa .num oko cijene
});

const regUvod = computed(() => {
  if (isPro.value) {
    return 'HAUS Pro se plaća po stanu. Upišite svaki stan koji ulazi u pretplatu.';
  }
  return 'Pretplata je vezana za jednu adresu. Adresu upisujete sada i ona se ne mijenja bez našeg odobrenja.';
});
</script>

<template>
  <div v-if="!loaded" style="min-height:100vh;background:var(--ivory)"></div>
  <div v-else class="reg-shell">
    <div class="reg-main">
      <div style="width:100%;max-width:720px">
        <RouterLink to="/" style="display:block;margin-bottom:44px">
          <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="152" height="33">
        </RouterLink>

        <nav style="display:flex;gap:36px;padding-bottom:22px;border-bottom:1px solid var(--sand);margin-bottom:40px" aria-label="Koraci">
          <div v-for="(label, i) in stepLabels" :key="label" style="display:flex;align-items:center;gap:13px">
            <span
              class="num"
              :style="{
                width: '30px',
                height: '30px',
                flex: 'none',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                border: '1px solid var(--ink)',
                fontSize: '14px',
                fontWeight: 700,
                background: stepState(i + 1) === 'active' ? 'var(--ink)' : stepState(i + 1) === 'done' ? 'var(--sand)' : 'transparent',
                color: stepState(i + 1) === 'active' ? 'var(--ivory)' : 'var(--ink)',
              }"
            >{{ stepState(i + 1) === 'done' ? '✓' : i + 1 }}</span>
            <span :style="{ fontSize: '16px', fontWeight: stepState(i + 1) === 'active' ? 600 : 400, color: stepState(i + 1) === 'upcoming' ? 'var(--bark)' : 'var(--ink)' }">{{ label }}</span>
          </div>
        </nav>

        <!-- KORAK 1: Paket i podaci -->
        <div v-if="phase === 'form' && step === 1">
          <h1 style="font-size:46px;font-weight:700;line-height:1.05;letter-spacing:-0.02em;margin-bottom:12px">Registracija</h1>
          <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:40px;max-width:560px">{{ regUvod }}</p>

          <form @submit.prevent="goToStep2">
            <fieldset style="border:0;padding:0;margin:0 0 36px">
              <legend class="field-label" style="margin-bottom:14px">Izaberite paket</legend>
              <div class="pkg-tiles">
                <label
                  v-for="pkg in packages"
                  :key="pkg.id"
                  style="display:block;cursor:pointer;padding:20px 18px 22px"
                  :style="{
                    border: `1px solid ${form.package_id === pkg.id ? 'var(--ink)' : 'var(--sand)'}`,
                    borderTop: `3px solid ${form.package_id === pkg.id ? 'var(--ember)' : 'var(--sand)'}`,
                    background: form.package_id === pkg.id ? 'var(--ivory)' : 'var(--white)',
                  }"
                >
                  <span style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
                    <input type="radio" name="paket" :value="pkg.id" v-model="form.package_id" style="width:17px;height:17px;accent-color:var(--ember);margin:0;flex:none">
                    <span style="font-size:16px;font-weight:600;letter-spacing:.03em">{{ pkg.name }}</span>
                  </span>
                  <span class="num" style="display:block;font-size:30px;font-weight:700;line-height:1;margin-bottom:4px">{{ Math.round(pkg.price_year) }}</span>
                  <span style="display:block;font-size:13px;font-weight:400;color:var(--bark);margin-bottom:14px">KM {{ paketJedinicaGodisnje(pkg) }}</span>
                  <span style="display:block;font-size:13px;font-weight:400;line-height:1.5;color:var(--bark);border-top:1px solid var(--sand);padding-top:12px">{{ paketKratko(pkg) }}</span>
                </label>
              </div>
              <p v-if="errors.package_id" class="field-error-text" style="margin-top:10px">{{ errors.package_id }}</p>
            </fieldset>

            <fieldset style="border:0;padding:0;margin:0 0 28px">
              <legend class="field-label" style="margin-bottom:14px">Vaši podaci</legend>
              <div class="reg-2up" style="margin-bottom:20px">
                <div class="field">
                  <label for="r-ime" class="field-label" style="font-size:13px">Ime i prezime</label>
                  <input id="r-ime" v-model="form.name" type="text" autocomplete="name" :class="{ 'field-error': errors.name }" @input="delete errors.name">
                  <p v-if="errors.name" class="field-error-text">{{ errors.name }}</p>
                </div>
                <div class="field">
                  <label for="r-mail" class="field-label" style="font-size:13px">E-mail</label>
                  <input id="r-mail" v-model="form.email" type="email" autocomplete="email" :class="{ 'field-error': errors.email }" @input="delete errors.email">
                  <p v-if="errors.email" class="field-error-text">{{ errors.email }}</p>
                </div>
              </div>
              <div class="field" style="max-width:340px">
                <label for="r-pass" class="field-label" style="font-size:13px">Lozinka</label>
                <input id="r-pass" v-model="form.password" type="password" autocomplete="new-password" :class="{ 'field-error': errors.password }" @input="delete errors.password">
                <p v-if="errors.password" class="field-error-text">{{ errors.password }}</p>
                <p v-else style="font-size:13px;font-weight:400;color:var(--bark)">Najmanje 8 znakova.</p>
              </div>
            </fieldset>

            <!-- Adresa: Mini / Plus -->
            <fieldset v-if="!isPro" style="border:0;padding:0;margin:0 0 32px">
              <legend class="field-label" style="margin-bottom:14px">Adresa stana</legend>
              <div class="reg-addr-grid">
                <div class="field">
                  <label for="r-grad" class="field-label" style="font-size:13px">Grad</label>
                  <select id="r-grad" v-model="form.city_id" :class="{ 'field-error': errors['properties.0.city_id'] }" @change="delete errors['properties.0.city_id']">
                    <option value="">Izaberite grad</option>
                    <option v-for="c in activeCities" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                  <p v-if="errors['properties.0.city_id']" class="field-error-text">{{ errors['properties.0.city_id'] }}</p>
                </div>
                <div class="field">
                  <label for="r-adr" class="field-label" style="font-size:13px">Ulica i broj</label>
                  <input id="r-adr" v-model="form.street" type="text" autocomplete="street-address" placeholder="Ulica, broj, sprat" :class="{ 'field-error': errors['properties.0.street'] }" @input="delete errors['properties.0.street']">
                  <p v-if="errors['properties.0.street']" class="field-error-text">{{ errors['properties.0.street'] }}</p>
                </div>
              </div>
              <p v-if="errors.properties" class="field-error-text" style="margin-top:12px">{{ errors.properties }}</p>
            </fieldset>

            <!-- Adresa: Pro, po stanu -->
            <fieldset v-else style="border:0;padding:0;margin:0 0 32px">
              <legend class="field-label" style="margin-bottom:6px">Vaši stanovi</legend>

              <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;border:1px solid var(--ink);background:var(--white);padding:16px 18px;margin-bottom:16px;flex-wrap:wrap">
                <div>
                  <span style="display:block;font-size:15px;font-weight:600;margin-bottom:2px">Broj stanova</span>
                  <span style="display:block;font-size:13px;font-weight:400;color:var(--bark)">{{ popustLinija }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:2px;flex:none">
                  <button
                    type="button"
                    aria-label="Manje stanova"
                    :disabled="apartments.length <= 2"
                    @click="removeLastApartment"
                    :style="{
                      width: '46px', height: '46px', border: '1px solid var(--ink)',
                      background: apartments.length <= 2 ? 'var(--sand)' : 'transparent',
                      color: apartments.length <= 2 ? 'var(--bark)' : 'var(--ink)',
                      fontSize: '20px', fontWeight: 600, lineHeight: 1,
                      cursor: apartments.length <= 2 ? 'not-allowed' : 'pointer',
                    }"
                  >−</button>
                  <span class="num" style="min-width:56px;text-align:center;font-size:22px;font-weight:700">{{ apartments.length }}</span>
                  <button
                    type="button"
                    aria-label="Više stanova"
                    @click="addApartment"
                    style="width:46px;height:46px;border:1px solid var(--ink);background:transparent;color:var(--ink);font-size:20px;font-weight:600;line-height:1;cursor:pointer"
                  >+</button>
                </div>
              </div>

              <div class="hairline-grid" style="grid-template-columns:1fr">
                <div v-for="(a, i) in apartments" :key="i" style="background:var(--white);padding:18px 20px">
                  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:14px">
                    <span class="num" style="font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--bark)">Stan {{ i + 1 }}</span>
                    <button
                      v-if="apartments.length > 2"
                      type="button"
                      style="border:0;background:transparent;padding:0;font-size:13px;font-weight:600;color:var(--bark);cursor:pointer;text-decoration:underline;text-decoration-color:var(--ember);text-decoration-thickness:2px;text-underline-offset:3px"
                      @click="removeApartment(i)"
                    >Ukloni</button>
                  </div>
                  <div class="reg-apt-grid" style="margin-bottom:14px">
                    <div class="field">
                      <label :for="'st-grad-' + i" class="field-label" style="font-size:13px">Grad</label>
                      <select :id="'st-grad-' + i" v-model="a.city_id" :class="{ 'field-error': errors[`properties.${i}.city_id`] }" @change="delete errors[`properties.${i}.city_id`]">
                        <option value="">Izaberite grad</option>
                        <option v-for="c in activeCities" :key="c.id" :value="c.id">{{ c.name }}</option>
                      </select>
                      <p v-if="errors[`properties.${i}.city_id`]" class="field-error-text">{{ errors[`properties.${i}.city_id`] }}</p>
                    </div>
                    <div class="field">
                      <label :for="'st-adr-' + i" class="field-label" style="font-size:13px">Ulica i broj</label>
                      <input :id="'st-adr-' + i" v-model="a.street" type="text" placeholder="Ulica, broj, sprat" :class="{ 'field-error': errors[`properties.${i}.street`] }" @input="delete errors[`properties.${i}.street`]">
                      <p v-if="errors[`properties.${i}.street`]" class="field-error-text">{{ errors[`properties.${i}.street`] }}</p>
                    </div>
                  </div>
                  <div class="reg-apt-grid" style="margin-bottom:14px">
                    <div class="field">
                      <label :for="'st-tip-' + i" class="field-label" style="font-size:13px">Namjena</label>
                      <select :id="'st-tip-' + i" v-model="a.use">
                        <option value="zivim">Živim u njemu</option>
                        <option value="izdaje_se">Izdaje se</option>
                        <option value="prazan">Prazan / dijaspora</option>
                      </select>
                    </div>
                    <div class="field">
                      <label :for="'st-kime-' + i" class="field-label" style="font-size:13px">Ime kontakta na adresi <span style="font-weight:400;text-transform:none;letter-spacing:normal">(nije obavezno)</span></label>
                      <input :id="'st-kime-' + i" v-model="a.contact_name" type="text" placeholder="Ime stanara ili susjeda">
                    </div>
                  </div>
                  <div class="field">
                    <label :for="'st-knap-' + i" class="field-label" style="font-size:13px">Napomena o pristupu <span style="font-weight:400;text-transform:none;letter-spacing:normal">(nije obavezno)</span></label>
                    <input :id="'st-knap-' + i" v-model="a.contact_note" type="text" placeholder="npr. šifra ulaznih vrata, sprat, zvono">
                  </div>
                </div>
              </div>
              <p v-if="errors.properties" class="field-error-text" style="margin-top:12px">{{ errors.properties }}</p>
            </fieldset>

            <div style="border-top:1px solid var(--sand);padding-top:28px;display:flex;flex-direction:column;gap:22px">
              <label style="display:grid;grid-template-columns:20px 1fr;gap:14px;align-items:start;cursor:pointer">
                <input type="checkbox" v-model="form.terms" @change="delete errors.terms" style="width:18px;height:18px;accent-color:var(--ember);margin:2px 0 0">
                <span style="font-size:15px;font-weight:400;line-height:1.5">
                  Pročitao sam <RouterLink to="/uslovi" target="_blank" rel="noopener">uslove korištenja</RouterLink> i prihvatam ih.
                </span>
              </label>
              <p v-if="errors.terms" class="field-error-text">{{ errors.terms }}</p>
              <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                <button type="submit" class="btn btn-ember" style="padding:18px 30px;font-size:17px">Nastavite na plaćanje</button>
                <span style="font-size:14px;font-weight:400;color:var(--bark)">U ovom koraku se ništa ne naplaćuje.</span>
              </div>
            </div>
          </form>
        </div>

        <!-- KORAK 2: Plaćanje -->
        <div v-else-if="phase === 'form' && step === 2">
          <h1 style="font-size:46px;font-weight:700;line-height:1.05;letter-spacing:-0.02em;margin-bottom:12px">Plaćanje</h1>
          <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:40px;max-width:560px">Dva načina. Karticu ne morate upisivati. Ako vam se to ne radi, uplatnicu šaljemo na mejl.</p>
          <p v-if="retryNotice" style="font-size:14px;font-weight:400;color:var(--bark);background:var(--white);border:1px solid var(--sand);padding:14px 16px;margin-bottom:28px">{{ retryNotice }}</p>

          <form @submit.prevent="submitRegistration">
            <fieldset style="border:0;padding:0;margin:0 0 32px;display:flex;flex-direction:column;gap:12px">
              <legend class="field-label" style="margin-bottom:14px">Način plaćanja</legend>
              <label
                v-for="m in paymentMethods"
                :key="m.id"
                style="display:grid;grid-template-columns:20px 1fr;gap:16px;align-items:start;padding:20px 22px;cursor:pointer"
                :style="{
                  border: `1px solid ${form.payment_method === m.id ? 'var(--ink)' : 'var(--sand)'}`,
                  borderLeft: `4px solid ${form.payment_method === m.id ? 'var(--ink)' : 'var(--sand)'}`,
                  background: form.payment_method === m.id ? 'var(--ivory)' : 'var(--white)',
                }"
              >
                <input type="radio" name="metoda" :value="m.id" v-model="form.payment_method" style="width:18px;height:18px;accent-color:var(--ember);margin:2px 0 0">
                <span>
                  <span style="display:block;font-size:18px;font-weight:600;margin-bottom:4px">{{ m.t }}</span>
                  <span style="display:block;font-size:15px;font-weight:400;color:var(--bark);line-height:1.55">{{ m.d }}</span>
                </span>
              </label>
              <p v-if="errors.payment_method" class="field-error-text">{{ errors.payment_method }}</p>
            </fieldset>

            <p v-if="serverError" class="error-panel" style="margin-bottom:24px">{{ serverError }}</p>

            <div style="border-top:1px solid var(--sand);padding-top:28px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
              <button type="submit" class="btn btn-ember" :disabled="submitting" style="padding:18px 30px;font-size:17px">
                <span v-if="submitLabel">{{ submitLabel }}</span>
                <span v-else>Potvrdite pretplatu &middot; <span class="num">{{ displayPrice }}</span> KM</span>
              </button>
              <button type="button" class="btn btn-ghost-ink" style="padding:17px 26px;font-size:16px" @click="step = 1">Nazad</button>
            </div>
          </form>
        </div>

        <!-- Treće stanje: poslije uspjeha -->
        <div v-else-if="phase === 'done' && result" style="max-width:560px">
          <template v-if="result.status === 'ponuda'">
            <span class="chip chip-ink" style="margin-bottom:20px">Zahtjev za ponudu</span>
            <h1 style="font-size:44px;font-weight:700;line-height:1.08;letter-spacing:-0.015em;margin-bottom:16px">Šaljemo vam ponudu.</h1>
            <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:20px">
              Prijava za <span class="num">{{ asideApartmentsCount }}</span> {{ stanWord(asideApartmentsCount) }} na paketu {{ asidePackageName }} ide dispečeru. Ponudu sa cijenom i rokom šaljemo na <strong>{{ result.user.email }}</strong> u najkraćem roku.
            </p>
            <p style="font-size:15px;font-weight:400;color:var(--bark);line-height:1.5;margin-bottom:32px">Prijavljeni ste odmah. Kad ponuda bude prihvaćena, pretplata se aktivira i vidjet ćete je u aplikaciji.</p>
            <RouterLink to="/klijent" class="btn btn-ember" style="padding:18px 30px;font-size:17px;margin-bottom:28px">U aplikaciju</RouterLink>
            <AppPromo message="Prijava kvara ide kroz aplikaciju." />
          </template>
          <template v-else>
            <span class="chip chip-ink" style="margin-bottom:20px">Uplatnica na mejl</span>
            <h1 style="font-size:44px;font-weight:700;line-height:1.08;letter-spacing:-0.015em;margin-bottom:16px">Registracija je primljena.</h1>
            <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:28px">
              Uplatnicu i račun šaljemo na <strong>{{ result.user.email }}</strong>. Pretplata radi od momenta kad uplata legne, obično isti ili sljedeći radni dan.
            </p>
            <div class="hairline-grid grid-cols-3" style="margin-bottom:32px">
              <div style="background:var(--white);padding:18px 20px">
                <span class="field-label" style="display:block;margin-bottom:8px">Broj fakture</span>
                <span class="num" style="font-size:22px;font-weight:700">{{ result.invoice.number }}</span>
              </div>
              <div style="background:var(--white);padding:18px 20px">
                <span class="field-label" style="display:block;margin-bottom:8px">Poziv na broj</span>
                <span class="num" style="font-size:22px;font-weight:700">{{ result.invoice.number }}</span>
              </div>
              <div style="background:var(--white);padding:18px 20px">
                <span class="field-label" style="display:block;margin-bottom:8px">Iznos</span>
                <span class="num" style="font-size:22px;font-weight:700">{{ result.subscription.price }} KM</span>
              </div>
            </div>
            <RouterLink to="/klijent" class="btn btn-ember" style="padding:18px 30px;font-size:17px;margin-bottom:28px">U aplikaciju</RouterLink>
            <AppPromo message="Prijava kvara ide kroz aplikaciju." />
          </template>
        </div>
      </div>
    </div>

    <aside class="reg-aside">
      <div class="reg-aside-inner">
        <h2 class="eyebrow" style="color:var(--grey)">Vaša pretplata</h2>
        <div style="border-bottom:1px solid var(--bark);padding-bottom:22px">
          <span style="display:block;font-size:30px;font-weight:700;color:var(--ivory);letter-spacing:.04em;margin-bottom:6px">{{ asidePackageName }}</span>
          <span style="display:flex;align-items:baseline;gap:8px">
            <span class="num" :style="{ fontSize: asidePriceSize, fontWeight: 700, color: 'var(--ivory)', lineHeight: 1 }">{{ asidePrice }}</span>
            <span style="font-size:15px;font-weight:400;color:var(--grey)">{{ asideUnit }}</span>
          </span>
        </div>
        <ul style="display:flex;flex-direction:column;gap:0">
          <li v-for="row in summaryRows" :key="row.k" style="display:grid;grid-template-columns:1fr auto;gap:16px;font-size:14px;font-weight:400;color:var(--sand);border-bottom:1px solid var(--bark);padding:11px 0">
            <span>{{ row.k }}</span><span class="num" style="color:var(--ivory);font-weight:500;text-align:right">{{ row.v }}</span>
          </li>
        </ul>
        <div style="background:var(--ember);padding:2px">
          <div style="background:var(--ivory);padding:20px 22px">
            <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;color:var(--ink)">Šta nije uključeno</h3>
            <p style="font-size:14px;font-weight:400;line-height:1.55;color:var(--ink)">{{ nijeUkljucenoText }}</p>
          </div>
        </div>
        <p style="font-size:13px;font-weight:400;color:var(--grey);line-height:1.55">Automatska obnova. Podsjetnik šaljemo 60 dana prije isteka i možete je otkazati u aplikaciji.</p>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.reg-shell {
  min-height: 100vh;
  background: var(--ivory);
  display: grid;
  grid-template-columns: minmax(0, 1fr) 420px;
}
.reg-main {
  display: flex;
  justify-content: center;
  padding: 48px 56px 96px;
}
.reg-aside {
  background: var(--ink);
}
.reg-aside-inner {
  position: sticky;
  top: 0;
  padding: 48px 40px 44px;
  display: flex;
  flex-direction: column;
  gap: 26px;
}
.pkg-tiles {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.reg-2up {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.reg-addr-grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 20px;
}
.reg-apt-grid {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 14px;
}
@media (max-width: 1024px) {
  .reg-shell { grid-template-columns: 1fr; }
  .reg-aside-inner { position: static; top: auto; }
}
@media (max-width: 768px) {
  .reg-main { padding: 24px 20px 56px; }
  .reg-aside-inner { padding: 32px 20px; }
}
@media (max-width: 640px) {
  .pkg-tiles { grid-template-columns: 1fr; }
  .reg-2up { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
  .reg-addr-grid { grid-template-columns: 1fr; }
  .reg-apt-grid { grid-template-columns: 1fr; }
}
</style>
