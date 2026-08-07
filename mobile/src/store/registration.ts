/**
 * Zustand store za draft registracije (ekrani 04 Izbor paketa, 05 Podaci i
 * adresa, 06 Plaćanje, 07 Pretplata aktivna).
 *
 * Postoji odvojeno od auth store-a jer draft ne predstavlja sesiju: token iz
 * POST /auth/register se ne upisuje u keychain / auth store dok korisnik ne
 * potvrdi "Uđite u aplikaciju" na ekranu 07 (vidi auth.ts commitSession).
 * Ako se ovaj store ne resetuje, sljedeća registracija bi krenula sa
 * ostacima prethodnog drafta.
 */

import { create } from 'zustand';
import type { Package, PaymentMethod, RegisterResponse } from '../api/types';

export interface PropertyDraft {
  cityId: number | null;
  street: string;
}

interface RegistrationState {
  pkg: Package | null;
  name: string;
  email: string;
  password: string;
  /** Mini / Plus: jedna adresa. */
  cityId: number | null;
  street: string;
  /** Pro: lista stanova, min 2 (ugovorno, docs/API.md). */
  properties: PropertyDraft[];
  paymentMethod: PaymentMethod;
  /** Odgovor POST /auth/register, čita ga ekran 07 i KarticaInfo. */
  result: RegisterResponse | null;

  setPackage: (pkg: Package) => void;
  setPersonalAndAddress: (data: {
    name: string;
    email: string;
    password: string;
    cityId: number | null;
    street: string;
    properties: PropertyDraft[];
  }) => void;
  setPaymentMethod: (method: PaymentMethod) => void;
  setResult: (result: RegisterResponse) => void;
  reset: () => void;
}

const initialState = {
  pkg: null as Package | null,
  name: '',
  email: '',
  password: '',
  cityId: null as number | null,
  street: '',
  properties: [] as PropertyDraft[],
  paymentMethod: 'uplatnica' as PaymentMethod,
  result: null as RegisterResponse | null,
};

export const useRegistrationStore = create<RegistrationState>((set) => ({
  ...initialState,

  setPackage: (pkg) => set({ pkg }),

  setPersonalAndAddress: (data) =>
    set({
      name: data.name,
      email: data.email,
      password: data.password,
      cityId: data.cityId,
      street: data.street,
      properties: data.properties,
    }),

  setPaymentMethod: (method) => set({ paymentMethod: method }),

  setResult: (result) => set({ result }),

  reset: () => set({ ...initialState }),
}));
