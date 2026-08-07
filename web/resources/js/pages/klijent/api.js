// Klijentski API pozivi. Koristi apiGet/apiPost iz zajedničkog wrappera gdje može.
// PUT i multipart POST (fotografija kvara) nemaju gotov wrapper u api/client.js;
// dodani su ovdje da se taj fajl ne dira izvan klijentske faze.
import { apiGet, apiPost, ApiError } from '../../api/client';

const BASE = '/api/v1';

function authHeader() {
  const token = window.localStorage.getItem('haus_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function handle(response) {
  const isJson = (response.headers.get('content-type') || '').includes('application/json');
  const body = isJson ? await response.json().catch(() => null) : null;

  if (!response.ok) {
    const message = (body && body.message) || 'Greška na serveru. Pokušajte ponovo.';
    const errors = (body && body.errors) || {};
    const error = new ApiError(message, response.status, errors);
    // 403 na prijavi kvara nosi subscription_status van errors mape.
    if (body && body.subscription_status !== undefined) {
      error.subscriptionStatus = body.subscription_status;
    }
    throw error;
  }

  return body;
}

async function apiPut(path, data) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'PUT',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...authHeader() },
    body: JSON.stringify(data || {}),
  });
  return handle(response);
}

async function apiPostMultipart(path, formData) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { Accept: 'application/json', ...authHeader() },
    body: formData,
  });
  return handle(response);
}

export function fetchDashboard() {
  return apiGet('/client/dashboard');
}

export function fetchSubscription() {
  return apiGet('/client/subscription');
}

export function fetchClientPriceList() {
  return apiGet('/client/price-list');
}

export async function fetchJobs() {
  const body = await apiGet('/client/jobs');
  return body.data;
}

export async function fetchJob(id) {
  const body = await apiGet(`/client/jobs/${id}`);
  return body.data;
}

export function cancelSubscription() {
  return apiPost('/client/subscription/cancel', {});
}

export async function fetchProfile() {
  const body = await apiGet('/client/profile');
  return body.data;
}

export function updateProfile(payload) {
  return apiPut('/client/profile', payload);
}

export function requestAddressChange(payload) {
  return apiPost('/client/address-change-request', payload);
}

export function createJob(formData) {
  return apiPostMultipart('/client/jobs', formData);
}

export { ApiError };
