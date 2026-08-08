<script setup>
// AI podrška na javnom i klijentskom dijelu sajta.
// Poruke žive u sessionStorage: prežive navigaciju, ne prežive zatvaranje browsera.
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiPost } from '../api/client';

const STORAGE_KEY = 'haus_support_chat';
const MAX_ISTORIJA = 20;

const UVOD = 'Postavite pitanje o paketima, cijenama ili tome kako HAUS radi.';

const route = useRoute();

// Widget se ne prikazuje u adminu ni u toku plaćanja.
const skriven = computed(() => {
  const path = route.path || '';
  return path.startsWith('/admin') || path.startsWith('/placanje');
});

const otvoren = ref(false);
const salje = ref(false);
const pitanje = ref('');
const poruke = ref(ucitaj());

const tijeloEl = ref(null);
const inputEl = ref(null);

function ucitaj() {
  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function spremi() {
  try {
    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(poruke.value));
  } catch {
    // Privatni prozor bez sessionStorage. Razgovor onda živi samo u memoriji.
  }
}

async function naDno() {
  await nextTick();
  if (tijeloEl.value) tijeloEl.value.scrollTop = tijeloEl.value.scrollHeight;
}

async function prebaci() {
  otvoren.value = !otvoren.value;

  if (otvoren.value) {
    await naDno();
    if (inputEl.value) inputEl.value.focus();
  }
}

// U API ide samo stvarni razgovor, bez balončića sa greškom.
function istorija() {
  return poruke.value
    .filter((p) => !p.greska)
    .slice(-MAX_ISTORIJA)
    .map((p) => ({ role: p.role, content: p.content }));
}

async function posalji() {
  const tekst = pitanje.value.trim();
  if (tekst === '' || salje.value) return;

  poruke.value.push({ role: 'user', content: tekst });
  pitanje.value = '';
  salje.value = true;
  spremi();
  await naDno();

  try {
    const odgovor = await apiPost('/support/chat', { messages: istorija() });
    poruke.value.push({ role: 'assistant', content: odgovor.reply });
  } catch (error) {
    poruke.value.push({
      role: 'assistant',
      content: error.message || 'Podrška trenutno ne radi.',
      greska: true,
    });
  } finally {
    salje.value = false;
    spremi();
    await naDno();
  }
}

watch(poruke, spremi, { deep: true });
</script>

<template>
  <div v-if="!skriven" class="haus-chat">
    <button
      v-if="!otvoren"
      type="button"
      class="haus-chat__dugme"
      aria-label="Otvorite HAUS podršku"
      @click="prebaci"
    >
      <svg width="26" height="26" viewBox="0 0 26 26" fill="none" aria-hidden="true">
        <path
          d="M3 3h20v15H9l-6 5V3z"
          stroke="#FFFCF2"
          stroke-width="2"
          stroke-linecap="square"
          stroke-linejoin="miter"
        />
      </svg>
    </button>

    <section v-if="otvoren" class="haus-chat__panel" aria-label="HAUS podrška">
      <header class="haus-chat__zaglavlje">
        <span class="haus-chat__naslov">HAUS podrška</span>
        <button
          type="button"
          class="haus-chat__zatvori"
          aria-label="Zatvorite podršku"
          @click="prebaci"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M2 2l12 12M14 2L2 14" stroke="#FFFCF2" stroke-width="2" stroke-linecap="square" />
          </svg>
        </button>
      </header>

      <div ref="tijeloEl" class="haus-chat__tijelo" role="log" aria-live="polite">
        <p class="haus-chat__balon haus-chat__balon--ai">{{ UVOD }}</p>

        <template v-for="(poruka, index) in poruke" :key="index">
          <p
            class="haus-chat__balon"
            :class="poruka.role === 'user' ? 'haus-chat__balon--ja' : 'haus-chat__balon--ai'"
          >
            {{ poruka.content }}
            <router-link v-if="poruka.greska" to="/kontakt" class="haus-chat__link">
              Otvorite kontakt formu
            </router-link>
          </p>
        </template>

        <p v-if="salje" class="haus-chat__balon haus-chat__balon--ai haus-chat__kuca" aria-label="Podrška piše odgovor">
          <span class="haus-chat__kvadratic" />
          <span class="haus-chat__kvadratic" />
          <span class="haus-chat__kvadratic" />
        </p>
      </div>

      <form class="haus-chat__red" @submit.prevent="posalji">
        <input
          ref="inputEl"
          v-model="pitanje"
          type="text"
          class="haus-chat__polje"
          maxlength="2000"
          placeholder="Vaše pitanje"
          aria-label="Vaše pitanje"
          :disabled="salje"
        />
        <button
          type="submit"
          class="haus-chat__posalji"
          aria-label="Pošaljite pitanje"
          :disabled="salje || pitanje.trim() === ''"
        >
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M2 9h13M10 4l5 5-5 5" stroke="#FFFCF2" stroke-width="2" stroke-linecap="square" />
          </svg>
        </button>
      </form>
    </section>
  </div>
