<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();

const form = reactive({ email: '', password: '' });
const submitting = ref(false);
const errorMessage = ref('');

async function onSubmit() {
  errorMessage.value = '';
  submitting.value = true;
  const result = await auth.login(form.email, form.password);
  submitting.value = false;
  if (!result.ok) {
    errorMessage.value = result.message || 'Prijava nije uspjela.';
  }
}
</script>

<template>
  <div style="min-height:100vh;background:var(--ivory);display:flex;justify-content:center;padding:88px 24px 96px">
    <div style="width:100%;max-width:440px">
      <RouterLink to="/" style="display:block;margin-bottom:48px">
        <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="152" height="33">
      </RouterLink>
      <h1 style="font-size:44px;font-weight:700;line-height:1.08;letter-spacing:-0.015em;margin-bottom:10px">Prijava</h1>
      <p style="font-size:17px;font-weight:400;line-height:1.55;color:var(--bark);margin-bottom:36px">Vaš HAUS Karton, intervencije i pretplata.</p>
      <form style="display:flex;flex-direction:column;gap:20px" @submit.prevent="onSubmit">
        <div class="field">
          <label for="p-mail" class="field-label" style="font-size:12px">E-mail</label>
          <input id="p-mail" v-model="form.email" type="email" autocomplete="username" required>
        </div>
        <div class="field">
          <label for="p-pass" class="field-label" style="font-size:12px">Lozinka</label>
          <input id="p-pass" v-model="form.password" type="password" autocomplete="current-password" required>
        </div>
        <p v-if="errorMessage" class="error-panel">{{ errorMessage }}</p>
        <button type="submit" class="btn btn-ember btn-block" style="padding:17px 24px;font-size:17px;margin-top:4px" :disabled="submitting">
          {{ submitting ? 'Prijavljivanje...' : 'Prijavite se' }}
        </button>
      </form>
      <div style="border-top:1px solid var(--sand);margin-top:32px;padding-top:24px;display:flex;flex-direction:column;gap:12px">
        <p style="font-size:15px;font-weight:400;color:var(--bark);line-height:1.5">
          Nemate pretplatu?
          <RouterLink to="/registracija" style="font-weight:600;color:var(--ink)">Registrujte se</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
