/**
 * TanStack Query hooks za javne reference liste (docs/API.md "Public").
 *
 * Curl-om potvrđeno (avgust 2026, lead review): oba endpointa vraćaju
 * `{data: [...]}`, NE plain niz kako je prva verzija ovog fajla
 * pretpostavila (nisu Laravel-paginirani, nema links/meta, samo `data`).
 */

import { useQuery } from '@tanstack/react-query';
import { get } from './client';
import type { ApiDataEnvelope, City, Package } from './types';

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
