<script setup>
import { useToastStore } from '../stores/toast';

const toast = useToastStore();
</script>

<template>
  <div
    v-if="toast.message"
    role="status"
    style="position:fixed;left:50%;bottom:32px;transform:translateX(-50%);z-index:100;background:#252422;color:#FFFCF2;padding:16px 20px;display:flex;align-items:center;gap:20px;max-width:calc(100vw - 40px)"
  >
    <span style="font-size:15px;font-weight:400;line-height:1.4">{{ toast.message }}</span>
    <button
      type="button"
      aria-label="Zatvorite obavještenje"
      style="border:1px solid #CCC5B9;background:transparent;color:#FFFCF2;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;flex:none"
      @click="toast.hide()"
    >
      U redu
    </button>
  </div>
</template>
