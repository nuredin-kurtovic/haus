/**
 * HAUS design tokeni za mobile.
 *
 * Izvor: design/README.md ("Design tokens" i "Screens: mobile" sekcije).
 * Ovo su ugovorni vrijednosti, ne mijenjati bez ažuriranja design/README.md.
 *
 * VAŽNO: border-radius ne postoji nigdje u ovom dizajnu. Nema konstante za
 * njega jer ne treba postojati poziv koji ga koristi. Svi elementi su oštrih
 * ivica (border-radius: 0 ekvivalent na RN je jednostavno ne postavljati
 * borderRadius).
 */

import type { TextStyle } from 'react-native';

export const colors = {
  ink: '#252422',
  ember: '#FE5100',
  emberDark: '#D94500',
  ivory: '#FFFCF2',
  sand: '#CCC5B9',
  bark: '#403D39',
  grey: '#8A857E',
  white: '#FFFFFF',
  zebra: '#FDFCF8',
  error: '#B03000',
} as const;

/**
 * Na React Native se font weight bira IMENOM fonta, ne fontWeight
 * propertijem (Poppins je varijabilni font, ali svaki weight je učitan kao
 * poseban fajl/porodica). Nikad postavljati fontWeight uz fontFamily.
 */
export const fontFamily = {
  light: 'Poppins-Light', // 300
  regular: 'Poppins-Regular', // 400
  medium: 'Poppins-Medium', // 500
  semiBold: 'Poppins-SemiBold', // 600
  bold: 'Poppins-Bold', // 700
} as const;

type TextStyleToken = {
  fontFamily: string;
  fontSize: number;
  lineHeight: number;
  letterSpacing?: number;
  textTransform?: 'uppercase';
};

/**
 * Type scale za mobile (390 x 844 logički viewport), iz design/README.md
 * "Screens: mobile" > "Typography" tabele.
 */
export const typeScale: Record<string, TextStyleToken> = {
  // Welcome headline: 35px, 700
  welcomeHeadline: {
    fontFamily: fontFamily.bold,
    fontSize: 35,
    lineHeight: 40,
  },
  // Screen H2: 30 do 34px, 700
  screenH2: {
    fontFamily: fontFamily.bold,
    fontSize: 32,
    lineHeight: 38,
  },
  // Section H2: 26px, 700
  sectionH2: {
    fontFamily: fontFamily.bold,
    fontSize: 26,
    lineHeight: 32,
  },
  // Card H3: 17 do 24px, 600 do 700 (default na semiBold, 20px)
  cardH3: {
    fontFamily: fontFamily.semiBold,
    fontSize: 20,
    lineHeight: 26,
  },
  cardH3Large: {
    fontFamily: fontFamily.bold,
    fontSize: 24,
    lineHeight: 30,
  },
  // Body: 15 do 17px, 400 (nikad ispod 13px na mobile)
  body: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
  },
  bodySmall: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
  },
  // Meta / caption: 13 do 14px, 400
  meta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    lineHeight: 18,
  },
  // Eyebrow: 11 do 12px, 600, .08em do .1em uppercase
  eyebrow: {
    fontFamily: fontFamily.semiBold,
    fontSize: 12,
    lineHeight: 16,
    letterSpacing: 1.2, // ~.1em na 12px
    textTransform: 'uppercase',
  },
  // Tab label: 11px, 400 ili 600 (600 kad je aktivan, vidi Button/tab bar)
  tabLabel: {
    fontFamily: fontFamily.regular,
    fontSize: 11,
    lineHeight: 14,
  },
  tabLabelActive: {
    fontFamily: fontFamily.semiBold,
    fontSize: 11,
    lineHeight: 14,
  },
  // Dugme (primary/ghost) tekst
  button: {
    fontFamily: fontFamily.semiBold,
    fontSize: 16,
    lineHeight: 20,
  },
};

/**
 * Spacing rhythm, iz design/README.md "Spacing" sekcije.
 */
export const spacing = {
  // Mobile screen padding: 22 do 26px horizontal
  screenPaddingMin: 22,
  screenPaddingMax: 26,
  screenPadding: 24,

  // Form field gap / label to input
  fieldGap: 18,
  labelToInput: 8,

  // Card padding
  cardPadding: 26,

  // Generička skala za manje razmake
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,

  // Touch target min i primary button visina
  touchTargetMin: 44,
  primaryButtonHeight: 52,

  // Progress bar segment gap (Kako radi ekran)
  progressBarGap: 6,
} as const;

/**
 * State chip mapping, iz docs/API.md "Konvencije" i design/README.md
 * "Job lifecycle" tabele. Konzistentno na web i mobile.
 */
export const stateChipStyles = {
  novo: { border: colors.ink, fill: colors.white, text: colors.ink },
  zakazano: { border: colors.bark, fill: colors.ivory, text: colors.bark },
  u_toku: { border: colors.ember, fill: colors.ember, text: colors.ivory },
  zavrseno: { border: colors.sand, fill: colors.sand, text: colors.ink },
  garancija: { border: colors.ink, fill: colors.ink, text: colors.ivory },
} as const;

/**
 * Uključuje i 'garancija', koje je u docs/API.md tip naloga, ne status
 * lifecycle-a, ali vizuelno dijeli isti chip sistem.
 */
export type ChipState = keyof typeof stateChipStyles;

/** Cifre uvijek tabular-nums (ugovorno pravilo brenda). Dodati na svaki Text koji prikazuje brojeve. */
export const numeric: { fontVariant: TextStyle['fontVariant'] } = {
  fontVariant: ['tabular-nums'],
};
