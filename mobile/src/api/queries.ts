/**
 * TanStack Query hooks za javne reference liste (docs/API.md "Public") i za
 * klijentske ekrane (docs/API.md "Klijent").
 *
 * Curl-om potvrđeno (avgust 2026, lead review): oba javna endpointa vraćaju
 * `{data: [...]}`, NE plain niz kako je prva verzija ovog fajla
 * pretpostavila (nisu Laravel-paginirani, nema links/meta, samo `data`).
 *
 * Klijentski hookovi (drugi krug verifikacije, avgust 2026, curl na živi
 * server): dashboard/subscription/profile nisu omotani u `{data}`
 * (dashboard i subscription su ravni objekti; profile JE omotan
 * `{data}` na GET, vidi useClientProfileQuery), jobs lista i price-list
 * imaju `{data: [...]}` (price-list dodatno `meta`).
 *
 * Keširanje za offline (design/README.md "Offline: job list i price list
 * moraju čitati iz cache-a"): AsyncStorage NIJE instaliran (native dep, van
 * scope-a ove faze), pa nema pravog persist-a preko restarta aplikacije.
 * Umjesto toga: `gcTime: Infinity` na jobs listi i price-listi znači da
 * TanStack Query drži zadnji uspješan odgovor u memoriji cijelu sesiju i
 * vraća GA čak i kad refetch padne (offline), umjesto da ekran prikaže
 * praznu grešku. Restart aplikacije i dalje briše keš: pravi offline
 * persist (npr. @tanstack/query-async-storage-persister) dolazi kad
 * AsyncStorage uđe u zavisnosti.
 */

import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { get, patch, post, put } from './client';
import type {
  AddressChangeRequestInput,
  AddressChangeRequestResponse,
  AdminCitiesListResponse,
  AdminCityResponse,
  AdminDashboard,
  AdminJobDetail,
  AdminJobsListResponse,
  AdminTechniciansListResponse,
  ApiDataEnvelope,
  CancelSubscriptionResponse,
  City,
  ClientDashboard,
  ClientPriceListResponse,
  ClientProfile,
  ClientSubscriptionDetail,
  CompleteJobResponse,
  CreateCityInput,
  CreateJobResponse,
  FakePaymentRequest,
  FakePaymentResponse,
  JobDetail,
  JobListItem,
  JobStatus,
  NotificationPreviewResponse,
  Package,
  TechnicianJobDetail,
  TechnicianJobListItem,
  TechnicianPriceListResponse,
  TechnicianStartJobResponse,
  UpdateCityInput,
  UpdateJobInput,
  UpdateJobResponse,
  UpdateProfileRequest,
  UpdateProfileResponse,
} from './types';

export function usePackagesQuery() {
  return useQuery({
    queryKey: ['packages'],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<Package[]>>('/packages');
      return response.data;
    },
  });
}

export function useCitiesQuery() {
  return useQuery({
    queryKey: ['cities'],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<City[]>>('/cities');
      return response.data;
    },
  });
}

/** Samo gradovi otvoreni za pretplatu (docs/API.md: registracija koristi status=aktivan). */
export function useActiveCities() {
  const query = useCitiesQuery();
  const activeCities = query.data?.filter((city) => city.status === 'aktivan') ?? [];
  return { ...query, activeCities };
}

// ---------------------------------------------------------------------------
// Klijent: početna (dashboard)
// ---------------------------------------------------------------------------

/** GET /client/dashboard, curl-om potvrđeno: ravan objekat, nema `{data}` omot. */
export function useClientDashboardQuery() {
  return useQuery({
    queryKey: ['client', 'dashboard'],
    queryFn: () => get<ClientDashboard>('/client/dashboard'),
  });
}

// ---------------------------------------------------------------------------
// Klijent: nalozi
// ---------------------------------------------------------------------------

/**
 * GET /client/jobs, curl-om potvrđeno: `{data: [...]}`, najnoviji prvi
 * (server već sortira). `gcTime: Infinity` je offline keš, vidi napomenu
 * na vrhu fajla.
 */
export function useClientJobsQuery() {
  return useQuery({
    queryKey: ['client', 'jobs'],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<JobListItem[]>>('/client/jobs');
      return response.data;
    },
    gcTime: Infinity,
  });
}

/** GET /client/jobs/{id}, curl-om potvrđeno: `{data: JobDetail}`. Tuđi nalog: 404. */
export function useClientJobDetailQuery(jobId: number) {
  return useQuery({
    queryKey: ['client', 'jobs', jobId],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<JobDetail>>(`/client/jobs/${jobId}`);
      return response.data;
    },
  });
}

