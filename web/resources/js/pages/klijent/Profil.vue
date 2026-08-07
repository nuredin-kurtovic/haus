<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import KlijentLayout from '../../layouts/KlijentLayout.vue';
import { fetchProfile, fetchSubscription, requestAddressChange, updateProfile } from './api';
import { propertyUseLabel } from './format';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const loading = ref(true);
const error = ref('');
const profile = ref(null);
const subscription = ref(null);
const saving = ref(false);

const form = reactive({ name: '', notifications: { push: false, email: false, marketing: false } });

async function load() {
  error.value = '';
  try {
    const [p, s] = await Promise.all([fetchProfile(), fetchSubscription().catch(() => null)]);
    profile.value = p;
    subscription.value = s;
    form.name = p.name;
    form.notifications = { ...p.notifications };
  } catch (e) {
    error.value = (e && e.message) || 'Greška pri učitavanju. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}
onMounted(load);

async function saveProfile() {
  saving.value = true;
  try {
    const body = await updateProfile({ name: form.name.trim(), notifications: form.notifications });
    profile.value = body.data;
    toast.show(body.message || 'Podaci su sačuvani.');
  } catch (e) {
    toast.show((e && e.message) || 'Podaci nisu sačuvani. Provjerite internet konekciju.');
  } finally {
    saving.value = false;
  }
}

const NOTIF_DEFS = [
  { key: 'push', title: 'Obavještenja u aplikaciji', desc: 'Potvrda termina, majstor krenuo, kašnjenje sa novim vremenom.' },
  { key: 'email', title: 'Nalaz i fotografije na mejl', desc: 'U roku od 24 sata poslije intervencije: nalaz, fotografije prije i poslije, datum garancije.' },
  { key: 'marketing', title: 'Marketing i ponude', desc: 'Popusti, nove usluge i podsjetnik na obnovu.' },
];

const properties = computed(() => subscription.value?.properties || []);

const addressFormOpen = ref(false);
const addressMessage = ref('');
const addressPropertyId = ref('');
const addressSubmitting = ref(false);
const addressError = ref('');

function openAddressForm() {
  addressFormOpen.value = true;
  addressMessage.value = '';
  addressError.value = '';
  addressPropertyId.value = properties.value[0]?.id || '';
}

async function submitAddressChange() {
  addressError.value = '';
  if (addressMessage.value.trim().length < 10) {
    addressError.value = 'Napišite najmanje 10 znakova: koju adresu mijenjate i na koju.';
    return;
  }
  addressSubmitting.value = true;
  try {
    const payload = { message: addressMessage.value.trim() };
    if (properties.value.length > 1) payload.subscription_property_id = Number(addressPropertyId.value);
    const body = await requestAddressChange(payload);
    toast.show(body.message);
    addressFormOpen.value = false;
  } catch (e) {
    addressError.value = (e && e.message) || 'Zahtjev nije poslan. Provjerite internet konekciju.';
  } finally {
    addressSubmitting.value = false;
  }
}
</script>

<template>
  <KlijentLayout>
    <div style="max-width:900px;margin:0 auto;padding:56px 28px 96px">
      <h1 style="font-size:44px;font-weight:700;line-height:1.05;letter-spacing:-0.015em;margin-bottom:40px">Profil</h1>

      <div v-if="loading" style="padding:60px 0;text-align:center;color:var(--bark)">Učitavanje...</div>

      <div v-else-if="error && !profile" class="error-panel" style="max-width:560px">
        <p style="margin-bottom:16px">{{ error }}</p>
        <button type="button" class="btn btn-ghost-ink" @click="load">Pokušajte ponovo</button>
      </div>

      <template v-else>
        <p v-if="error" class="error-panel" style="margin-bottom:24px">{{ error }} Prikazujemo zadnje učitane podatke.</p>

        <section style="border:1px solid var(--sand);padding:32px;margin-bottom:24px">
          <h2 style="font-size:22px;font-weight:700;margin-bottom:20px">Adresa</h2>
          <div v-if="properties.length" style="display:flex;flex-direction:column;gap:1px;background:var(--sand);border:1px solid var(--sand)">
            <div v-for="prop in properties" :key="prop.id" style="display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;background:var(--ivory);padding:20px 24px">
              <div>
                <span style="display:block;font-size:18px;font-weight:600;margin-bottom:4px">{{ prop.street }}, {{ prop.city }}</span>
                <span style="display:block;font-size:14px;font-weight:400;color:var(--bark)">{{ propertyUseLabel(prop.use) }} · Pretplata je vezana za adresu i nije prenosiva.</span>
              </div>
              <button type="button" class="btn btn-ghost-ink" style="white-space:nowrap" @click="openAddressForm">Zatražite promjenu</button>
            </div>
          </div>
          <p v-else style="font-size:15px;font-weight:400;color:var(--bark)">Nemate adresu na pretplati.</p>

          <div v-if="addressFormOpen" style="border:1px solid var(--ink);background:var(--white);padding:24px;margin-top:16px">
            <h3 style="font-size:16px;font-weight:600;margin-bottom:14px">Zahtjev za promjenu adrese</h3>
            <div v-if="properties.length > 1" class="field" style="margin-bottom:16px">
              <label for="pr-adr-sel" class="field-label">Koja adresa</label>
              <select id="pr-adr-sel" v-model="addressPropertyId">
                <option v-for="prop in properties" :key="prop.id" :value="prop.id">{{ prop.street }}, {{ prop.city }}</option>
              </select>
            </div>
            <div class="field" style="margin-bottom:16px">
              <label for="pr-adr-msg" class="field-label">Poruka dispečeru</label>
              <textarea id="pr-adr-msg" v-model="addressMessage" rows="3" placeholder="Napišite koju adresu mijenjate i na koju." :class="{ 'field-error': addressError }"></textarea>
              <p v-if="addressError" class="field-error-text">{{ addressError }}</p>
            </div>
            <div style="display:flex;gap:12px">
              <button type="button" class="btn btn-ember" :disabled="addressSubmitting" @click="submitAddressChange">{{ addressSubmitting ? 'Slanje...' : 'Poslati zahtjev' }}</button>
              <button type="button" class="btn btn-ghost-ink" @click="addressFormOpen = false">Odustani</button>
            </div>
          </div>
        </section>

        <section style="border:1px solid var(--sand);padding:32px;margin-bottom:24px">
          <h2 style="font-size:22px;font-weight:700;margin-bottom:20px">Kontakt</h2>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="field">
              <label for="pr-ime" class="field-label">Ime i prezime</label>
              <input id="pr-ime" v-model="form.name" type="text" autocomplete="name">
            </div>
            <div class="field">
              <label for="pr-mail" class="field-label">E-mail</label>
              <input id="pr-mail" :value="profile.email" type="email" disabled style="color:var(--bark);background:var(--zebra)">
            </div>
          </div>
          <button type="button" class="btn btn-ember" style="margin-top:24px" :disabled="saving" @click="saveProfile">{{ saving ? 'Čuvanje...' : 'Sačuvajte izmjene' }}</button>
        </section>

        <section style="border:1px solid var(--sand);padding:32px">
          <h2 style="font-size:22px;font-weight:700;margin-bottom:8px">Obavještenja</h2>
          <p style="font-size:15px;font-weight:400;color:var(--bark);margin-bottom:20px">Loše vijesti šaljemo mi, prvi, i uz rješenje. Ovo su kanali kojima to radimo.</p>
          <div style="display:flex;flex-direction:column;gap:2px;background:var(--sand);border:1px solid var(--sand)">
            <div v-for="o in NOTIF_DEFS" :key="o.key" style="display:grid;grid-template-columns:1fr 52px;gap:24px;align-items:center;background:var(--white);padding:20px 24px">
              <span>
                <span style="display:block;font-size:16px;font-weight:600;margin-bottom:3px">{{ o.title }}</span>
                <span style="display:block;font-size:14px;font-weight:400;color:var(--bark);line-height:1.5">{{ o.desc }}</span>
              </span>
              <button
                type="button"
                role="switch"
                :aria-checked="form.notifications[o.key]"
                :aria-label="o.title"
                style="width:46px;height:24px;padding:2px;cursor:pointer;justify-self:end"
                :style="{ border: form.notifications[o.key] ? '0' : '1px solid var(--ink)', background: form.notifications[o.key] ? 'var(--ember)' : 'var(--white)' }"
                @click="form.notifications[o.key] = !form.notifications[o.key]"
              >
                <span
                  style="display:block;width:18px;height:18px;transition:margin .2s ease"
                  :style="{ background: form.notifications[o.key] ? 'var(--ivory)' : 'var(--ink)', marginLeft: form.notifications[o.key] ? '22px' : '0' }"
                ></span>
              </button>
            </div>
          </div>
          <button type="button" class="btn btn-ember" style="margin-top:24px" :disabled="saving" @click="saveProfile">{{ saving ? 'Čuvanje...' : 'Sačuvajte izmjene' }}</button>
        </section>
      </template>
    </div>
  </KlijentLayout>
</template>
