<script setup>
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
</script>

<template>
  <div style="min-height:100vh;background:var(--white);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;gap:20px;text-align:center">
    <h1 style="font-size:44px;font-weight:700;letter-spacing:-0.015em">Klijent</h1>
    <p style="font-size:17px;font-weight:400;color:var(--bark);max-width:480px">Početna, prijava kvara i intervencije dolaze u sljedećoj fazi.</p>
    <button type="button" class="btn btn-ghost-ink" @click="auth.logout()">Odjava</button>
  </div>
</template>
