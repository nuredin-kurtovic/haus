/**
 * TypeScript tipovi za HAUS REST API entitete.
 *
 * Izvor: docs/API.md (ugovor između weba i mobile) PLUS živa provjera
 * protiv `php artisan serve` (lead review, avgust 2026): docs/API.md ne
 * navodi tačna imena polja, a stvarni JSON iz Laravel resursa se
 * razlikovao od prve verzije ovog fajla (npr. Package nema `price`/
 * `features`, ima `price_year`/`visits_per_year`/...; VolumeDiscountTier
 * koristi `min/max/pct`, ne `min_properties/max_properties/discount_percent`).
 * Polja obilježena "nepotvrđeno" nisu viđena u živom odgovoru, ostavljena
 * su kao best-effort pretpostavka. Backend je jedini izvor istine; ako se
 * ugovor promijeni, ažurirati ovaj fajl i ponovo provjeriti curl-om.
 */

// ---------------------------------------------------------------------------
// Zajednički / pomoćni tipovi
// ---------------------------------------------------------------------------

export type Role = 'klijent' | 'dispecer';

export type PaymentMethod = 'uplatnica' | 'kartica';

/** Job lifecycle status, iz design/README.md "Job lifecycle". */
export type JobStatus = 'novo' | 'zakazano' | 'u_toku' | 'zavrseno';

/** Garancija je poseban, nefakturisan tip naloga, ne status. */
export type JobType = 'redovan' | 'garancija';

export type CityStatus = 'aktivan' | 'u_pripremi' | 'pauziran';

export type SurchargeType = 'percent' | 'per_km' | 'flat';

/**
 * Nepotvrđeno: jedini uzorak koji smo vidjeli je "zivim" (default kad se
 * `use` ne šalje na registraciji). Web prototip ima tri opcije (Izdaje se
 * / Prazan, dijaspora / Živim u njemu) ali njihove stvarne enum vrijednosti
 * nisu curl-om potvrđene, pa je tip ovdje otvoren (string) da ne tvrdi
 * pogrešan ugovor.
 */
export type PropertyUse = string;

/** Puni slug sa "haus-" prefiksom, potvrđeno curl-om na GET /packages. */
export type PackageSlug = 'haus-mini' | 'haus-plus' | 'haus-pro';

/**
 * Status pretplate. "cekanje_uplate" i "ponuda" potvrđeni curl-om (POST
 * /auth/register). "aktivna" nije živo viđen (Monri webhook još ne
 * postoji, čak i "kartica" plaćanje vraća "cekanje_uplate" dok se
 * simulirano plaćanje ne završi), ali je nužan za kasnije stanje kad
 * uplata legne ili webhook stigne.
 */
export type SubscriptionStatus = 'cekanje_uplate' | 'aktivna' | 'ponuda';

export interface ApiListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiListLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

/** Laravel standard paginacija oblik: {data, links, meta}. */
export interface ApiListResponse<T> {
  data: T[];
  links: ApiListLinks;
  meta: ApiListMeta;
}

/**
 * Sve javne liste (packages, cities, price-list, surcharges) i
 * settings/public su omotane u `{data: ...}` BEZ links/meta (potvrđeno
 * curl-om), za razliku od paginiranih admin listi (ApiListResponse gore).
 */
export interface ApiDataEnvelope<T> {
  data: T;
}

/** 422 odgovor sa greškama po polju, poruke na bosanskom. */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

// ---------------------------------------------------------------------------
// Gradovi
// ---------------------------------------------------------------------------

export interface City {
  id: number;
  name: string;
  slug: string;
  lat: number;
  lng: number;
  status: CityStatus;
}

// ---------------------------------------------------------------------------
// Paketi
// ---------------------------------------------------------------------------

/** Ključevi potvrđeni curl-om: min/max/pct, NE min_properties/discount_percent. */
export interface VolumeDiscountTier {
  min: number;
  max: number | null;
  pct: number;
}

/**
 * GET /packages shape, curl-om potvrđeno (avgust 2026). Nema `price`,
 * `unit`, `audience`, `features`, `remaining_*`: to je bila pogrešna
 * pretpostavka prve verzije ovog fajla. Cijena, popusti i rokovi su
 * numerički parametri paketa; klijent ih spaja u prikaz kartice
 * (screens/registracija/IzborPaketaScreen.tsx), ne čita gotovu rečenicu.
 */