/**
 * POST /client/jobs (prijava kvara), curl-om potvrđeno: multipart, 201
 * `{job: {id, number, deadline_at}}`. FormData se pravi u pozivnom ekranu
 * (PrijaviScreen) jer polja zavise od koraka wizard-a i opcione fotografije;
 * ovaj hook samo šalje i invalidira dashboard/jobs keš na uspjeh.
 */
export function useCreateJobMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (formData: FormData) =>
      post<CreateJobResponse>('/client/jobs', undefined, { formData }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['client', 'dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['client', 'jobs'] });
    },
  });
}

// ---------------------------------------------------------------------------
// Klijent: pretplata
// ---------------------------------------------------------------------------

/**
 * GET /client/subscription, curl-om potvrđeno (Mini/Plus i Pro korisnik):
 * ravan objekat, nema `{data}` omot. Bez ijedne pretplate server vraća 404
 * (docs/API.md), pa `retry: false` da ekran ne pokušava iznova uzalud.
 */
export function useClientSubscriptionQuery() {
  return useQuery({
    queryKey: ['client', 'subscription'],
    queryFn: () => get<ClientSubscriptionDetail>('/client/subscription'),
    retry: false,
  });
}

export function useCancelSubscriptionMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => post<CancelSubscriptionResponse>('/client/subscription/cancel'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['client', 'subscription'] });
    },
  });
}

// ---------------------------------------------------------------------------
// Cjenovnik (klijentov, sa my_price)
// ---------------------------------------------------------------------------

/**
 * GET /client/price-list, curl-om potvrđeno: `{data: [...], meta: {...}}`.
 * `gcTime: Infinity` je offline keš, vidi napomenu na vrhu fajla. Koristi se
 * i za korak 1 prijave kvara (kategorije) i za ekran 14 Cjenovnik, isti
 * queryKey znači jedan poziv za oba mjesta.
 */
export function useClientPriceListQuery() {
  return useQuery({
    queryKey: ['client', 'price-list'],
    queryFn: () => get<ClientPriceListResponse>('/client/price-list'),
    gcTime: Infinity,
  });
}

// ---------------------------------------------------------------------------
// Klijent: profil
// ---------------------------------------------------------------------------

/** GET /client/profile, curl-om potvrđeno: OMOTAN u `{data: ClientProfile}`. */
export function useClientProfileQuery() {
  return useQuery({
    queryKey: ['client', 'profile'],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<ClientProfile>>('/client/profile');
      return response.data;
    },
  });
}

/**
 * PUT /client/profile, curl-om potvrđeno: `{data, message}`. Poziv iz
 * ProfilScreen radi optimistic update (task pravilo) preko `onMutate`, pa
 * prekidač djeluje odmah; `onError` vraća prijašnju vrijednost iz keša.
 */
export function useUpdateProfileMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: UpdateProfileRequest) =>
      put<UpdateProfileResponse>('/client/profile', payload),
    onMutate: async (payload) => {
      await queryClient.cancelQueries({ queryKey: ['client', 'profile'] });
      const previous = queryClient.getQueryData<ClientProfile>(['client', 'profile']);
      if (previous) {
        queryClient.setQueryData<ClientProfile>(['client', 'profile'], {
          ...previous,
          name: payload.name,
          notifications: payload.notifications,
        });
      }
      return { previous };
    },
    onError: (_error, _payload, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['client', 'profile'], context.previous);
      }
    },
    onSuccess: (response) => {
      queryClient.setQueryData(['client', 'profile'], response.data);
    },
  });
}

export function useAddressChangeRequestMutation() {
  return useMutation({
    mutationFn: (payload: AddressChangeRequestInput) =>
      post<AddressChangeRequestResponse>('/client/address-change-request', payload),
  });
}

// ---------------------------------------------------------------------------
// Dev: simulacija kartičnog plaćanja (KarticaInfoScreen)
// ---------------------------------------------------------------------------

/**
 * POST /dev/fake-payment, čitanjem FakePaymentController.php potvrđeno:
 * bez auth-a (ruta postoji samo lokalno, fake gateway + debug). `skipAuth`
 * ovdje je samo higijena: korisnik u ovom trenutku registracionog toka još
 * nema token upisan u store/keychain (commitSession se zove kasnije).
 */
export function useFakePaymentMutation() {
  return useMutation({
    mutationFn: (payload: FakePaymentRequest) =>
      post<FakePaymentResponse>('/dev/fake-payment', payload, { skipAuth: true }),
  });
}

// ---------------------------------------------------------------------------
// Serviser (uloga majstor)
//
// Curl-om potvrđeno protiv php artisan serve --port=8008 (treći krug
// verifikacije, avgust 2026), pun krug: login damir@haus.ba > jobs lista >
// detalj > start > complete sa multipart photos_before[]/photos_after[].
// ---------------------------------------------------------------------------

