// Auth store: token u localStorage, user, role. Login vodi na /admin (dispecer) ili /klijent.
import { defineStore } from 'pinia';
import { apiGet, apiPost, ApiError } from '../api/client';
import router from '../router';

const TOKEN_KEY = 'haus_token';

function readStoredToken() {
  try {
    return window.localStorage.getItem(TOKEN_KEY) || '';
  } catch (e) {
    return '';
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: readStoredToken(),
    user: null,
    role: null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    setSession({ token, user, role }) {
      this.token = token;
      this.user = user;
      this.role = role;
      window.localStorage.setItem(TOKEN_KEY, token);
    },
    clearSession() {
      this.token = '';
      this.user = null;
      this.role = null;
      window.localStorage.removeItem(TOKEN_KEY);
    },
    /**
     * @returns {Promise<{ok: boolean, message?: string, errors?: object}>}
     */
    async login(email, password) {
      try {
        const body = await apiPost('/auth/login', { email, password });
        this.setSession(body);
        await router.push(this.role === 'dispecer' ? '/admin' : '/klijent');
        return { ok: true };
      } catch (error) {
        if (error instanceof ApiError) {
          return { ok: false, message: error.message, errors: error.errors };
        }
        return { ok: false, message: 'Prijava nije uspjela. Provjerite internet konekciju.' };
      }
    },
    async logout() {
      try {
        await apiPost('/auth/logout', {});
      } catch (e) {
        // Odjava lokalno prolazi i ako server ne odgovori.
      }
      this.clearSession();
      await router.push('/prijava');
    },
    async fetchMe() {
      if (!this.token) return;
      try {
        const me = await apiGet('/me');
        this.user = me.user || me;
        if (me.role) this.role = me.role;
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          this.clearSession();
        }
      }
    },
  },
});
