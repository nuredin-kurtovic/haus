/**
 * Pojednostavljena bosanska pluralizacija za brojeve koji se stvarno
 * javljaju u ovom domenu (1, 2 do 4, 5+: broj izlazaka po paketu, broj
 * stanova u tier rasponima). Nije puno pravilo (pravo pravilo gleda
 * zadnju cifru mod 10 i posebne slučajeve 11 do 14), ali pokriva sve
 * vrijednosti koje se pojavljuju u ovom toku (1, 3, 5 izlazaka po
 * paketu; 2 do 9 stanova).
 */
export function pluralize(
  count: number,
  forms: readonly [one: string, few: string, many: string],
): string {
  if (count === 1) {
    return forms[0];
  }
  if (count >= 2 && count <= 4) {
    return forms[1];
  }
  return forms[2];
}