/** GET /technician/jobs, curl-om potvrđeno: `{data: [...]}`. */
export function useTechnicianJobsQuery(params?: { status?: JobStatus; date?: string }) {
  return useQuery({
    queryKey: ['technician', 'jobs', params ?? {}],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<TechnicianJobListItem[]>>(
        '/technician/jobs',
        params,
      );
      return response.data;
    },
    // Offline keš (design/README.md "Offline: job list ... mora čitati iz
    // cache-a"), isti obrazac kao useClientJobsQuery.
    gcTime: Infinity,
  });
}

/** GET /technician/jobs/{id}, curl-om potvrđeno: `{data: TechnicianJobDetail}`. */
export function useTechnicianJobDetailQuery(jobId: number) {
  return useQuery({
    queryKey: ['technician', 'jobs', jobId],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<TechnicianJobDetail>>(
        `/technician/jobs/${jobId}`,
      );
      return response.data;
    },
  });
}

/**
 * POST /technician/jobs/{id}/start, curl-om potvrđeno: idempotentno u
 * u_toku (drugi poziv vraća 200, ne 422), pa mutacija ne mora posebno
 * paziti na ponovljen tap.
 */
export function useStartTechnicianJobMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (jobId: number) =>
      post<TechnicianStartJobResponse>(`/technician/jobs/${jobId}/start`),
    onSuccess: (_response, jobId) => {
      queryClient.invalidateQueries({ queryKey: ['technician', 'jobs'] });
      queryClient.invalidateQueries({ queryKey: ['technician', 'jobs', jobId] });
    },
  });
}

/**
 * GET /technician/price-list, curl-om potvrđeno: `{data, meta}`, bez
 * cijena po paketu (vidi TechnicianPriceCategory/TechnicianPriceItem).
 */
export function useTechnicianPriceListQuery(q?: string) {
  return useQuery({
    queryKey: ['technician', 'price-list', q ?? ''],
    queryFn: () =>
      get<TechnicianPriceListResponse>('/technician/price-list', q ? { q } : undefined),
    gcTime: Infinity,
  });
}

/**
 * POST /technician/jobs/{id}/complete, curl-om potvrđeno: multipart, isti
 * servis kao dispečerovo zatvaranje. FormData se pravi u pozivnom ekranu
 * (items/materials kao JSON tekst, CompleteJobRequest::prepareForValidation
 * ih raspakuje), isti obrazac kao useCreateJobMutation.
 */
export function useCompleteTechnicianJobMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ jobId, formData }: { jobId: number; formData: FormData }) =>
      post<CompleteJobResponse>(`/technician/jobs/${jobId}/complete`, undefined, { formData }),
    onSuccess: (_response, variables) => {
      queryClient.invalidateQueries({ queryKey: ['technician', 'jobs'] });
      queryClient.invalidateQueries({ queryKey: ['technician', 'jobs', variables.jobId] });
    },
  });
}

// ---------------------------------------------------------------------------
// Dispečer (uloga dispecer)
//
// Curl-om potvrđeno protiv php artisan serve --port=8008 (treći krug
// verifikacije): login dispecer@haus.ba > dashboard > jobs lista > PATCH
// dodjela+termin (zakazano) > notification-preview (tekst identičan
// stvarno poslatom obavještenju) > admin/technicians > admin/cities
// (GET/POST/PATCH, uklj. 422 na BiH bbox).
// ---------------------------------------------------------------------------

/** GET /admin/dashboard, curl-om potvrđeno: ravan objekat, nema `{data}` omot. */
export function useAdminDashboardQuery() {
  return useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: () => get<AdminDashboard>('/admin/dashboard'),
  });
}

/** `status: undefined` znači "Svi" (filter chip), meta.counts ne prate filter (docs/API.md). */
export interface AdminJobsQueryParams {
  status?: JobStatus;
  q?: string;
}

const ADMIN_JOBS_PER_PAGE = 20;

/**
 * GET /admin/jobs, curl-om potvrđeno: `{data, meta: {counts, ...}}`.
 * useInfiniteQuery za "učitaj još" paginaciju (ekran 17 Nalozi): Laravel
 * paginate() čita `page` query parametar direktno iz zahtjeva (ne kroz
 * $request->validate() listu), potvrđeno čitanjem AdminJobController/
 * paginate() poziva, pa slobodno šaljemo `page` iako nije eksplicitno u
 * dokumentovanom ugovoru.
 */
