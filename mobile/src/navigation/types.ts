/**
 * Tipovi ruta za navigaciju. Svi ekrani su za sada placeholderi (sljedeća
 * faza gradi stvarne ekrane); parametri su undefined dok ne zatreba
 * prosljeđivanje podataka (npr. jobId na detalj ekranu).
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