export interface Package {
  id: number;
  name: string;
  slug: PackageSlug;
  /** Cijena u KM. Za Pro je "po stanu godišnje". */
  price_year: number;
  visits_per_year: number;
  deadline_hours: number;
  emergency_deadline_hours: number;
  emergency_included: boolean;
  labor_discount_pct: number;
  material_discount_pct: number;
  inspections_per_year: number;
  warranty_months: number;
  /** Pro je "po stanu"; ovo je pouzdaniji test od provjere slug-a. */
  is_per_apartment: boolean;
  sort: number;
  /** Prisutno na svim paketima, prazan niz za Mini/Plus. */
  volume_discount_tiers: VolumeDiscountTier[];
}

// ---------------------------------------------------------------------------
// Cjenovnik
// ---------------------------------------------------------------------------

/** Ključevi su slug bez "haus-" prefiksa (docs/API.md "Konvencije"). */
export interface PriceItemPrices {
  mini: number;
  plus: number;
  pro: number;
}

export interface PriceItem {
  id: number;
  name: string;
  unit: string;
  /** Osnovna cijena bez pretplate. */
  base_price: number;
  /** Izračunate cijene po paketu, uvijek sa servera. */
  prices: PriceItemPrices;
  /** Samo na /client/price-list: cijena za ulogovanog klijenta. */
  my_price?: number;
}

export interface PriceCategory {
  id: number;
  name: string;
  slug: string;
  icon: string;
  items: PriceItem[];
}

export interface Surcharge {
  key: string;
  label: string;
  type: SurchargeType;
  value: number;
}

/** GET /settings/public shape, curl-om potvrđeno. */
export interface PublicSettings {
  radno_vrijeme: {
    pon_pet: { od: string; do: string };
    subota: { od: string; do: string };
    nedjelja: { samo_hitno: boolean };
    napomena: string;
  };
  satnica_redovna: number;
  satnica_hitna: number;
  izlazak_bez_pretplate: number;
  ukljuceno_minuta: number;
  materijal_marza_pct: number;
  price_list_version: number;
}

// ---------------------------------------------------------------------------
// Korisnik, pretplata
// ---------------------------------------------------------------------------

/**
 * Curl-om potvrđeno na /auth/login, /me i /auth/register: User NEMA
 * `role` (role putuje odvojeno, vidi LoginResponse/MeResponse), notif
 * flagovi su ravni (notif_push/notif_email/notif_marketing), ne
 * ugniježđeni `notifications: {...}` kako je prva verzija pretpostavila.
 */
export interface User {
  id: number;
  name: string;
  email: string;
  notif_push: boolean;
  notif_email: boolean;
  notif_marketing: boolean;
  /** Nepotvrđeno u uzorku (nije bilo u odgovoru); ostavljeno opciono. */
  phone?: string | null;
}

/** Slim oblik paketa ugniježđen u Subscription (id/name/slug/is_per_apartment
 * samo), NE puni Package sa cijenama/rokovima. Curl-om potvrđeno. */
export interface SubscriptionPackageSummary {
  id: number;
  name: string;
  slug: PackageSlug;
  is_per_apartment: boolean;
}

/** subscription.properties[] iz POST /auth/register, curl-om potvrđeno. */
export interface SubscriptionPropertyResult {
  id: number;
  city_id: number;
  city: { id: number; name: string };
  street: string;
  use: PropertyUse;
  contact_name: string | null;
  contact_note: string | null;
  remaining_visits: number;
  remaining_inspections: number;
}

export interface SubscriptionInvoice {
  id: number;
  number: string;
  /** Viđeno: "pretplata". Vjerovatno i "posao"/"garancija" za nalog fakture. */
  type: string;
  /** Viđeno: "nenaplaceno". Nepotvrđeno kako izgleda plaćeni status. */
  status: string;
  total: number;
  paid_at: string | null;
}

export interface SubscriptionPaymentHistoryEntry {
  id: number;
  date: string;
  amount: number;
  method: PaymentMethod;
  status: 'placeno' | 'neplaceno';
}

/**
 * Curl-om potvrđeno na POST /auth/register. `free_interventions` je
 * runtime kredit (kreće od 0, sistem ga automatski dodaje kad rok
 * padne, design/README.md "Job lifecycle"), NIJE statični parametar
 * paketa: prva verzija ovog fajla je to pogrešno stavila na Package.
 */
export interface Subscription {
  id: number;
  status: SubscriptionStatus;
  package: SubscriptionPackageSummary;
  starts_at: string | null;
  ends_at: string | null;
  auto_renew: boolean;
  /** Ukupna godišnja cijena pretplate, server-side izračunata (uklj. Pro popust). */
  price: number;
  price_paid: number | null;
  free_interventions: number;
  remaining_visits: number;
  remaining_inspections: number;
  properties: SubscriptionPropertyResult[];
  /** Nepotvrđeno curl-om (GET /client/subscription nije testiran u ovoj fazi). */
  payment_history?: SubscriptionPaymentHistoryEntry[];
}

