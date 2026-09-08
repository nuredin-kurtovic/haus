/**
 * 06 Plaćanje, registracija korak 3 od 3 (design/README.md, prototip
 * data-screen-label="06 Placanje").
 *
 * Sažetak iznad prikazuje IZRAČUNATU cijenu: za Mini/Plus je to
 * pkg.price_year; za Pro je to procjena kroz volume_discount_tiers (curl-om
 * potvrđeni ključevi min/max/pct). Ovo je jedino mjesto na mobile klijentu
 * gdje se popust računa lokalno, i to je namjerno ograničeno na pre-submit
 * prikaz: design/README.md pravilo "klijent nikad ne računa popust
 * lokalno" ostaje ispravno u duhu, jer je konačna, mjerodavna cijena ona
 * koju vrati server u odgovoru na POST /auth/register
 * (subscription.price) i ta se prikazuje na sljedećem ekranu (07), ne ova
 * procjena.
 *
 * Submit -> POST /auth/register (docs/API.md). Lead review + curl na živi
 * server (avgust 2026) je otkrio da odgovor UVIJEK nosi `status`
 * (subscription.status), i da "kartica" NE aktivira odmah: Monri webhook
 * još ne postoji, pa i kartica trenutno vraća status "cekanje_uplate" dok
 * se simulirano plaćanje ne završi. Zato se poslije submit-a ne
 * pretpostavlja ishod po payment_method-u, već se prosljeđuje stvarni
 * odgovor (setResult) i ekran 07 sâm čita subscription.status:
 * - "ponuda" (Pro 10+): ekran 07, poruka da zahtjev ide dispečeru.
 * - payment.redirect_url prisutan (kartica): InfoScreen placeholder prije
 *   ekrana 07 (najavljuje web tok koji dolazi u fazi plaćanja).
 * - inače: direktno ekran 07, koji prikazuje "cekanje_uplate" ili
 *   "aktivna" prema stvarnom statusu.
 */

