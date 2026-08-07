<template>
  <div style="min-height:100vh;background:var(--ivory);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;gap:20px;text-align:center">
    <RouterLink to="/" style="display:block">
      <img :src="'/assets/logo-primary.svg'" alt="HAUS" width="152" height="33">
    </RouterLink>
    <h1 style="font-size:44px;font-weight:700;letter-spacing:-0.015em">Registracija</h1>
    <p style="font-size:17px;font-weight:400;color:var(--bark);max-width:480px">Izbor paketa i podaci dolaze u sljedećoj fazi.</p>
    <RouterLink to="/cijene" style="font-size:15px;font-weight:600">Pogledajte pakete</RouterLink>
  </div>
</template>