</template>

<style scoped>
.haus-chat {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 90;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.haus-chat__dugme {
  width: 56px;
  height: 56px;
  border: 0;
  background: #252422;
  color: #fffcf2;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.haus-chat__panel {
  width: 360px;
  max-width: calc(100vw - 32px);
  max-height: 70vh;
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #252422;
}

.haus-chat__zaglavlje {
  flex: none;
  background: #252422;
  color: #fffcf2;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.haus-chat__naslov {
  font-size: 15px;
  font-weight: 600;
}

.haus-chat__zatvori {
  border: 0;
  background: transparent;
  padding: 4px;
  display: flex;
  cursor: pointer;
}

.haus-chat__tijelo {
  flex: 1 1 auto;
  overflow-y: auto;
  background: #ffffff;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.haus-chat__balon {
  margin: 0;
  padding: 10px 12px;
  border: 1px solid #ccc5b9;
  font-size: 15px;
  line-height: 1.45;
  max-width: 85%;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.haus-chat__balon--ai {
  background: #ffffff;
  color: #252422;
  align-self: flex-start;
}

.haus-chat__balon--ja {
  background: #fffcf2;
  color: #252422;
  align-self: flex-end;
}

.haus-chat__link {
  display: block;
  margin-top: 6px;
  color: #252422;
  font-weight: 600;
}

.haus-chat__kuca {
  display: flex;
  align-items: center;
  gap: 6px;
}

.haus-chat__kvadratic {
  width: 8px;
  height: 8px;
  background: #252422;
  animation: haus-chat-puls 1.2s infinite steps(1, end);
}

.haus-chat__kvadratic:nth-child(2) {
  animation-delay: 0.4s;
}

.haus-chat__kvadratic:nth-child(3) {
  animation-delay: 0.8s;
}

@keyframes haus-chat-puls {
  0% {
    opacity: 1;
  }
  33% {
    opacity: 0.35;
  }
  66% {
    opacity: 0.35;
  }
  100% {
    opacity: 1;
  }
}

.haus-chat__red {
  flex: none;
  display: flex;
  gap: 8px;
  padding: 12px;
  background: #ffffff;
  border-top: 1px solid #ccc5b9;
}

.haus-chat__polje {
  flex: 1 1 auto;
  min-width: 0;
  border: 1px solid #ccc5b9;
  background: #ffffff;
  color: #252422;
  padding: 10px 12px;
  font-family: inherit;
  font-size: 15px;
}

.haus-chat__polje:disabled {
  color: #ccc5b9;
}

.haus-chat__posalji {
  flex: none;
  width: 44px;
  height: 44px;
  border: 0;
  background: #fe5100;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.haus-chat__posalji:disabled {
  cursor: default;
  opacity: 0.5;
}

@media (max-width: 520px) {
  .haus-chat {
    right: 0;
    bottom: 0;
    left: 0;
  }

  .haus-chat__dugme {
    margin: 0 16px 16px 0;
  }

  .haus-chat__panel {
    width: 100%;
    max-width: 100%;
    max-height: 80vh;
    border-left: 0;
    border-right: 0;
    border-bottom: 0;
  }
}
</style>
