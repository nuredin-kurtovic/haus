<script setup>
import { computed } from 'vue';

// Mapping stanja naloga na chip stil. Isti mapping koristi i admin faza,
// pa se ovaj fajl ne mijenja bez razloga koji vrijedi za oba surface-a.
// Izvor: design/README.md, "State chip styling" i docs/API.md konvencije.
const STYLES = {
  novo: { border: 'var(--ink)', bg: 'var(--white)', color: 'var(--ink)', label: 'Novo' },
  zakazano: { border: 'var(--bark)', bg: 'var(--ivory)', color: 'var(--bark)', label: 'Zakazano' },
  u_toku: { border: 'var(--ember)', bg: 'var(--ember)', color: 'var(--ivory)', label: 'U toku' },
  zavrseno: { border: 'var(--sand)', bg: 'var(--sand)', color: 'var(--ink)', label: 'Završeno' },
  garancija: { border: 'var(--ink)', bg: 'var(--ink)', color: 'var(--ivory)', label: 'Garancija' },
};

const props = defineProps({
  // novo | zakazano | u_toku | zavrseno | garancija
  state: { type: String, required: true },
  label: { type: String, default: '' },
});

const style = computed(() => STYLES[props.state] || STYLES.novo);
</script>

<template>
  <span
    class="num"
    :style="{
      display: 'inline-flex',
      alignItems: 'center',
      border: `1px solid ${style.border}`,
      background: style.bg,
      color: style.color,
      padding: '4px 9px',
      fontSize: '12px',
      fontWeight: 600,
      letterSpacing: '.04em',
      whiteSpace: 'nowrap',
    }"
  >{{ label || style.label }}</span>
</template>
