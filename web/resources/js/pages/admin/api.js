// Admin (dispečer) API pozivi. Koristi apiGet/apiPost iz zajedničkog wrappera gdje može.
// PATCH, PUT, DELETE i multipart POST nemaju gotov wrapper u api/client.js;
// dodani su ovdje da se taj fajl ne dira izvan admin faze (isti pattern kao pages/klijent/api.js).
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
    throw new ApiError(message, response.status, errors);
  }

  return body;
}

async function apiPatch(path, data) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'PATCH',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...authHeader() },
    body: JSON.stringify(data || {}),
  });
  return handle(response);
}

async function apiPut(path, data) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'PUT',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...authHeader() },
    body: JSON.stringify(data || {}),
  });
  return handle(response);
}

async function apiDelete(path) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', ...authHeader() },
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

// Pregled
export function fetchDashboard() {
  return apiGet('/admin/dashboard');
}

// Zahtjevi (nalozi)
export function fetchJobs(params) {
  return apiGet('/admin/jobs', params);
}

export async function fetchJob(id) {
  const body = await apiGet(`/admin/jobs/${id}`);
  return body.data;
}

export function updateJob(id, payload) {
  return apiPatch(`/admin/jobs/${id}`, payload);
}

export async function fetchNotificationPreview(id, params) {
  const body = await apiGet(`/admin/jobs/${id}/notification-preview`, params);
  return body.data;
}

export function completeJob(id, formData) {
  return apiPostMultipart(`/admin/jobs/${id}/complete`, formData);
}

export function createWarrantyJob(id, payload) {
  return apiPost(`/admin/jobs/${id}/warranty-job`, payload);
}

export async function fetchTechnicians() {
  const body = await apiGet('/admin/technicians');
  return body.data;
}

// Cjenovnik (koristi se i za pretragu pozicija u završetku naloga, i za uređivanje)
export async function fetchPriceList() {
  const body = await apiGet('/admin/price-list');
  return body.data;
}

// Klijenti
export function fetchClients(params) {
  return apiGet('/admin/clients', params);
}

export async function fetchClientDetail(id) {
  const body = await apiGet(`/admin/clients/${id}`);
  return body.data;
}

// Pretplate
export function fetchSubscriptions(params) {
  return apiGet('/admin/subscriptions', params);
}

// Naplata
export function fetchBilling(params) {
  return apiGet('/admin/billing', params);
}

export function refundInvoice(id, payload) {
  return apiPost(`/admin/invoices/${id}/refund`, payload);
}

// Cjenovnik uređivanje
export function updatePriceItem(id, payload) {
  return apiPut(`/admin/price-list/items/${id}`, payload);
}

export function publishPriceList() {
  return apiPost('/admin/price-list/publish', {});
}

// Gradovi
export function fetchAdminCities() {
  return apiGet('/admin/cities');
}

export function createCity(payload) {
  return apiPost('/admin/cities', payload);
}

export function updateCity(id, payload) {
  return apiPatch(`/admin/cities/${id}`, payload);
}

export function deleteCity(id) {
  return apiDelete(`/admin/cities/${id}`);
}

// Postavke
export async function fetchSettings() {
  const body = await apiGet('/admin/settings');
  return body.data;
}

export function updateSettings(payload) {
  return apiPut('/admin/settings', payload);
}

export async function fetchSurcharges() {
  const body = await apiGet('/admin/surcharges');
  return body.data;
}

export function updateSurcharge(id, payload) {
  return apiPut(`/admin/surcharges/${id}`, payload);
}

// Majstori (CRUD)
export function createTechnician(payload) {
  return apiPost('/admin/technicians', payload);
}

export function updateTechnician(id, payload) {
  return apiPatch(`/admin/technicians/${id}`, payload);
}

export function deleteTechnician(id) {
  return apiDelete(`/admin/technicians/${id}`);
}

export { ApiError };
