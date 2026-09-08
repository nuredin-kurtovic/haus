/**
 * 04 Izbor paketa, registracija korak 1 od 3 (design/README.md, prototip
 * data-screen-label="04 Registracija paket").
 *
 * Paketi dolaze sa GET /packages (docs/API.md), nikad hardkodirano
 * (CLAUDE.md). Ugovorni Package tip (src/api/types.ts, curl-om potvrđen)
 * nema posebno "jedna rečenica" polje: nema `price`, nema `features`.
 * Sažetak kartice se IZVODI iz brojčanih parametara (visits_per_year,
 * deadline_hours, labor_discount_pct), npr. "1 izlazak, rok 72 h, 15%
 * popusta na rad" / za Pro "5 izlazaka po stanu, rok 24 h, 25% popusta na
 * rad" (lead review napomena).
 */

import React, { useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { usePackagesQuery } from '../../api/queries';
import { useRegistrationStore } from '../../store/registration';
import { pluralize } from '../../utils/plural';
import { colors, fontFamily, numeric, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';
import type { Package } from '../../api/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

function packageSummary(pkg: Package): string {
  const word = pluralize(pkg.visits_per_year, ['izlazak', 'izlaska', 'izlazaka']);
  const perApartment = pkg.is_per_apartment ? ' po stanu' : '';
  return `${pkg.visits_per_year} ${word}${perApartment}, rok ${pkg.deadline_hours} h, ${pkg.labor_discount_pct}% popusta na rad`;
}

function packageUnit(pkg: Package): string {
  return pkg.is_per_apartment ? 'po stanu godišnje' : 'godišnje';
}

export function IzborPaketaScreen() {
  const navigation = useNavigation<Nav>();
  const query = usePackagesQuery();
  const setPackage = useRegistrationStore((state) => state.setPackage);
  const storedPackage = useRegistrationStore((state) => state.pkg);
  const [selectedId, setSelectedId] = useState<number | null>(
    storedPackage?.id ?? null,
  );

  const packages = query.data ?? [];
  const selected = packages.find((pkg) => pkg.id === selectedId) ?? null;

  const handleNext = () => {
    if (!selected) {
      return;
    }
    setPackage(selected);
    navigation.navigate('PodaciAdresa');
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
          <Text style={styles.stepLabel}>Korak 1 od 3</Text>
        </View>
        <View style={styles.progressRow}>
          <ProgressBar total={3} current={1} />
        </View>
        <Text style={styles.headline}>Izaberite paket</Text>
        <Text style={styles.paragraph}>
          Plaća se jednom godišnje. Paket možete promijeniti pri obnovi.
        </Text>
      </View>

      <ScrollView contentContainerStyle={styles.list}>
        {query.isLoading && <QueryLoadingNotice label="Učitavanje paketa..." />}
        {query.isError && (
          <QueryErrorNotice
            message="Nije moguće učitati pakete. Provjerite internet vezu."
            onRetry={() => query.refetch()}
          />
        )}
        {!query.isLoading &&
          !query.isError &&
          packages.map((pkg: Package) => {
            const isSelected = pkg.id === selectedId;
            return (
              <Pressable
                key={pkg.id}
                accessibilityRole="button"
                accessibilityState={{ selected: isSelected }}
                onPress={() => setSelectedId(pkg.id)}
                style={[
                  styles.card,
                  isSelected ? styles.cardSelected : styles.cardDefault,
                ]}
              >
                <View style={styles.cardTop}>
                  <Text style={styles.cardName}>{pkg.name}</Text>
                  <View style={styles.cardPriceBlock}>
                    <Text style={styles.cardPrice}>{pkg.price_year} KM</Text>
                    <Text style={styles.cardPriceUnit}>{packageUnit(pkg)}</Text>
                  </View>
                </View>
                <Text style={styles.cardSummary}>{packageSummary(pkg)}</Text>
              </Pressable>
            );
          })}
      </ScrollView>

      <View style={styles.footer}>
        <Button
          label="Nastavite"
          disabled={!selected}
          onPress={handleNext}
        />
      </View>
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
    marginBottom: 8,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.bark,
    marginBottom: 22,
  },
  list: {
    paddingHorizontal: 26,
    gap: 10,
    paddingBottom: spacing.lg,
  },
  card: {
    borderWidth: 1,
    borderLeftWidth: 4,
    padding: 18,
    paddingTop: 18,
    paddingBottom: 20,
  },
  cardDefault: {
    borderColor: colors.sand,
    borderLeftColor: colors.sand,
    backgroundColor: colors.white,
  },
  cardSelected: {
    borderColor: colors.ink,
    borderLeftColor: colors.ember,
    backgroundColor: colors.ivory,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: 12,
    marginBottom: 10,
  },
  cardName: {
    fontFamily: fontFamily.semiBold,
    fontSize: 18,
    color: colors.ink,
    letterSpacing: 0.4,
  },
  cardPriceBlock: {
    alignItems: 'flex-end',
  },
  cardPrice: {
    fontFamily: fontFamily.bold,
    fontSize: 24,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  cardPriceUnit: {
    fontFamily: fontFamily.regular,
    fontSize: 12,
    color: colors.bark,
    marginTop: 1,
  },
  cardSummary: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
    ...numeric,
    borderTopWidth: 1,
    borderTopColor: colors.sand,
    paddingTop: 11,
  },
  footer: {
    paddingHorizontal: 26,
    paddingVertical: 26,
  },
});

export default IzborPaketaScreen;
