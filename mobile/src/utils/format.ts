/**
 * Deljeni formatteri datuma za HAUS mobile. Bosanski format: dd.mm.gggg.
 * (tačka i na kraju), isti obrazac koji je već korišten lokalno u
 * PretplataAktivnaScreen (ekran 07); izvučeno ovdje da se ne duplira po
 * novim klijentskim ekranima (08-15).
 */

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

/** "09.08.2026." ili null ako je ulaz null/neispravan. */
export function formatDate(iso: string | null | undefined): string | null {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}.`;
}

/** "09.08.2026. 15:00" ili null ako je ulaz null/neispravan. */
export function formatDateTime(iso: string | null | undefined): string | null {
  const datePart = formatDate(iso);
  if (!datePart || !iso) {
    return datePart;
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return datePart;
  }
  return `${datePart} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** "15:00" ili null ako je ulaz null/neispravan. */
export function formatTime(iso: string | null | undefined): string | null {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
