// Formatiranje datuma za klijentsku površinu. Bosanski stil: 07.08.2026.
// Zajednički utils/format.js ne dira klijentska faza pa su ovi mali helperi ovdje.

export function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const dan = String(d.getDate()).padStart(2, '0');
  const mjesec = String(d.getMonth() + 1).padStart(2, '0');
  return `${dan}.${mjesec}.${d.getFullYear()}.`;
}

export function formatDateTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const dan = String(d.getDate()).padStart(2, '0');
  const mjesec = String(d.getMonth() + 1).padStart(2, '0');
  const sat = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${dan}.${mjesec}. ${sat}:${min}`;
}

export function formatWindow(startIso, endIso) {
  if (!startIso || !endIso) return '';
  const start = new Date(startIso);
  const end = new Date(endIso);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return '';
  const dan = String(start.getDate()).padStart(2, '0');
  const mjesec = String(start.getMonth() + 1).padStart(2, '0');
  const hs = String(start.getHours()).padStart(2, '0');
  const ms = String(start.getMinutes()).padStart(2, '0');
  const he = String(end.getHours()).padStart(2, '0');
  const me = String(end.getMinutes()).padStart(2, '0');
  return `${dan}.${mjesec}. ${hs}:${ms}–${he}:${me}`;
}

/** Stanje naloga za StateChip: garancijski nalog uvijek pokazuje "garancija", bez obzira na status. */
export function jobChipState(job) {
  if (job.type === 'garancija') return 'garancija';
  return job.status;
}

const USE_LABELS = {
  zivim: 'Živim u njemu',
  izdaje_se: 'Izdaje se',
  prazan: 'Prazan / dijaspora',
};

export function propertyUseLabel(use) {
  return USE_LABELS[use] || use;
}

const SUBSCRIPTION_STATUS_LABELS = {
  cekanje_uplate: 'Čekanje uplate',
  aktivna: 'Aktivna',
  istekla: 'Istekla',
  otkazana: 'Otkazana',
  ponuda: 'Ponuda',
};

export function subscriptionStatusLabel(status) {
  return SUBSCRIPTION_STATUS_LABELS[status] || status;
}

const INVOICE_TYPE_LABELS = {
  pretplata: 'Pretplata',
  rad: 'Rad i materijal',
};

export function invoiceTypeLabel(type) {
  return INVOICE_TYPE_LABELS[type] || type;
}

const INVOICE_STATUS_LABELS = {
  nenaplaceno: 'Nenaplaćeno',
  placeno: 'Plaćeno',
  refundirano: 'Refundirano',
  djelimicno_refundirano: 'Djelimično refundirano',
  bez_naplate: 'Bez naplate',
};

export function invoiceStatusLabel(status) {
  return INVOICE_STATUS_LABELS[status] || status;
}

export function invoiceStatusChipClass(status) {
  return status === 'placeno' ? 'chip-ink' : '';
}