import React, { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import { useCitiesQuery } from '../../api/queries';
import { useAuthStore } from '../../store/auth';
import { useRegistrationStore } from '../../store/registration';
import { ApiError } from '../../api/client';
import { colors, fontFamily, numeric, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';
import type { PaymentMethod, RegisterRequest, VolumeDiscountTier } from '../../api/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

function tierFor(count: number, tiers?: VolumeDiscountTier[]) {
  return tiers?.find((tier) => count >= tier.min && (tier.max == null || count <= tier.max));
}

const METHODS: Array<{ id: PaymentMethod; title: string; description: string }> = [
  {
    id: 'uplatnica',
    title: 'Uplatnica na mejl',
    description: 'Uplatnicu i račun šaljemo na mejl. Pretplata je aktivna kad uplata legne.',
  },
  {
    id: 'kartica',
    title: 'Kartica',
    description: 'Aktivacija odmah. Račun dobijate na mejl istog trenutka.',
  },
];

export function PlacanjeScreen() {
  const navigation = useNavigation<Nav>();
  const citiesQuery = useCitiesQuery();
  const registerRequest = useAuthStore((state) => state.register);
  const draft = useRegistrationStore((state) => state);
  const setPaymentMethod = useRegistrationStore((state) => state.setPaymentMethod);
  const setResult = useRegistrationStore((state) => state.setResult);

  const [method, setMethod] = useState<PaymentMethod>(draft.paymentMethod);
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState('');

  const pkg = draft.pkg;
  const isPro = pkg?.is_per_apartment ?? false;
  const propertyCount = isPro ? draft.properties.length : 1;

  const { priceDisplay, numericTotal, isQuote } = useMemo(() => {
    if (!pkg) {
      return { priceDisplay: '', numericTotal: null as number | null, isQuote: false };
    }
    if (!isPro) {
      return { priceDisplay: `${pkg.price_year} KM`, numericTotal: pkg.price_year, isQuote: false };
    }
    if (propertyCount >= 10) {
      return { priceDisplay: 'Dogovor', numericTotal: null as number | null, isQuote: true };
    }
    const baseTotal = pkg.price_year * propertyCount;
    const tier = tierFor(propertyCount, pkg.volume_discount_tiers);
    const total = tier ? Math.round(baseTotal * (1 - tier.pct / 100)) : baseTotal;
    return { priceDisplay: `${total} KM`, numericTotal: total, isQuote: false };
  }, [pkg, isPro, propertyCount]);

  const cityName = useMemo(() => {
    if (isPro || !draft.cityId) {
      return null;
    }
    return citiesQuery.data?.find((city) => city.id === draft.cityId)?.name ?? null;
  }, [isPro, draft.cityId, citiesQuery.data]);

  if (!pkg) {
    return <SafeAreaView style={styles.container} />;
  }

  const summaryRows: Array<{ k: string; v: string }> = [
    {
      k: 'Uključeni izlasci',
      v: isPro ? `${pkg.visits_per_year} po stanu` : String(pkg.visits_per_year),
    },
    { k: 'Rok izlaska', v: `${pkg.deadline_hours} h` },
    { k: 'Popust na rad', v: `${pkg.labor_discount_pct}%` },
    isPro
      ? { k: 'Stanovi', v: `${propertyCount}` }
      : { k: 'Grad', v: cityName ?? 'Nije izabran' },
  ];

  const submitLabel = submitting
    ? 'Obrađujemo...'
    : isQuote
      ? 'Pošaljite zahtjev za ponudu'
      : `Potvrdite pretplatu${numericTotal != null ? ` · ${numericTotal} KM` : ''}`;

  const handleSubmit = async () => {
    setServerError('');
    setSubmitting(true);
    try {
      const properties: RegisterRequest['properties'] = isPro
        ? draft.properties.map((property) => ({
            city_id: property.cityId as number,
            street: property.street,
          }))
        : [{ city_id: draft.cityId as number, street: draft.street }];

      const payload: RegisterRequest = {
        package_id: pkg.id,
        name: draft.name,
        email: draft.email,
        password: draft.password,
        payment_method: method,
        properties,
      };

      const response = await registerRequest(payload);
      setPaymentMethod(method);
      setResult(response);

      if (response.status !== 'ponuda' && response.payment?.redirect_url) {
        navigation.replace('KarticaInfo');
      } else {
        // Ekran 07 čita stvarni subscription.status iz store-a (ne
        // pretpostavlja aktivaciju po payment_method-u, vidi napomenu na
        // vrhu fajla).
        navigation.replace('PretplataAktivna');
      }
    } catch (error) {
      if (error instanceof ApiError) {
        setServerError(error.message);
      } else {
        setServerError('Došlo je do greške. Pokušajte ponovo.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.header}>
        <View style={styles.backRow}>
          <Pressable
            accessibilityRole="button"
            onPress={() => navigation.goBack()}
            style={styles.backButton}
          >
            <Text style={styles.backGlyph}>‹</Text>
          </Pressable>
          <Text style={styles.stepLabel}>Korak 3 od 3</Text>
        </View>
        <View style={styles.progressRow}>
          <ProgressBar total={3} current={3} />
        </View>
        <Text style={styles.headline}>Plaćanje</Text>
      </View>

      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.summaryCard}>
          <View style={styles.summaryTop}>
            <Text style={styles.summaryName}>{pkg.name}</Text>
            <Text
              style={[styles.summaryPrice, isQuote && styles.summaryPriceQuote]}
            >
              {priceDisplay}
            </Text>
          </View>
          <View style={styles.summaryList}>
            {summaryRows.map((row) => (
              <View key={row.k} style={styles.summaryRow}>
                <Text style={styles.summaryKey}>{row.k}</Text>
                <Text style={styles.summaryValue}>{row.v}</Text>
              </View>
            ))}
          </View>
          {isPro && (
            <Text style={styles.estimateNote}>
              Procjena prije potvrde. Konačnu cijenu vraća server u odgovoru.
            </Text>
          )}
        </View>

        <Text style={styles.methodLabel}>Način plaćanja</Text>
        <View style={styles.methodList}>
          {METHODS.map((item) => {
            const isSelected = method === item.id;
            return (
              <Pressable
                key={item.id}
                accessibilityRole="radio"
                accessibilityState={{ selected: isSelected }}
                onPress={() => setMethod(item.id)}
                style={[
                  styles.methodTile,
                  isSelected ? styles.methodTileSelected : styles.methodTileDefault,
                ]}
              >
                <Text style={styles.methodTitle}>{item.title}</Text>
                <Text style={styles.methodDescription}>{item.description}</Text>
              </Pressable>
            );
          })}
        </View>

        {!!serverError && <Text style={styles.serverError}>{serverError}</Text>}

        <Button
          label={submitLabel}
          onPress={handleSubmit}
          disabled={submitting}
          style={styles.submitButton}
        />
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingHorizontal: 26,
    paddingTop: 22,
  },
  backRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    marginBottom: 18,
  },
  backButton: {
    minHeight: spacing.touchTargetMin,
    minWidth: spacing.touchTargetMin,
    justifyContent: 'center',
    alignItems: 'flex-start',
  },
  backGlyph: {
    fontFamily: fontFamily.semiBold,
    fontSize: 22,
    color: colors.ink,
  },
  stepLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    ...numeric,
  },
  progressRow: {
    marginBottom: 26,
  },
  headline: {
    ...typeScale.screenH2,
    color: colors.ink,
    letterSpacing: -0.4,
    marginBottom: 22,
  },
  scroll: {
    paddingHorizontal: 26,
    paddingBottom: 26,
    gap: 22,
  },
  summaryCard: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.ivory,
    padding: 18,
    paddingHorizontal: 20,
    gap: 12,
  },
  summaryTop: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
  },
  summaryName: {
    fontFamily: fontFamily.semiBold,
    fontSize: 19,
    color: colors.ink,
    letterSpacing: 0.4,
  },
  summaryPrice: {
    fontFamily: fontFamily.bold,
    fontSize: 26,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  summaryPriceQuote: {
    fontSize: 20,
  },
  summaryList: {
    borderTopWidth: 1,
    borderTopColor: colors.sand,
    paddingTop: 10,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 14,
    paddingVertical: 6,
  },
  summaryKey: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
  },
  summaryValue: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
    textAlign: 'right',
  },
  estimateNote: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    lineHeight: 18,
    color: colors.bark,
  },
  methodLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  methodList: {
    gap: 10,
  },
  methodTile: {
    borderWidth: 1,
    borderLeftWidth: 4,
    padding: 16,
    paddingHorizontal: 18,
    gap: 4,
  },
  methodTileDefault: {
    borderColor: colors.sand,
    borderLeftColor: colors.sand,
    backgroundColor: colors.white,
  },
  methodTileSelected: {
    borderColor: colors.ink,
    borderLeftColor: colors.ink,
    backgroundColor: colors.ivory,
  },
  methodTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 16,
    color: colors.ink,
  },
  methodDescription: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
  serverError: {
    borderWidth: 1,
    borderColor: colors.error,
    backgroundColor: colors.white,
    padding: 14,
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 20,
    color: colors.error,
  },
  submitButton: {
    marginTop: spacing.sm,
  },
});

export default PlacanjeScreen;
