/**
 * Fetch wrapper za HAUS REST API (docs/API.md).
 *
 * Base URL se bira po platformi jer emulatori ne dijele localhost sa
 * hostom: iOS simulator vidi Mac hosta na localhost, Android emulator ga
 * vidi na specijalnoj adresi 10.0.2.2.
 *
 * Token se čuva u react-native-keychain (sigurniji je od AsyncStorage za
 * osjetljive vrijednosti), pod fiksnim servisom.
 */

import { Platform } from 'react-native';
import * as Keychain from 'react-native-keychain';
import type { ApiValidationError } from './types';

export const API_BASE_URL = Platform.select({
  ios: 'http://localhost:8000/api/v1',
  android: 'http://10.0.2.2:8000/api/v1',
  default: 'http://localhost:8000/api/v1',
});

const KEYCHAIN_SERVICE = 'haus.auth.token';
const KEYCHAIN_USERNAME = 'haus';

export async function setToken(token: string): Promise<void> {
  await Keychain.setGenericPassword(KEYCHAIN_USERNAME, token, {
    service: KEYCHAIN_SERVICE,
  });
}

export async function getToken(): Promise<string | null> {
  const credentials = await Keychain.getGenericPassword({
    service: KEYCHAIN_SERVICE,
  });
  if (!credentials) {
    return null;
  }
  return credentials.password;
}

export async function clearToken(): Promise<void> {
  await Keychain.resetGenericPassword({ service: KEYCHAIN_SERVICE });
}

/**
 * Greška iz API-ja. Za 422 nosi i `errors` mapu po polju (docs/API.md).
 */
export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

type QueryParams = Record<string, string | number | boolean | undefined>;

function buildQueryString(params?: QueryParams): string {
  if (!params) {
    return '';
  }
  const entries = Object.entries(params).filter(
    ([, value]) => value !== undefined,
  );
  if (entries.length === 0) {
    return '';
  }
  const search = new URLSearchParams();
  entries.forEach(([key, value]) => search.append(key, String(value)));
  return `?${search.toString()}`;
}

interface RequestOptions {
  query?: QueryParams;
  /** Ako je true, ne šalje Authorization header (za public rute). */
  skipAuth?: boolean;
  /** Za multipart/form-data upload (fotografije). */
  formData?: FormData;
}

async function request<T>(
  method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
  path: string,
  body?: unknown,
  options?: RequestOptions,
): Promise<T> {
  const url = `${API_BASE_URL}${path}${buildQueryString(options?.query)}`;

  const headers: Record<string, string> = {
    Accept: 'application/json',
  };

  if (!options?.skipAuth) {
    const token = await getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  let requestBody: FormData | string | undefined;
  if (options?.formData) {
    requestBody = options.formData;
    // Ne postavljamo Content-Type ručno za multipart: fetch dodaje boundary.
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    requestBody = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(url, { method, headers, body: requestBody });
  } catch (networkError) {
    throw new ApiError(0, 'Nema veze sa serverom. Provjerite internet.');
  }

  const isJson = response.headers
    .get('content-type')
    ?.includes('application/json');
  const payload = isJson ? await response.json().catch(() => null) : null;

  if (!response.ok) {
    if (response.status === 422 && payload) {
      const validation = payload as ApiValidationError;
      throw new ApiError(
        422,
        validation.message ?? 'Podaci nisu ispravni.',
        validation.errors,
      );
    }
    const message =
      (payload && (payload as { message?: string }).message) ||
      'Došlo je do greške. Pokušajte ponovo.';
    throw new ApiError(response.status, message);
  }

  return payload as T;
}

export function get<T>(path: string, query?: QueryParams): Promise<T> {
  return request<T>('GET', path, undefined, { query });
}

export function post<T>(
  path: string,
  body?: unknown,
  options?: RequestOptions,
): Promise<T> {
  return request<T>('POST', path, body, options);
}

export function patch<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('PATCH', path, body);
}

export function put<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('PUT', path, body);
}

export function del<T>(path: string, body?: unknown): Promise<T> {
  return request<T>('DELETE', path, body);
}

// TODO: push notifikacije (Firebase / notifee) se registruju kroz
// POST /devices ({fcm_token, platform}), vidi docs/API.md "Auth zajedničko".
// Nije uključeno u ovoj fazi jer google-services.json / APNs setup još ne
// postoji. Kad stigne: dodati firebase messaging + notifee zavisnosti,
// zatražiti dozvolu, dobiti fcm token i pozvati post('/devices', {...}).
