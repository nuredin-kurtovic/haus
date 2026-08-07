import type { NavigatorScreenParams } from '@react-navigation/native';

/**
 * Tipovi ruta za navigaciju. Onboarding, auth, registracioni tok (01-07),
 * klijentski ekrani (08-15), serviserski ekrani (majstor: Nalozi, Profil) i
 * dispečerski ekrani (16-19) su implementirani.
 *
 * PretplataAktivna nema route param za ishod: ekran čita stvarni
 * `subscription.status` iz registrationStore (setResult), umjesto da
 * navigacija pretpostavlja ishod po payment_method-u (lead review,
 * curl na živi server je pokazao da "kartica" trenutno ne aktivira
 * odmah, jer Monri webhook još ne postoji).
 *
 * Tri klijentska taba (Prijavi, Nalozi, Pretplata) su NIJESU više gole
 * (undefined) rute: svaki je zaseban native-stack navigator ugniježđen u
 * tabu (vidi navigation/PrijaviStack.tsx, NaloziStack.tsx,
 * PretplataStack.tsx), da bi detalj/potvrda ekrani (10, 12, 14) mogli
 * imati svoje '‹' back dugme i da tab bar ostane vidljiv na njima
 * (React Navigation default: ugniježđen stack ne skriva roditeljski tab
 * bar sam od sebe). `NavigatorScreenParams` omogućava cross-tab navigaciju
 * direktno na ugniježđeni ekran, npr. sa Početne na konkretan nalog:
 * `navigation.navigate('Nalozi', { screen: 'NalogDetalj', params: { jobId } })`.
 */
export type RootStackParamList = {
  // Onboarding
  Welcome: undefined;
  KakoRadi: undefined;
  // Auth
  Prijava: undefined;
  // Registracija
  IzborPaketa: undefined;
  PodaciAdresa: undefined;
  Placanje: undefined;
  /** Placeholder prije Monri integracije, vidi PlacanjeScreen. */
  KarticaInfo: undefined;
  PretplataAktivna: undefined;
  // Ulazne tačke u tab navigatore
  ClientTabs: undefined;
  DispatcherTabs: undefined;
  TechnicianTabs: undefined;
};

/** Ekrani 09 (koraci) i 10 (potvrda), ugniježđeni u tabu Prijavi. */
export type PrijaviStackParamList = {
  PrijaviKvar: undefined;
  PrijavaPrimljena: {
    jobId: number;
    number: string;
    deadlineAt: string;
    category: string;
    isEmergency: boolean;
    preferredWindow: string;
    remainingVisits: number;
    totalVisits: number;
  };
};

/** Ekrani 11 (lista) i 12 (detalj), ugniježđeni u tabu Nalozi. */
export type NaloziStackParamList = {
  NaloziList: undefined;
  NalogDetalj: { jobId: number };
};

/** Ekran 13 (glavni) i 14 (cjenovnik), ugniježđeni u tabu Pretplata. */
export type PretplataStackParamList = {
  PretplataGlavna: undefined;
  Cjenovnik: undefined;
};

export type ClientTabParamList = {
  Pocetna: undefined;
  Prijavi: NavigatorScreenParams<PrijaviStackParamList>;
  Nalozi: NavigatorScreenParams<NaloziStackParamList>;
  Pretplata: NavigatorScreenParams<PretplataStackParamList>;
  Profil: undefined;
};

/** Ekran 17 (lista) i 18 (nalog i dodjela), ugniježđeni u dispečerskom tabu Nalozi. */
export type DispatcherNaloziStackParamList = {
  NaloziList: undefined;
  NalogDodjela: { jobId: number };
};

export type DispatcherTabParamList = {
  Danas: undefined;
  Nalozi: NavigatorScreenParams<DispatcherNaloziStackParamList>;
  Gradovi: undefined;
};

/**
 * Serviserski (majstor) tabovi: Nalozi, Profil (task A). "Moji nalozi",
 * "Detalj naloga" i "Završetak naloga" su ugniježđeni u tabu Nalozi, isti
 * obrazac kao NaloziStack.tsx na klijentskoj strani, da tab bar ostane
 * vidljiv i na detalju.
 */
export type TechnicianNaloziStackParamList = {
  MojiNalozi: undefined;
  NalogDetalj: { jobId: number };
  ZavrsetakNaloga: { jobId: number };
  ZavrsetakPotvrda: {
    jobId: number;
    number: string;
    warrantyUntil: string | null;
    invoice: {
      id: number;
      number: string;
      status: string;
      labor_total: number;
      material_total: number;
      total: number;
    } | null;
  };
};

export type TechnicianTabParamList = {
  Nalozi: NavigatorScreenParams<TechnicianNaloziStackParamList>;
  Profil: undefined;
};

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace ReactNavigation {
    interface RootParamList extends RootStackParamList {}
  }
}
