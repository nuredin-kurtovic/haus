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
 *
 * DRUGI KRUG VERIFIKACIJE (klijentski ekrani 08-15, avgust 2026): curl na
 * /client/dashboard, /client/jobs (lista+detalj, uklj. POST za novi nalog),
 * /client/subscription (Mini/Plus i Pro korisnik), /client/price-list,
 * /client/profile (GET+PUT), /client/address-change-request,
 * /client/subscription/cancel, /me, PLUS čitanje izvornog PHP koda
 * (JobListResource, JobDetailResource, JobInvoiceResource,
 * DashboardController, SubscriptionController, enum fajlovi) jer
 * `php artisan route:list` pokazuje da admin/tehničar rute JOŠ NE POSTOJE
 * (samo /admin/ping), pa se nalog ne može voditi kroz cio lifecycle do
 * "zavrseno" da bi se `findings`/`photos`/`invoice` vidjeli popunjeni
 * uživo. Njihov oblik je potvrđen ČITANJEM resource klasa (izvor istine),
 * ne curl-om na popunjen primjer; to je obilježeno ispod gdje je bitno.
 *
 * Otkriveno da je prva verzija ovog fajla (napravljena za registracioni
 * tok) na više mjesta pogrešna za klijentske ekrane:
 * - JobType je "redovno" (ne "redovan"), i postoji treća vrijednost
 *   "pregled" (godišnji pregled instalacija, enum JobType.php).
 * - JobPhoto.type je "prije"/"poslije" (ne "pre"/"posle").
 * - JobStep ima i `key` (ne samo label/done), Job::steps() u PHP-u.
 * - JobListItem/JobDetail oblik iz JobListResource/JobDetailResource se
 *   znatno razlikuje od prve verzije: `technician` je objekat {name}|null
 *   (ne `technician_name` string), prozor termina su dva ravna polja
 *   `scheduled_window_start`/`scheduled_window_end` (ne ugniježđeni
 *   `scheduled_window` objekat), nalog ima i `description`/`is_emergency`/
 *   `created_at`/`deadline_missed_at` koje prva verzija nije imala.
 * - Invoice (JobInvoiceResource) NEMA `price_item_id`/`price` na stavkama
 *   rada niti `purchase_price` na materijalu: samo `name`, `qty`,
 *   `line_total`. Ima i `id`/`number`/`status`/`paid_at` na vrhu.
 * - ClientDashboard.recent_jobs NIJE puni JobListItem: DashboardController
 *   vraća samo {id, number, status, type, category, created_at}, bez
 *   title/description/technician/itd. Zato postoji zaseban
 *   RecentJobSummary tip.
 * - SubscriptionStatus ima i "istekla" i "otkazana" (enum
 *   SubscriptionStatus.php), prva verzija je imala samo tri od pet.
 * - /client/subscription vraća properties[] sa `city` kao RAVAN STRING
 *   (ime grada), za razliku od registracionog odgovora gdje je `city`
 *   ugniježđen objekat {id, name}. Ovo su DVA razdvojena tipa ispod
 *   (SubscriptionPropertyResult za registraciju, ClientSubscriptionProperty
 *   za /client/subscription), ne jedan.
 * - /client/subscription.payments red je {number, type, total, status,
 *   paid_at, created_at}: prava verzija stare SubscriptionPaymentHistoryEntry
 *   (id/date/amount/method/status 'placeno'|'neplaceno') nije postojala
 *   nigdje u živom odgovoru.
 * - /me.subscription ima i `id`, `status`, `properties_count` koje prva
 *   verzija (Pick<Subscription,...>) nije pokrivala.
 */

// ---------------------------------------------------------------------------
// Zajednički / pomoćni tipovi
// ---------------------------------------------------------------------------

export type Role = 'klijent' | 'dispecer' | 'majstor';

export type PaymentMethod = 'uplatnica' | 'kartica';

