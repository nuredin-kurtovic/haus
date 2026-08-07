/**
 * TypeScript tipovi za HAUS REST API entitete.
 *
 * Izvor: docs/API.md (ugovor između weba i mobile). Backend je jedini
 * izvor istine; ovi tipovi prate ugovor onako kako je opisan u API.md i
 * design/README.md. Ako se ugovor promijeni, ažurirati ovaj fajl.
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

export type PropertyUse =
  | 'izdaje_se'
  | 'prazan_dijaspora'
  | 'zivim_u_njemu';

export type PackageSlug = 'mini' | 'plus' | 'pro';

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
  lat: number;
  lng: number;
  status: CityStatus;
}

// ---------------------------------------------------------------------------
// Paketi
// ---------------------------------------------------------------------------

export interface VolumeDiscountTier {
  min_properties: number;
  max_properties: number | null;
  discount_percent: number;
}

export interface Package {
  id: number;
  slug: PackageSlug;
  name: string;
  /** Cijena u KM. Za Pro je "po stanu". */
  price: number;
  unit: string;
  audience: string;
  features: string[];
  remaining_visits: number;
  free_interventions: number;
  remaining_inspections: number;
  /** Samo za HAUS Pro. */
  volume_discount_tiers?: VolumeDiscountTier[];
}

// ---------------------------------------------------------------------------
// Cjenovnik
// ---------------------------------------------------------------------------

export interface PriceItemPrices {
  mini: number;
  plus: number;
  pro: number;
}

export interface PriceItem {
  id: number;
  name: string;
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
  items: PriceItem[];
}

export interface Surcharge {
  key: string;
  label: string;
  type: SurchargeType;
  value: number;
}

export interface PublicSettings {
  opening_hours: string;
  hourly_rate: number;
  price_list_version: string;
}

// ---------------------------------------------------------------------------
// Korisnik, pretplata
// ---------------------------------------------------------------------------

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  phone?: string | null;
  notifications?: {
    push: boolean;
    email: boolean;
    marketing: boolean;
  };
}

export interface SubscriptionProperty {
  id: number;
  city_id: number;
  street: string;
  use?: PropertyUse;
  contact?: string | null;
}

export interface SubscriptionPaymentHistoryEntry {
  id: number;
  date: string;
  amount: number;
  method: PaymentMethod;
  status: 'placeno' | 'neplaceno';
}

export interface Subscription {
  id: number;
  package: Package;
  ends_at: string;
  remaining_visits: number;
  free_interventions: number;
  remaining_inspections: number;
  auto_renew: boolean;
  properties?: SubscriptionProperty[];
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

export interface ClientDashboard {
  subscription: {
    package: Package;
    ends_at: string;
    remaining_visits: number;
    free_interventions: number;
    remaining_inspections: number;
  };
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
  contact?: string;
}

export interface RegisterRequest {
  package_id: number;
  name: string;
  email: string;
  password: string;
  payment_method: PaymentMethod;
  properties: RegisterPropertyInput[];
}

export interface RegisterResponse {
  user: User;
  subscription: Subscription;
  token: string;
  /** Prisutno samo za payment_method: kartica. */
  payment?: { redirect_url: string };
  /** Prisutno kad Pro registracija ima 10+ stanova. */
  status?: 'ponuda';
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

export interface MeResponse {
  user: User;
  role: Role;
  subscription?: Pick<
    Subscription,
    'package' | 'ends_at' | 'remaining_visits'
  >;
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
