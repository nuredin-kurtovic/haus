<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiPost, ApiError } from '../../api/client';

// Lokalna simulacija Monri 3DS stranice (FakeGateway). Ne ide kroz PublicLayout,
// stranica je namjerno izolovana jer imitira eksterni gateway.
const route = useRoute();
const router = useRouter();

const reference = computed(() => String(route.query.ref || ''));
const state = ref('pending'); // 'pending' | 'approved' | 'declined'
const loading = ref(false);
const errorMessage = ref('');

async function odigraj(outcome) {
  errorMessage.value = '';
  loading.value = true;
  try {
    await apiPost('/dev/fake-payment', { reference: reference.value, outcome });
    state.value = outcome === 'approved' ? 'approved' : 'declined';
  } catch (e) {
    errorMessage.value =
      e instanceof ApiError
        ? e.message || 'Simulacija plaćanja nije uspjela.'
        : 'Simulacija plaćanja nije uspjela. Provjerite internet konekciju.';
  } finally {
    loading.value = false;
  }
}

function nazadNaPlacanje() {
  router.push({ path: '/registracija', query: { nastavak: 'placanje' } });
}
</script>

<template>
  <div style="min-height:100vh;background:var(--ivory);display:flex;align-items:center;justify-content:center;padding:48px 24px">
    <div style="width:100%;max-width:520px">

      <template v-if="state === 'pending'">
        <div style="border:1px solid var(--sand);background:var(--white);padding:24px 26px;margin-bottom:32px">
          <p class="eyebrow" style="margin-bottom:10px">Simulacija plaćanja</p>
          <p style="font-size:15px;font-weight:400;color:var(--bark);line-height:1.55">
            Ovo je lokalna simulacija Monri 3DS stranice, isključivo za razvoj. Nema pravih transakcija i nikad se ne traže podaci kartice.
          </p>
        </div>

        <h1 style="font-size:36px;font-weight:700;line-height:1.1;letter-spacing:-0.015em;margin-bottom:20px">Potvrda kartice</h1>

        <div class="field" style="margin-bottom:32px">
          <span class="field-label">Referenca uplate</span>
          <span class="num" style="font-size:18px;font-weight:600">{{ reference || 'Nema referencu u linku.' }}</span>
        </div>

        <p v-if="errorMessage" class="error-panel" style="margin-bottom:24px">{{ errorMessage }}</p>
        <p v-if="!reference" class="field-error-text" style="margin-bottom:24px">Link nema ispravnu referencu uplate. Vratite se na registraciju i pokušajte ponovo.</p>

        <div style="display:flex;flex-direction:column;gap:12px">
          <button type="button" class="btn btn-ember" :disabled="loading || !reference" @click="odigraj('approved')" style="padding:18px 30px;font-size:17px">
            {{ loading ? 'Obrađujemo...' : 'Uspješna uplata' }}
          </button>
          <button type="button" class="btn btn-ghost-ink" :disabled="loading || !reference" @click="odigraj('declined')" style="padding:17px 26px;font-size:16px">
            Neuspješna uplata
          </button>
        </div>
      </template>

      <template v-else-if="state === 'approved'">
        <div style="background:var(--ink);padding:40px 36px;margin-bottom:28px">
          <span class="chip" style="margin-bottom:18px">Kartica potvrđena</span>
          <h1 style="font-size:36px;font-weight:700;line-height:1.1;letter-spacing:-0.015em;color:var(--ivory);margin-bottom:14px">Pretplata je aktivna.</h1>
          <p style="font-size:16px;font-weight:400;color:var(--sand);line-height:1.55;margin-bottom:24px">
            Prvu prijavu kvara možete poslati odmah, ne čekate ništa. Poznata cijena, dogovoren rok i pisana garancija idu od ovog trenutka. Račun je na mejlu.
          </p>
          <RouterLink to="/klijent" class="btn btn-ember" style="padding:17px 26px;font-size:16px">U aplikaciju</RouterLink>
        </div>
      </template>

      <template v-else>
        <h1 style="font-size:36px;font-weight:700;line-height:1.1;letter-spacing:-0.015em;margin-bottom:16px">Uplata nije prošla.</h1>
        <p style="font-size:16px;font-weight:400;color:var(--bark);line-height:1.55;margin-bottom:32px">
          Kartica je odbijena u simulaciji. Registracija ostaje zabilježena, samo nije plaćena. Vratite se na plaćanje i izaberite način koji vam odgovara, na primjer uplatnicu na mejl.
        </p>
        <button type="button" class="btn btn-ember" style="padding:18px 30px;font-size:17px" @click="nazadNaPlacanje">Nazad na registraciju</button>
      </template>

    </div>
  </div>
</template>
