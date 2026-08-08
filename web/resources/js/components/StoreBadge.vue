<script setup>
// Store badge (App Store / Google Play) kao kompletan inline SVG.
// SVG rect rx nosi zaobljenje badge-a: to je Apple/Google artwork po njihovim
// smjernicama, ne nas UI element, pa ga globalno border-radius: 0 ne dira.
defineProps({
  store: { type: String, required: true }, // 'apple' | 'google'
  height: { type: Number, default: 52 },
});
</script>

<template>
  <svg
    v-if="store === 'apple'"
    :height="height"
    :width="height * 3.25"
    viewBox="0 0 130 40"
    role="img"
    aria-label="Preuzmite u App Storeu"
  >
    <rect x="0.5" y="0.5" width="129" height="39" rx="7" fill="#000000" stroke="#A6A6A6" stroke-width="1" />
    <g transform="translate(10, 6.6) scale(0.0268)">
      <path
        fill="#FFFFFF"
        d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76.5 0-103.7 40.8-165.9 40.8s-105.6-57-155.5-127C46.7 790.7 0 663 0 541.8c0-194.4 126.4-297.5 250.8-297.5 66.1 0 121.2 43.4 162.7 43.4 39.5 0 101.1-46 176.3-46 28.5 0 130.9 2.6 198.3 99.2zm-234-181.5c31.1-36.9 53.1-88.1 53.1-139.3 0-7.1-.6-14.3-1.9-20.1-50.6 1.9-110.8 33.7-147.1 75.8-28.5 32.4-55.1 83.6-55.1 135.5 0 7.8 1.3 15.6 1.9 18.1 3.2.6 8.4 1.3 13.6 1.3 45.4 0 102.5-30.4 135.5-71.3z"
      />
    </g>
    <text x="38" y="16" fill="#FFFFFF" font-size="8.5" font-weight="400" font-family="Poppins, sans-serif">Preuzmite u</text>
    <text x="38" y="30.5" fill="#FFFFFF" font-size="14.5" font-weight="600" font-family="Poppins, sans-serif">App Storeu</text>
  </svg>

  <svg
    v-else
    :height="height"
    :width="height * 3.375"
    viewBox="0 0 135 40"
    role="img"
    aria-label="Preuzmite na Google Playu"
  >
    <rect x="0.5" y="0.5" width="134" height="39" rx="7" fill="#000000" stroke="#A6A6A6" stroke-width="1" />
    <g transform="translate(10, 7) scale(0.68)">
      <path fill="#00C4FF" d="M1.1.9C.7 1.3.5 1.9.5 2.7v32.6c0 .8.2 1.4.6 1.8l.1.1L19.4 19.2v-.4L1.2.8z" />
      <path fill="#FFCE00" d="M25.4 25.3l-6-6.1v-.4l6-6.1.1.1 7.2 4.1c2.1 1.2 2.1 3.1 0 4.3l-7.2 4.1z" />
      <path fill="#FF5063" d="M25.6 25.2l-6.2-6.2L1.1 37.2c.7.7 1.8.8 3.1.1l21.4-12.1" />
      <path fill="#00EE7A" d="M25.6 12.8L4.2 .7C2.9 0 1.8.1 1.1.8l18.3 18.2 6.2-6.2z" />
    </g>
    <text x="40" y="15.5" fill="#FFFFFF" font-size="7.5" font-weight="500" letter-spacing="0.06em" font-family="Poppins, sans-serif">PREUZMITE NA</text>
    <text x="40" y="30.5" fill="#FFFFFF" font-size="14.5" font-weight="600" font-family="Poppins, sans-serif">Google Play</text>
  </svg>
</template>
