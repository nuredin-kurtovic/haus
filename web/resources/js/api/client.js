// Mali fetch wrapper za HAUS API. Baza /api/v1, JSON, Bearer token iz localStorage.
const BASE = '/api/v1';

/**
 * Greška iz API-ja. 422 nosi errors mapu po polju, ostale greške samo message.
 */
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

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

function toQuery(params) {
  const entries = Object.entries(params || {}).filter(([, v]) => v !== undefined && v !== null && v !== '');
  if (entries.length === 0) return '';
  const search = new URLSearchParams(entries);
  return `?${search.toString()}`;
}

export async function apiGet(path, params) {
  const response = await fetch(`${BASE}${path}${toQuery(params)}`, {
    method: 'GET',
    headers: { Accept: 'application/json', ...authHeader() },
  });
  return handle(response);
}

export async function apiPost(path, data) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...authHeader(),
    },
    body: JSON.stringify(data || {}),
  });
  return handle(response);
}
