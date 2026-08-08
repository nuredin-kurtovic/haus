<script setup>
import SupportChat from './components/SupportChat.vue';
import Toast from './components/Toast.vue';
</script>

<template>
  <router-view />
  <Toast />
  <SupportChat />
</template>
