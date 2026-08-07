/**
 * Tipovi ruta za navigaciju. Klijent/dispečer tab ekrani su za sada
 * placeholderi (sljedeća faza gradi stvarne ekrane); onboarding, auth i
 * registracioni tok (ekrani 01-07) su implementirani.
 *
 * PretplataAktivna nema route param za ishod: ekran čita stvarni
 * `subscription.status` iz registrationStore (setResult), umjesto da
 * navigacija pretpostavlja ishod po payment_method-u (lead review,
 * curl na živi server je pokazao da "kartica" trenutno ne aktivira
 * odmah, jer Monri webhook još ne postoji).
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
};

export type ClientTabParamList = {
  Pocetna: undefined;
  Prijavi: undefined;
  Nalozi: undefined;
  Pretplata: undefined;
  Profil: undefined;
};

export type DispatcherTabParamList = {
  Danas: undefined;
  Nalozi: undefined;
  Gradovi: undefined;
};

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace ReactNavigation {
    interface RootParamList extends RootStackParamList {}
  }
}
