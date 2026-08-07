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

/**
 * "10:00–12:00" (en dash je dozvoljen u numeričkim rasponima, CLAUDE.md),
 * za prikaz prozora izlaska (ekrani serviser/dispečer). `null` kad bar
 * jedno polje nedostaje (nalog još nema termin).
 */
export function formatWindowRange(
  startIso: string | null | undefined,
  endIso: string | null | undefined,
): string | null {
  const start = formatTime(startIso);
  const end = formatTime(endIso);
  if (!start || !end) {
    return null;
  }
  return `${start}–${end}`;
}

function isSameLocalDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/**
 * "Danas" / "Sutra" / "09.08.2026." za grupisanje po datumu prozora
 * (serviserova lista naloga, ekran "Moji nalozi"). Nalog bez prozora vraća
 * "Bez termina".
 *
 * NAPOMENA na tačnost sata: server čuva/vraća vremena u UTC (config/app.php
 * "timezone" => "UTC", app.md potvrđeno), a termin se upisuje kao "zidni"
 * sat (npr. dispečer upiše 10:00, to se čuva kao 10:00 UTC, ne konvertuje
 * se iz BiH lokalnog vremena). `new Date(iso)` + lokalni getters (ovdje i u
 * formatTime/formatDate iznad) prikazuju vrijeme u TIMEZONE UREĐAJA, pa na
 * uređaju čiji se sistemski sat razlikuje od UTC (npr. BiH ljetno vrijeme,
 * UTC+2) prikazani sat neće odgovarati satu koji je dispečer upisao. Ovo je
 * postojeći obrazac u cijelom kodu (formatTime/formatDateTime, korišteni
 * već u NalogDetaljScreen/PocetnaScreen), ne nova greška ovdje: zadržano
 * dosljedno sa ostatkom aplikacije, dokumentovano i u finalnom izvještaju
 * kao otvoreno pitanje za sljedeću fazu (ili postaviti uređaj/emulator na
 * UTC dok se ne riješi sistemski, ili server treba čuvati/vraćati BiH
 * lokalno vrijeme umjesto UTC za "zidni sat" polja kao scheduled_window_*).
 */
export function dateGroupLabel(iso: string | null | undefined): string {
  if (!iso) {
    return 'Bez termina';
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return 'Bez termina';
  }
  const today = new Date();
  if (isSameLocalDay(date, today)) {
    return 'Danas';
  }
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  if (isSameLocalDay(date, tomorrow)) {
    return 'Sutra';
  }
  return formatDate(iso) ?? 'Bez termina';
}