/** Job lifecycle status, iz design/README.md "Job lifecycle". */
export type JobStatus = 'novo' | 'zakazano' | 'u_toku' | 'zavrseno';

/**
 * Tip naloga, ne status. Curl-om + enum JobType.php potvrđeno: "redovno"
 * (ne "redovan"), plus "pregled" (godišnji pregled instalacija, i on je
 * nefakturisan kao garancija).
 */
export type JobType = 'redovno' | 'garancija' | 'pregled';

/**
 * Ispravljeno (serviser/dispečer faza, avgust 2026): enum CityStatus.php
 * (čitanjem izvora, web/app/Enums/CityStatus.php) ima SAMO dvije
 * vrijednosti, "aktivan" i "u_pripremi". Prethodna verzija ovog tipa je
 * imala i "pauziran", koje ne postoji nigdje na backendu (ni u enumu, ni u
 * CityController); nije korišteno nigdje u kodu (grep prije izmjene), pa je
 * uklanjanje sigurno. Gradovi ekran (19) prebacuje grad tačno između ova
 * dva stanja, isto kao web prototip (design/README.md "Gradovi": "novi
 * grad ide u pripremi").
 */
export type CityStatus = 'aktivan' | 'u_pripremi';

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
 * /auth/register); "aktivna" curl-om potvrđeno na seeded klijentu
 * (klijent@haus.ba, avgust 2026, drugi krug verifikacije). "istekla" i
 * "otkazana" nisu živo viđeni (traže vremenski istek ili poseban tok), ali
 * su potvrđeni ČITANJEM enum SubscriptionStatus.php: prva verzija ovog
 * tipa je imala samo tri od pet vrijednosti.
 */
export type SubscriptionStatus = 'cekanje_uplate' | 'aktivna' | 'istekla' | 'otkazana' | 'ponuda';

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

/**
 * GET /client/price-list, curl-om potvrđeno (drugi krug verifikacije):
 * isti `data` oblik kao javni /price-list (PriceCategory[]), plus `meta`
 * koji javni odgovor nema (`price_list_version` i `my_package`).
 */
export interface ClientPriceListMeta {
  price_list_version: number;
  my_package: {
    name: string;
    slug: PackageSlug;
    labor_discount_pct: number;
    material_discount_pct: number;
  };
}

