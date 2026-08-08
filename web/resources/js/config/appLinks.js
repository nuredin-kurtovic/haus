// Linkovi na prodavnice aplikacija, na jednom mjestu.
// Aplikacije JOŠ NISU objavljene: oba linka su prazna dok ne dobijemo finalne
// store URL-ove. AppPromo.vue automatski prelazi sa toast obavještenja na
// prave linkove čim se ovdje upiše URL, bez izmjena po stranicama.
export const APP_STORE_URL = '';
export const PLAY_STORE_URL = '';

export function hasStoreLinks() {
  return Boolean(APP_STORE_URL) || Boolean(PLAY_STORE_URL);
}
