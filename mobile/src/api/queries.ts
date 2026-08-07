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

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, put } from './client';
import type {
  AddressChangeRequestInput,
  AddressChangeRequestResponse,
  ApiDataEnvelope,
  CancelSubscriptionResponse,
  City,
  ClientDashboard,
  ClientPriceListResponse,
  ClientProfile,
  ClientSubscriptionDetail,
  CreateJobResponse,
  FakePaymentRequest,
  FakePaymentResponse,
  JobDetail,
  JobListItem,
  Package,
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