export interface ClientPriceListResponse {
  data: PriceCategory[];
  meta: ClientPriceListMeta;
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

/** Vrsta fakture, enum InvoiceType.php (čitanjem izvora potvrđeno). */
export type InvoiceType = 'pretplata' | 'rad';

/** Stanje fakture, enum InvoiceStatus.php (čitanjem izvora potvrđeno). */
export type InvoiceStatus =
  | 'nenaplaceno'
  | 'placeno'
  | 'refundirano'
  | 'djelimicno_refundirano'
  | 'bez_naplate';

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

/**
 * Red u GET /client/subscription.payments, curl-om potvrđeno (avgust
 * 2026, drugi krug). Nema `id`/`date`/`amount`/`method`: to je bila
 * pogrešna pretpostavka prve verzije (SubscriptionPaymentHistoryEntry).
 * Server šalje `number`/`total`/`created_at`, InvoiceType/InvoiceStatus
 * potvrđeni čitanjem enuma jer prazan payments niz na seeded korisniku
 * nije dao živi uzorak vrijednosti.
 */
export interface SubscriptionPayment {
  number: string;
  type: InvoiceType;
  total: number;
  status: InvoiceStatus;
  paid_at: string | null;
  created_at: string;
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
}

/**
 * Sažetak pretplate na GET /me, curl-om potvrđeno (drugi krug
 * verifikacije). Zaseban tip od Subscription (register/07 ekran): /me
 * nema `price`/`price_paid`/`auto_renew`/`starts_at`, ali ima `id`,
 * `status` i `properties_count` koje registracioni odgovor nema. Prva
 * verzija je koristila `Pick<Subscription, 'package'|'ends_at'|
 * 'remaining_visits'>` što ne pokriva ni jedno od ova tri polja.
 */
export interface MeSubscriptionSummary {
  id: number;
  status: SubscriptionStatus;
  package: SubscriptionPackageSummary;
  starts_at: string | null;
  ends_at: string | null;
  remaining_visits: number;
  remaining_inspections: number;
  free_interventions: number;
  properties_count: number;
}

/**
 * Red u GET /client/subscription.properties, curl-om potvrđeno (Mini/Plus
 * i Pro korisnik). RAZLIKUJE SE od SubscriptionPropertyResult (register
 * odgovor): ovdje je `city` ravan string (ime grada), ne ugniježđeni
 * {id, name} objekat, i nema contact_name/contact_note.
 */
export interface ClientSubscriptionProperty {
  id: number;
  city: string;
  street: string;
  use: PropertyUse;
  remaining_visits: number;
  remaining_inspections: number;
}

/**
 * GET /client/subscription, curl-om potvrđeno (Mini/Plus i Pro korisnik,
 * drugi krug verifikacije). `package` je PUNI Package (isti oblik kao
 * /packages), ne slim summary. Bez pretplate: 404 (docs/API.md), pa ovaj
 * tip pretpostavlja da je poziv uspio.
 */
export interface ClientSubscriptionDetail {
  package: Package;
  status: SubscriptionStatus;
  starts_at: string | null;
  ends_at: string | null;
  auto_renew: boolean;
  price_paid: number | null;
  free_interventions: number;
  properties: ClientSubscriptionProperty[];
  payments: SubscriptionPayment[];
}

// ---------------------------------------------------------------------------
// Nalozi (jobs)
// ---------------------------------------------------------------------------

/**
 * Job::steps() u PHP-u, čitanjem izvora + curl-om potvrđeno (uvijek ista
 * četiri koraka istim redom, docs/API.md "Detalji klijentskih odgovora").
 * `key` postoji na svakom koraku, prva verzija tipa ga nije imala.
 */
export interface JobStep {
  key: string;
  label: string;
  done: boolean;
}

/**
 * Enum JobPhotoType.php, čitanjem izvora potvrđeno: "prije"/"poslije", NE
 * "pre"/"posle" kako je prva verzija ovog tipa pretpostavila.
 */
export interface JobPhoto {
  type: 'prije' | 'poslije';
  url: string;
}

/**
 * JobInvoiceResource, čitanjem izvora potvrđeno: SAMO name/qty/line_total.
 * Nema `price_item_id` ni jediničnu `price`: prva verzija je izmislila oba.
 */
export interface InvoiceLaborItem {
  name: string;
  qty: number;
  line_total: number;
}

/**
 * Isto: materijal nema `purchase_price` u odgovoru (server je ne šalje
 * klijentu, samo obračunatu `line_total`), prva verzija je pretpostavila
 * pogrešno.
 */
export interface InvoiceMaterialItem {
  name: string;
  qty: number;
  line_total: number;
}

/**
 * Račun uz nalog (JobInvoiceResource), čitanjem izvora potvrđeno: ima i
 * `id`/`number`/`status`/`paid_at` na vrhu, ne samo stavke i totale kako
 * je prva verzija tipa imala.
 */
export interface Invoice {
  id: number;
  number: string;
  status: InvoiceStatus;
  labor_items: InvoiceLaborItem[];
  materials: InvoiceMaterialItem[];
  labor_total: number;
  material_total: number;
  total: number;
  paid_at: string | null;
}

/**
 * Red u GET /client/jobs i osnova za JobDetail (JobListResource), curl-om
 * potvrđeno (avgust 2026, drugi krug). Znatno drugačije od prve verzije:
 * `technician` je objekat {name}|null (ne `technician_name` string),
 * prozor termina su dva ravna ISO polja (ne ugniježđen `scheduled_window`
 * objekat), i ima description/is_emergency/created_at/deadline_at/
 * deadline_missed_at koje prva verzija nije imala.
 */
export interface JobListItem {
  id: number;
  number: string;
  status: JobStatus;
  type: JobType;
  category: string | null;
  /** Prvih 60 znakova opisa (docs/API.md), server ga skraćuje. */
  title: string;
  description: string;
  is_emergency: boolean;
  technician: { name: string } | null;
  scheduled_window_start: string | null;
  scheduled_window_end: string | null;
  warranty_until: string | null;
  created_at: string;
  deadline_at: string;
  deadline_missed_at: string | null;
}

/**
 * GET /client/jobs/{id} (JobDetailResource extends JobListResource),
 * čitanjem izvora + curl-om (praznog naloga) potvrđeno. `findings`,
 * `photos`, `invoice` su curl-om viđeni SAMO u praznom (null/[]) stanju
 * jer admin/tehničar rute za završavanje naloga još ne postoje na živom
 * serveru (samo /admin/ping) da bi se nalog mogao dovesti do "zavrseno";
 * njihov popunjen oblik je potvrđen čitanjem JobDetailResource/
 * JobInvoiceResource izvora, ne živim popunjenim primjerom.
 */
export interface JobDetail extends JobListItem {
  preferred_window: string | null;
  completed_at: string | null;
  findings: string | null;
  steps: JobStep[];
  property: { id: number; city: string; street: string } | null;
  photos: JobPhoto[];
  invoice: Invoice | null;
}

/**
 * `active_job` na GET /client/dashboard (DashboardController::nalogUToku),
 * čitanjem izvora + curl-om potvrđeno. Prva verzija je imala samo
 * {id, number, status, steps}: nedostajali su category/deadline_at/
 * scheduled_window_start/scheduled_window_end/technician.
 */
export interface ActiveJobSummary {
  id: number;
  number: string;
  status: JobStatus;
  category: string | null;
  deadline_at: string | null;
  scheduled_window_start: string | null;
  scheduled_window_end: string | null;
  technician: { name: string } | null;
  steps: JobStep[];
}

/**
 * Red u `recent_jobs` na GET /client/dashboard
 * (DashboardController::zadnjiNalozi), čitanjem izvora potvrđeno. NIJE
 * puni JobListItem: server šalje samo ovih šest polja (bez title,
 * description, technician, prozora termina itd).
 */
export interface RecentJobSummary {
  id: number;
  number: string;
  status: JobStatus;
  type: JobType;
  category: string | null;
  created_at: string;
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

/**
 * GET /client/dashboard, curl-om potvrđeno (avgust 2026, drugi krug
 * verifikacije, i na klijentu bez naloga i sa aktivnim nalogom).
 * `subscription` je zasebna sažeta forma (DashboardController::pretplata):
 * NE isti oblik kao MeSubscriptionSummary ni ClientSubscriptionDetail
 * (nema `starts_at`/`auto_renew`/`price_paid`, package je samo
 * {name, slug} bez id-a). Docs/API.md kaže da je uvijek prisutan (aktivna
 * pretplata ili zadnja upisana), ali tip ostaje nullable za klijenta koji
 * nikad nije imao pretplatu.
 */
export interface ClientDashboard {
  subscription: {
    id: number;
    package: { name: string; slug: PackageSlug };
    status: SubscriptionStatus;
    ends_at: string | null;
    remaining_visits: number;
    free_interventions: number;
    remaining_inspections: number;
  } | null;
  active_job: ActiveJobSummary | null;
  recent_jobs: RecentJobSummary[];
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
 * traje. Prva verzija je koristila `Pick<Subscription, 'package'|
 * 'ends_at'|'remaining_visits'>`, koji ne pokriva `id`/`status`/
 * `properties_count` koje /me stvarno šalje (vidi MeSubscriptionSummary,
 * curl-om potvrđeno drugi krug verifikacije).
 */
export interface MeResponse {
  user: User;
  role: Role;
  subscription: MeSubscriptionSummary | null;
}

// ---------------------------------------------------------------------------
// Fault report (Prijavi kvar)
// ---------------------------------------------------------------------------

/**
 * Oblik POST /client/jobs zahtjeva, čitanjem StoreJobRequest.php +
 * curl-om potvrđeno (multipart/form-data, ne JSON: is_emergency stiže kao
 * tekst "0"/"1"/"true"/"false", server ga svodi na bool). `photo` NIJE
 * string: na React Native se multipart slika prilaže kao
 * `{uri, type, name}` objekat direktno u FormData, ne kroz ovaj tip (vidi
 * PrijaviScreen.tsx, buildJobFormData). Ovaj interfejs postoji za
 * dokumentaciju/validaciju polja prije slanja, ne kao FormData vrijednost.
 */
export interface CreateJobRequest {
  price_category_id: number;
  description: string;
  is_emergency: boolean;
  /** Nullable na serveru, ali ekran uvijek traži izbor prije submit-a. */
  preferred_window: string;
  /** Obavezno kad pretplata ima više od jedne adrese (Pro), inače se ignoriše. */
  subscription_property_id?: number;
}

export interface CreateJobResponse {
  job: {
    id: number;
    number: string;
    deadline_at: string;
  };
}

// ---------------------------------------------------------------------------
// Profil, zahtjev za promjenu adrese
// ---------------------------------------------------------------------------

/** GET/PUT /client/profile, curl-om potvrđeno (drugi krug verifikacije). */
export interface ClientProfile {
  name: string;
  email: string;
  notifications: {
    push: boolean;
    email: boolean;
    marketing: boolean;
  };
}

export interface UpdateProfileRequest {
  name: string;
  notifications: {
    push: boolean;
    email: boolean;
    marketing: boolean;
  };
}

export interface UpdateProfileResponse {
  data: ClientProfile;
  message: string;
}

/** POST /client/address-change-request, čitanjem AddressChangeRequest.php + curl-om potvrđeno. */
export interface AddressChangeRequestInput {
  message: string;
  subscription_property_id?: number;
}

export interface AddressChangeRequestResponse {
  message: string;
}

/** POST /client/subscription/cancel, curl-om potvrđeno. */
export interface CancelSubscriptionResponse {
  message: string;
  auto_renew: false;
  ends_at: string | null;
}

// ---------------------------------------------------------------------------
// Dev: simulacija kartičnog plaćanja
// ---------------------------------------------------------------------------

/**
 * POST /api/v1/dev/fake-payment, čitanjem FakePaymentController.php
 * potvrđeno. Samo lokalno (fake gateway + app.debug), 404 inače.
 */
export interface FakePaymentRequest {
  reference: string;
  outcome: 'approved' | 'declined';
}

export interface FakePaymentResponse {
  processed: boolean;
  subscription_status: SubscriptionStatus | null;
}

// ---------------------------------------------------------------------------
// Serviser (uloga majstor) i dispečer (uloga dispecer)
//
// Svi tipovi ispod su verifikovani DVOSTRUKO (treći krug verifikacije,
// avgust 2026): čitanjem izvora (TechnicianJobResource, AdminJobResource,
// CompleteJobRequest, UpdateJobRequest, JobTransitionService, JobNotifier,
// CityController/CityResource, TechnicianController, DashboardController
// za /admin/dashboard) I curl-om na php artisan serve --port=8008, kroz
// pun krug: klijent prijavi nalog > dispečer dodijeli (PATCH) > preview
// tekst provjeren riječ po riječ sa stvarno poslatim obavještenjem > Damir
// start > Damir complete sa dvije multipart fotografije > warranty_until i
// invoice u odgovoru. Sve dole navedeno je viđeno živo, ne pretpostavljeno.
// ---------------------------------------------------------------------------

/**
 * Red u GET /technician/jobs (TechnicianJobResource), curl-om potvrđeno.
 * BEZ cijena: majstor bira pozicije tek pri završetku, cijenu za klijenta
 * računa server. `client`/`address` su ravni objekti sa samo onim što
 * majstoru treba (ime klijenta, grad, ulica), ne puni User/Property.
 */
export interface TechnicianJobListItem {
  id: number;
  number: string;
  status: JobStatus;
  type: JobType;
  is_emergency: boolean;
  category: string | null;
  description: string;
  client: { name: string };
  address: { city: string | null; street: string | null };
  scheduled_window_start: string | null;
  scheduled_window_end: string | null;
  deadline_at: string;
}

/**
 * GET /technician/jobs/{id}, curl-om potvrđeno. `package` je RAVAN STRING
 * (naziv paketa), ne objekat: TechnicianJobController::show radi
 * `$subscription?->package?->name`. `entitlements.ide_na_naplatu` je razlog
 * zbog kojeg ovaj tip postoji: majstor mora znati unaprijed ide li rad na
 * naplatu (docs/API.md: "true kad su i kredit i izlasci potrošeni").
 */
export interface TechnicianJobDetail extends TechnicianJobListItem {
  preferred_window: string | null;
  findings: string | null;
  contact: { name: string | null; note: string | null };
  package: string | null;
  entitlements: {
    remaining_visits: number;
    remaining_inspections: number;
    free_interventions: number;
    ide_na_naplatu: boolean;
  };
  photos: JobPhoto[];
}

/**
 * GET /technician/price-list (TechnicianPriceListController), curl-om
 * potvrđeno: RAZLIKUJE SE od PriceItem (public/klijentski cjenovnik):
 * SAMO id/name/unit/base_price, NEMA `prices`/`my_price` mapu (majstor ne
 * vidi popuste po paketu, server ih primjenjuje pri zatvaranju naloga).
 */
export interface TechnicianPriceItem {
  id: number;
  name: string;
  unit: string;
  base_price: number;
}

export interface TechnicianPriceCategory {
  id: number;
  name: string;
  slug: string;
  icon: string;
  items: TechnicianPriceItem[];
}

export interface TechnicianPriceListResponse {
  data: TechnicianPriceCategory[];
  meta: { price_list_version: number };
}

/**
 * POST /technician/jobs/{id}/start, curl-om potvrđeno: `data` je isti
 * oblik kao TechnicianJobListItem (bez detail polja), plus `message`.
 * Idempotentno u u_toku (drugi poziv vraća 200 sa drugom porukom, ne 422).
 */
export interface TechnicianStartJobResponse {
  data: TechnicianJobListItem;
  message: string;
}

/** Ulazni oblik jedne stavke rada, POST .../complete (CompleteJobRequest::stavke()). */
export interface CompleteJobItemInput {
  price_item_id: number;
  qty: number;
}

/** Ulazni oblik jednog materijala, POST .../complete (CompleteJobRequest::materijali()). */
export interface CompleteJobMaterialInput {
  name: string;
  purchase_price: number;
  qty: number;
}

/**
 * Odgovor POST /technician/jobs/{id}/complete (i identično POST
 * /admin/jobs/{id}/complete), curl-om potvrđeno: NIJE puni JobDetail, samo
 * ova šest polja plus sažeta faktura (bez stavki, samo totali). `invoice`
 * je `null` samo teorijski (JobCompletionService uvijek pravi fakturu tipa
 * "rad", i kad je iznos 0, docs/API.md), ostavljeno nullable za sigurnost.
 */
export interface CompleteJobResponse {
  data: {
    id: number;
    number: string;
    status: JobStatus;
    completed_at: string | null;
    warranty_until: string | null;
    visit_source: 'kredit' | 'izlazak' | 'naplata' | null;
    invoice: {
      id: number;
      number: string;
      status: InvoiceStatus;
      labor_total: number;
      material_total: number;
      total: number;
    } | null;
  };
  message: string;
}

// ---------------------------------------------------------------------------
// Dispečer (uloga dispecer): nalozi, dashboard, majstori, gradovi
// ---------------------------------------------------------------------------

/**
 * Red u GET /admin/jobs i osnova GET /admin/jobs/{id} (AdminJobResource),
 * curl-om potvrđeno. `client`/`technician` su objekti sa `id` (za razliku
 * od TechnicianJobListItem koji samo majstoru treba ime), jer dispečer
 * navigira dalje (npr. na karton klijenta).
 */
export interface AdminJobListItem {
  id: number;
  number: string;
  status: JobStatus;
  type: JobType;
  is_emergency: boolean;
  category: string | null;
  /** Prvih 60 znakova opisa, server ga skraćuje (AdminJobResource::NASLOV_ZNAKOVA). */
  title: string;
  client: { id: number; name: string; email: string };
  address: { city: string | null; street: string | null };
  technician: { id: number; name: string } | null;
  scheduled_window_start: string | null;
  scheduled_window_end: string | null;
  deadline_at: string;
  deadline_missed_at: string | null;
  completed_at: string | null;
  created_at: string;
}

/** GET /admin/jobs meta, curl-om potvrđeno: brojači prate `q`, ne `status` filter. */
export interface AdminJobsListMeta {
  counts: {
    novo: number;
    zakazano: number;
    u_toku: number;
    zavrseno: number;
    ukupno: number;
  };
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface AdminJobsListResponse {
  data: AdminJobListItem[];
  meta: AdminJobsListMeta;
}

/** Jedan red u AdminJobDetail.items[], čitanjem AdminJobController::show potvrđeno. */
export interface AdminJobItem {
  id: number;
  name: string;
  qty: number;
  base_price: number;
  discount_pct: number;
  line_total: number;
}

/** Jedan red u AdminJobDetail.materials[], čitanjem AdminJobController::show potvrđeno. */
export interface AdminJobMaterial {
  id: number;
  name: string;
  purchase_price: number;
  qty: number;
  markup_pct: number;
  discount_pct: number;
  line_total: number;
}

/** Red u AdminJobDetail.notifications[] (historija poslatih obavještenja). */
export interface AdminJobNotification {
  id: number;
  channel: 'push' | 'mejl';
  template_key: string;
  body: string;
  sent_at: string | null;
}

/**
 * GET /admin/jobs/{id}, čitanjem AdminJobController::show + curl-om
 * potvrđeno. `subscription.package` i `parent_job` su RAVNI/sažeti, ne puni
 * objekti: `package` je samo naziv (string), `parent_job` je samo
 * {id, number}.
 */
export interface AdminJobDetail extends AdminJobListItem {
  description: string;
  preferred_window: string | null;
  findings: string | null;
  warranty_until: string | null;
  visit_source: 'kredit' | 'izlazak' | 'naplata' | null;
  parent_job: { id: number; number: string } | null;
  contact: { name: string | null; note: string | null };
  subscription: {
    id: number;
    status: SubscriptionStatus;
    package: string | null;
    ends_at: string | null;
    free_interventions: number;
    remaining_visits: number;
    remaining_inspections: number;
  } | null;
  items: AdminJobItem[];
  materials: AdminJobMaterial[];
  photos: JobPhoto[];
  invoice: {
    id: number;
    number: string;
    status: InvoiceStatus;
    labor_total: number;
    material_total: number;
    total: number;
    paid_at: string | null;
  } | null;
  notifications: AdminJobNotification[];
}

/**
 * PATCH /admin/jobs/{id} ulaz (UpdateJobRequest), čitanjem izvora + curl-om
 * potvrđeno. Sva polja opciona: dodjela majstora bez stanja je dozvoljena i
 * ne šalje obavještenje (docs/API.md).
 */
export interface UpdateJobInput {
  technician_id?: number;
  scheduled_window_start?: string;
  scheduled_window_end?: string;
  status?: JobStatus;
}

/**
 * PATCH /admin/jobs/{id} odgovor, curl-om potvrđeno: `notifications_sent`
 * je niz template_key stringova (npr. ["termin_potvrdjen"]), praznо kad
 * PATCH nije okinuo obavještenje (npr. samo dodjela majstora bez stanja).
 */
export interface UpdateJobResponse {
  data: AdminJobListItem;
  notifications_sent: string[];
  message: string;
}

/**
 * GET /admin/jobs/{id}/notification-preview, curl-om potvrđeno riječ po
 * riječ protiv teksta koji je stvarno poslan istim parametrima (treći krug
 * verifikacije): "HAUS: Termin potvrđen. subota 08.08.2026, između 10:00 i
 * 12:00. Majstor: Damir Hodžić. Otkazivanje u aplikaciji." `status: 'novo'`
 * nema predložak i vraća 422 (nema polje za taj slučaj).
 */
export interface NotificationPreviewResponse {
  data: {
    template_key: string;
    body: string;
  };
}

/**
 * GET /admin/dashboard, curl-om potvrđeno. `deadlines_today` i jobs unutar
 * `schedule_today` su puni AdminJobListItem (isti oblik kao lista naloga),
 * ne skraćeni sažetak.
 */
export interface AdminDashboard {
  kpi: {
    novi_danas: number;
    aktivni_nalozi: number;
    rokovi_danas: number;
    /** Prosjek sati od prijave do završetka, zadnjih 30 dana; `null` bez uzorka. */
    prosjek_zavrsetka_h: number | null;
    aktivne_pretplate: number;
  };
  deadlines_today: AdminJobListItem[];
  schedule_today: Array<{
    /** Tačan tekst sa servera, npr. "10:00 do 12:00" (riječ "do", ne en dash). */
    window: string;
    starts_at: string | null;
    ends_at: string | null;
    jobs: AdminJobListItem[];
  }>;
  renewals_soon: Array<{
    id: number;
    client: { id: number; name: string; email: string };
    package: string | null;
    ends_at: string | null;
    auto_renew: boolean;
    dana_do_isteka: number;
  }>;
}

/**
 * Red u GET/POST/PATCH /admin/technicians (TechnicianController::red),
 * curl-om potvrđeno.
 */
export interface AdminTechnician {
  id: number;
  name: string;
  trade: string;
  active: boolean;
  email: string | null;
  has_account: boolean;
  jobs_count: number;
  open_jobs_count: number;
}

/**
 * GET /admin/cities red, curl-om potvrđeno: CityResource plus
 * `properties_count` (dodano u CityController::index, ne u resursu samom).
 */
export interface AdminCity extends City {
  properties_count: number;
}

/** POST /admin/cities ulaz, čitanjem CityController::pravila() potvrđeno. */
export interface CreateCityInput {
  name: string;
  lat: number;
  lng: number;
}

/** PATCH /admin/cities/{id} ulaz, sva polja opciona (čitanjem izvora potvrđeno). */
export interface UpdateCityInput {
  status?: CityStatus;
  name?: string;
  lat?: number;
  lng?: number;
}

/**
 * POST/PATCH /admin/cities odgovor, čitanjem CityController::store/update
 * potvrđeno: `data` je OBIČAN CityResource (bez `properties_count`), za
 * razliku od GET /admin/cities liste (AdminCity ispod) koja ga dodaje samo
 * u index().
 */
export interface AdminCityResponse {
  data: City;
  message: string;
}

export interface AdminCitiesListResponse {
  data: AdminCity[];
}

export interface AdminTechniciansListResponse {
  data: AdminTechnician[];
}