// ---------------------------------------------------------------------------
// Nalozi (jobs)
// ---------------------------------------------------------------------------

export interface JobStep {
  label: string;
  done: boolean;
}

export interface JobPhoto {
  type: 'pre' | 'posle';
  url: string;
}

export interface InvoiceLaborItem {
  price_item_id: number;
  name: string;
  qty: number;
  price: number;
  total: number;
}

export interface InvoiceMaterialItem {
  name: string;
  purchase_price: number;
  qty: number;
  total: number;
}

export interface Invoice {
  labor_items: InvoiceLaborItem[];
  materials: InvoiceMaterialItem[];
  labor_total: number;
  material_total: number;
  total: number;
}

export interface JobListItem {
  id: number;
  number: string;
  status: JobStatus;
  type: JobType;
  category: string;
  title: string;
  technician_name: string | null;
  scheduled_window: { start: string; end: string } | null;
  warranty_until: string | null;
}

export interface JobDetail extends JobListItem {
  description: string;
  is_emergency: boolean;
  deadline_at: string;
  steps: JobStep[];
  findings: string | null;
  photos: JobPhoto[];
  invoice: Invoice | null;
}

export interface ActiveJobSummary {
  id: number;
  number: string;
  status: JobStatus;
  steps: JobStep[];
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

/**
 * Nepotvrđeno curl-om (klijent tab ekrani su van scope-a ove faze).
 * Oblik pretplate ovdje je sveden na isti Subscription tip radi
 * dosljednosti (package je slim summary, ne puni Package sa cijenama).
 */
export interface ClientDashboard {
  subscription: Pick<
    Subscription,
    'package' | 'ends_at' | 'remaining_visits' | 'free_interventions' | 'remaining_inspections'
  >;
  active_job: ActiveJobSummary | null;
  recent_jobs: JobListItem[];
}

// ---------------------------------------------------------------------------
// Tehničari
// ---------------------------------------------------------------------------

export interface Technician {
  id: number;
  name: string;
  trade: string;
  active: boolean;
}

// ---------------------------------------------------------------------------
// Auth request / response oblici
// ---------------------------------------------------------------------------

export interface RegisterPropertyInput {
  city_id: number;
  street: string;
  use?: PropertyUse;
  /**
   * Nepotvrđeno kao INPUT (samo output oblik `contact_name`/
   * `contact_note` je curl-om viđen na subscription.properties[]; mobile
   * ekrani ne šalju ova polja u ovoj fazi, drži se optional/best-effort).
   */
  contact_name?: string;
  contact_note?: string;
}

export interface RegisterRequest {
  package_id: number;
  name: string;
  email: string;
  password: string;
  payment_method: PaymentMethod;
  properties: RegisterPropertyInput[];
}

/**
 * Curl-om potvrđeno (POST /auth/register, 201). `status` je uvijek
 * prisutan (ogleda subscription.status), ne samo za "ponuda" kako je
 * prva verzija ovog fajla pretpostavila. `invoice` nedostaje samo za
 * "ponuda" (Pro 10+, nema fakture prije nego dispečer napravi ponudu).
 */
export interface RegisterResponse {
  status: SubscriptionStatus;
  user: User;
  subscription: Subscription;
  token: string;
  /** Prisutno kad je payment_method kartica (Monri redirect, fake za sada). */
  payment?: { redirect_url: string };
  invoice?: SubscriptionInvoice;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  user: User;
  token: string;
  role: Role;
}

/**
 * Curl-om potvrđeno na GET /me: `subscription` je `null` (ne izostavljeno
 * polje) kad korisnik nema aktivnu pretplatu, npr. dok "cekanje_uplate"
 * traje. Prva verzija je koristila `subscription?:`, što ne hvata `null`.
 */
export interface MeResponse {
  user: User;
  role: Role;
  subscription: Pick<
    Subscription,
    'package' | 'ends_at' | 'remaining_visits'
  > | null;
}

// ---------------------------------------------------------------------------
// Fault report (Prijavi kvar)
// ---------------------------------------------------------------------------

export interface CreateJobRequest {
  price_category_id: number;
  description: string;
  is_emergency: boolean;
  preferred_window: string;
  subscription_property_id?: number;
  /** Multipart, opciono. */
  photo?: string;
}

export interface CreateJobResponse {
  job: {
    id: number;
    number: string;
    deadline_at: string;
  };
}
