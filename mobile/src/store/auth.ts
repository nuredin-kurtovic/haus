/**
 * Zustand store za autentikaciju.
 *
 * Token je izvor istine u react-native-keychain (src/api/client.ts);
 * ovaj store je samo in-memory odraz trenutnog stanja da UI/navigacija
 * mogu reagovati na promjenu (nema tokena -> Onboarding/Auth, klijent ->
 * ClientTabs, dispecer -> DispatcherTabs).
 */

import { create } from 'zustand';
import { clearToken, get, getToken, post, setToken } from '../api/client';
import type { LoginResponse, MeResponse, Role, User } from '../api/types';

interface AuthState {
  user: User | null;
  role: Role | null;
  token: string | null;
  /** True dok se čita keychain / poziva /me pri pokretanju aplikacije. */
  isHydrating: boolean;
  hydrate: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  role: null,
  token: null,
  isHydrating: true,

  hydrate: async () => {
    set({ isHydrating: true });
    try {
      const token = await getToken();
      if (!token) {
        set({ user: null, role: null, token: null, isHydrating: false });
        return;
      }
      const me = await get<MeResponse>('/me');
      set({
        user: me.user,
        role: me.role,
        token,
        isHydrating: false,
      });
    } catch {
      // Token je nevažeći ili nema veze; tretiraj kao odjavljenog.
      await clearToken();
      set({ user: null, role: null, token: null, isHydrating: false });
    }
  },

  login: async (email: string, password: string) => {
    const response = await post<LoginResponse>('/auth/login', {
      email,
      password,
    });
    await setToken(response.token);
    set({
      user: response.user,
      role: response.role,
      token: response.token,
      isHydrating: false,
    });
  },

  logout: async () => {
    try {
      await post('/auth/logout');
    } catch {
      // Ignorisati grešku odjave: lokalno stanje se briše svakako.
    }
    await clearToken();
    set({ user: null, role: null, token: null, isHydrating: false });
  },
}));