export function useAdminJobsInfiniteQuery(params: AdminJobsQueryParams) {
  const status = params.status;
  const q = params.q?.trim() || undefined;
  return useInfiniteQuery({
    queryKey: ['admin', 'jobs', { status, q }],
    queryFn: ({ pageParam }) =>
      get<AdminJobsListResponse>('/admin/jobs', {
        status,
        q,
        per_page: ADMIN_JOBS_PER_PAGE,
        page: pageParam,
      }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.meta.current_page < lastPage.meta.last_page
        ? lastPage.meta.current_page + 1
        : undefined,
  });
}

/** GET /admin/jobs/{id}, curl-om potvrđeno: `{data: AdminJobDetail}`. */
export function useAdminJobDetailQuery(jobId: number) {
  return useQuery({
    queryKey: ['admin', 'jobs', jobId],
    queryFn: async () => {
      const response = await get<ApiDataEnvelope<AdminJobDetail>>(`/admin/jobs/${jobId}`);
      return response.data;
    },
  });
}

/**
 * PATCH /admin/jobs/{id}, curl-om potvrđeno: dodjela majstora, termin
 * (tačno 2h prozor) i tranzicije. Invalidira listu, detalj i dashboard
 * (KPI/raspored/rokovi zavise od stanja naloga).
 */
export function useUpdateAdminJobMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ jobId, payload }: { jobId: number; payload: UpdateJobInput }) =>
      patch<UpdateJobResponse>(`/admin/jobs/${jobId}`, payload),
    onSuccess: (_response, variables) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'jobs'] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'jobs', variables.jobId] });
      queryClient.invalidateQueries({ queryKey: ['admin', 'dashboard'] });
    },
  });
}

/**
 * GET /admin/jobs/{id}/notification-preview, curl-om potvrđeno riječ po
 * riječ (vidi api/types.ts NotificationPreviewResponse). `params === null`
 * gasi upit (npr. nalog je "novo" i još nema majstora/termin izabran, za
 * koje `status: 'novo'` vraća 422 jer nema predložak).
 *
 * `placeholderData: keepPreviousData` drži zadnji prikazani tekst dok
 * refetch (uzrokovan promjenom majstora/termina na ekranu 18) ne završi,
 * da panel ne trepće na praznо dok korisnik dodaje slova u polje termina.
 */
export function useNotificationPreviewQuery(
  jobId: number,
  params: {
    status: JobStatus;
    technician_id?: number;
    scheduled_window_start?: string;
    scheduled_window_end?: string;
  } | null,
) {
  return useQuery({
    queryKey: ['admin', 'jobs', jobId, 'notification-preview', params],
    queryFn: () =>
      get<NotificationPreviewResponse>(
        `/admin/jobs/${jobId}/notification-preview`,
        params
          ? {
              status: params.status,
              technician_id: params.technician_id,
              scheduled_window_start: params.scheduled_window_start,
              scheduled_window_end: params.scheduled_window_end,
            }
          : undefined,
      ),
    enabled: params !== null,
    placeholderData: keepPreviousData,
    retry: false,
  });
}

/** GET /admin/technicians, curl-om potvrđeno: `{data: AdminTechnician[]}`. */
export function useAdminTechniciansQuery() {
  return useQuery({
    queryKey: ['admin', 'technicians'],
    queryFn: async () => {
      const response = await get<AdminTechniciansListResponse>('/admin/technicians');
      return response.data;
    },
  });
}

/**
 * GET /admin/cities, curl-om potvrđeno: `{data: AdminCity[]}` (sa
 * `properties_count`, samo na listi, vidi api/types.ts AdminCity).
 */
export function useAdminCitiesQuery() {
  return useQuery({
    queryKey: ['admin', 'cities'],
    queryFn: async () => {
      const response = await get<AdminCitiesListResponse>('/admin/cities');
      return response.data;
    },
  });
}

/**
 * POST /admin/cities, curl-om potvrđeno: 201 `{data, message}`, novi grad
 * je uvijek "u_pripremi" (server ignoriše svaki status koji bi klijent
 * poslao, ne prima to polje ni u pravilima). BiH bbox (lat 42 do 46, lng 15
 * do 20) je i server-side (422) i mora biti provjeren na ekranu prije slanja.
 */
export function useCreateCityMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateCityInput) => post<AdminCityResponse>('/admin/cities', payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'cities'] });
    },
  });
}

/**
 * PATCH /admin/cities/{id}, curl-om potvrđeno (status toggle aktivan <->
 * u_pripremi). Koristi se i za tap na state chip (ekran 19 Gradovi).
 */
export function useUpdateCityMutation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ cityId, payload }: { cityId: number; payload: UpdateCityInput }) =>
      patch<AdminCityResponse>(`/admin/cities/${cityId}`, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'cities'] });
    },
  });
}
