<script setup>
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
</script>

<template>
  <div style="min-height:100vh;background:var(--ink);color:var(--ivory);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;gap:20px;text-align:center">
    <h1 style="font-size:36px;font-weight:700;letter-spacing:-0.01em">Dispečer</h1>
    <p style="font-size:17px;font-weight:400;color:var(--sand);max-width:480px">Pregled naloga i dodjela majstora dolaze u sljedećoj fazi.</p>
    <button type="button" class="btn btn-ghost-ivory" @click="auth.logout()">Odjava</button>
  </div>
</template>
