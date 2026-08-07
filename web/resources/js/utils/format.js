// Pomoćne funkcije za bosanske množinske oblike i prikaz podataka sa API-ja.
// Cifre koje ove funkcije vrate treba prikazati u elementu sa klasom .num
// (font-variant-numeric: tabular-nums je već pravilo iz tokens.css).

/**
 * Slavenski oblik množine za riječi tipa "sat/sata/sati", "mjesec/mjeseca/mjeseci".
 * Vraća indeks: 0 = jednina (1), 1 = malo (2-4), 2 = mnogo (5+, i sve na 11-14).
 */
function pluralIndex(n) {
  const mod100 = n % 100;
  const mod10 = n % 10;
  if (mod100 >= 11 && mod100 <= 14) return 2;
  if (mod10 === 1) return 0;
  if (mod10 >= 2 && mod10 <= 4) return 1;
  return 2;
}

function pluralWord(n, forms) {
  return `${n} ${forms[pluralIndex(n)]}`;
}

export const sati = (n) => pluralWord(n, ['sat', 'sata', 'sati']);
export const mjeseci = (n) => pluralWord(n, ['mjesec', 'mjeseca', 'mjeseci']);
export const grada = (n) => pluralWord(n, ['grad', 'grada', 'gradova']);
export const intervencije = (n) => pluralWord(n, ['uključena intervencija', 'uključene intervencije', 'uključenih intervencija']);
export const stanova = (n) => pluralWord(n, ['stan', 'stana', 'stanova']);

/**
 * Naziva niz gradova kao rečenicu: "Sarajevo", "Sarajevo i Travnik", "Sarajevo, Travnik i Zenica".
 */
export function nabrojiGradove(names) {
  if (names.length === 0) return '';
  if (names.length === 1) return names[0];
  return `${names.slice(0, -1).join(', ')} i ${names[names.length - 1]}`;
}

/**
 * Kratki opis paketa izveden iz brojčanih atributa sa /packages, bez izmišljenih polja.
 */
export function paketBullets(pkg) {
  const bullets = [
    intervencije(pkg.visits_per_year),
    `${pkg.labor_discount_pct}% popusta na rad iznad uključenog`,
    `Rok izlaska ${sati(pkg.deadline_hours)}`,
    `Hitno ${sati(pkg.emergency_deadline_hours)}, ${pkg.emergency_included ? 'bez doplate' : 'uz doplatu'}`,
  ];
  if (pkg.inspections_per_year > 0) {
    bullets.push(`Godišnji pregled instalacija ${pkg.inspections_per_year}×`);
  }
  bullets.push(`Garancija na rad ${mjeseci(pkg.warranty_months)}`);
  if (pkg.material_discount_pct > 0) {
    bullets.push(`${pkg.material_discount_pct}% popusta na materijal`);
  }
  return bullets;
}

const PAKET_AUDIENCE = {
  'haus-mini': 'Da imate kome prijaviti kvar. Jedan izlazak i cijena bez pregovaranja.',
  'haus-plus': 'Za domaćinstvo koje ne želi tražiti majstora.',
  'haus-pro': 'Stan radi i kad vas nema. Gost prijavi nama, vi dobijete izvještaj.',
};

export function paketOpis(pkg) {
  return PAKET_AUDIENCE[pkg.slug] || 'Održavanje doma na godišnju pretplatu.';
}

export function paketJedinica(pkg) {
  return pkg.is_per_apartment ? 'god po stanu' : 'god';
}

/**
 * Puna riječ za jedinicu cijene, za registracioni tok (tile i sažetak).
 */
export function paketJedinicaGodisnje(pkg) {
  return pkg.is_per_apartment ? 'po stanu godišnje' : 'godišnje';
}

/**
 * Jedan hairline red sažetka na tile-u paketa u registraciji, izveden iz
 * brojčanih atributa sa /packages, bez izmišljenih podataka.
 */
export function paketKratko(pkg) {
  const rok = `Rok ${sati(pkg.deadline_hours)}`;
  if (pkg.is_per_apartment) {
    return `${intervencije(pkg.visits_per_year)}. ${rok}. Po stanu.`;
  }
  return `${intervencije(pkg.visits_per_year)}. ${rok}. Popust ${pkg.labor_discount_pct}%.`;
}

/**
 * Popust na količinu za HAUS Pro iz volume_discount_tiers sa /packages.
 * Isti algoritam kao PriceCalculator::volumeDiscountPct na serveru.
 */
export function popustNaKolicinu(apartments, tiers) {
  for (const tier of tiers || []) {
    const min = Number(tier.min ?? 0);
    const max = tier.max === null || tier.max === undefined ? null : Number(tier.max);
    if (apartments >= min && (max === null || apartments <= max)) {
      return Number(tier.pct ?? 0);
    }
  }
  return 0;
}

/**
 * Godišnja cijena HAUS Pro pretplate, ista formula kao PriceCalculator::subscriptionTotal.
 * Samo za orijentacioni prikaz prije uplate; serverski total iz responsa je izvor istine.
 */
export function proTotal(priceYear, apartments, tiers) {
  const gross = priceYear * apartments;
  const pct = popustNaKolicinu(apartments, tiers);
  return Math.round(gross * (1 - pct / 100));
}

/**
 * Radno vrijeme kao rečenica, iz /settings/public.radno_vrijeme.
 */
export function radnoVrijeme(rv) {
  if (!rv || !rv.pon_pet || !rv.subota) return '';
  const dio = `Radnim danima ${rv.pon_pet.od}–${rv.pon_pet.do}, subotom ${rv.subota.od}–${rv.subota.do}.`;
  return rv.napomena ? `${dio} ${rv.napomena}` : dio;
}
