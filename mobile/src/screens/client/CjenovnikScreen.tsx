/**
 * 14 Cjenovnik (design/README.md "Screens: mobile", prototip
 * data-screen-label="14 Cjenovnik"). Registrovan kao "Cjenovnik" u
 * PretplataStack, ulaz sa ekrana 13 Moja pretplata (dugme "Pogledajte
 * cjenovnik"): task je ostavio izbor ulaza timu, odluka i razlog su
 * dokumentovani u PretplataScreen.tsx zaglavlju.
 *
 * GET /client/price-list (curl-om potvrđeno, drugi krug verifikacije):
 * `data` je isti oblik kao javni /price-list (kategorije > pozicije), plus
 * `meta.my_package` sa popustima. `my_price` je već izračunata cijena sa
 * servera (CLAUDE.md: snižene cijene se NIKAD ne računaju na klijentu).
 *
 * Isti queryKey kao korak 1 prijave kvara (api/queries.ts
 * useClientPriceListQuery, `gcTime: Infinity`): dijeljen keš, jedan poziv
 * servisira i kategorije u wizardu i ovaj ekran, i oba čitaju iz cache-a
 * kad je uređaj offline (design/README.md "Offline: ... price list mora
 * čitati iz cache-a").
 */

import React, { useMemo, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useClientPriceListQuery } from '../../api/queries';
import { colors, fontFamily, numeric, spacing, typeScale } from '../../theme/tokens';
import type { PretplataStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<PretplataStackParamList, 'Cjenovnik'>;

const ALL_CHIP_ID = 0;

export function CjenovnikScreen() {
  const navigation = useNavigation<Nav>();
  const query = useClientPriceListQuery();
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<number>(ALL_CHIP_ID);

  const categories = query.data?.data ?? [];
  const laborDiscountPct = query.data?.meta.my_package.labor_discount_pct;

  const filteredGroups = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return categories
      .filter((category) => categoryFilter === ALL_CHIP_ID || category.id === categoryFilter)
      .map((category) => ({
        ...category,
        items: category.items.filter(
          (item) => needle.length === 0 || item.name.toLowerCase().includes(needle),
        ),
      }))
      .filter((category) => category.items.length > 0);
  }, [categories, categoryFilter, search]);

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <View style={styles.headerTopRow}>
          <Pressable
            accessibilityRole="button"
            onPress={() => navigation.goBack()}
            style={styles.backButton}
          >
            <Text style={styles.backGlyph}>‹</Text>
          </Pressable>
          <Text style={styles.headline}>Cjenovnik</Text>
        </View>
        {laborDiscountPct != null && (
          <Text style={styles.subline}>
            Vaše cijene su sa popustom od {laborDiscountPct}% na rad. Isti cjenovnik majstor
            otvori kod Vas.
          </Text>
        )}
      </View>

      {query.isLoading && <QueryLoadingNotice label="Učitavanje cjenovnika..." />}
      {query.isError && categories.length === 0 && (
        <QueryErrorNotice
          message="Nije moguće učitati cjenovnik. Provjerite internet vezu."
          onRetry={() => query.refetch()}
        />
      )}

      {categories.length > 0 && (
        <>
          <View style={styles.searchWrap}>
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Pretraga: baterija, bojler, brava"
              placeholderTextColor={colors.grey}
              style={styles.searchInput}
              autoCapitalize="none"
            />
          </View>

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.chipsScroll}
            contentContainerStyle={styles.chipsRow}
          >
            {[{ id: ALL_CHIP_ID, name: 'Sve' }, ...categories].map((category) => {
              const isSelected = categoryFilter === category.id;
              return (
                <Pressable
                  key={category.id}
                  accessibilityRole="button"
                  accessibilityState={{ selected: isSelected }}
                  onPress={() => setCategoryFilter(category.id)}
                  style={[styles.chip, isSelected ? styles.chipSelected : styles.chipDefault]}
                >
                  <Text style={[styles.chipLabel, isSelected && styles.chipLabelSelected]}>
                    {category.name}
                  </Text>
                </Pressable>
              );
            })}
          </ScrollView>

          <ScrollView contentContainerStyle={styles.list}>
            {filteredGroups.map((group) => (
              <View key={group.id} style={styles.section}>
                <Text style={styles.sectionHeader}>{group.name}</Text>
                <View style={styles.itemList}>
                  {group.items.map((item) => (
                    <View key={item.id} style={styles.itemRow}>
                      <Text style={styles.itemName}>{item.name}</Text>
                      <View style={styles.itemPrices}>
                        <Text style={styles.itemBasePrice}>{item.base_price} KM</Text>
                        <Text style={styles.itemMyPrice}>{item.my_price ?? item.base_price} KM</Text>
                      </View>
                    </View>
                  ))}
                </View>
              </View>
            ))}

            {filteredGroups.length === 0 && (
              <View style={styles.emptyBox}>
                <Text style={styles.emptyTitle}>Nema pozicije pod tim imenom</Text>
                <Text style={styles.emptyBody}>
                  Prijavite kvar i opišite šta se dešava. Dispečer nađe poziciju. Ako je nema,
                  majstor pita kancelariju pred vama.
                </Text>
              </View>
            )}
          </ScrollView>
        </>
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingHorizontal: 22,
    paddingTop: 20,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  headerTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 6,
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
  headline: {
    ...typeScale.sectionH2,
    color: colors.ink,
    letterSpacing: -0.3,
  },
  subline: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
    ...numeric,
  },
  searchWrap: {
    paddingHorizontal: 22,
    paddingTop: 16,
  },
  searchInput: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    minHeight: spacing.primaryButtonHeight,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  chipsScroll: {
    // Horizontalni ScrollView ne raste po vertikalnom paddingu contentContainera,
    // pa razmak nosi style: inace se chipovi preklope sa naslovom sekcije ispod.
    flexGrow: 0,
    marginTop: 14,
    marginBottom: 18,
  },
  chipsRow: {
    paddingHorizontal: 22,
    gap: 8,
  },
  chip: {
    borderWidth: 1,
    paddingHorizontal: 14,
    minHeight: 40,
    justifyContent: 'center',
  },
  chipDefault: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  chipSelected: {
    borderColor: colors.ink,
    backgroundColor: colors.ink,
  },
  chipLabel: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.ink,
  },
  chipLabelSelected: {
    color: colors.ivory,
  },
  list: {
    paddingHorizontal: 22,
    paddingBottom: 26,
  },
  section: {
    marginBottom: 22,
  },
  sectionHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 10,
  },
  itemList: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  itemRow: {
    backgroundColor: colors.white,
    paddingVertical: 12,
    paddingHorizontal: 15,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  itemName: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 20,
    color: colors.ink,
    flex: 1,
  },
  itemPrices: {
    alignItems: 'flex-end',
  },
  itemBasePrice: {
    fontFamily: fontFamily.regular,
    fontSize: 12,
    color: colors.bark,
    textDecorationLine: 'line-through',
    fontVariant: ['tabular-nums'],
  },
  itemMyPrice: {
    fontFamily: fontFamily.bold,
    fontSize: 16,
    color: colors.ink,
    backgroundColor: colors.ivory,
    paddingHorizontal: 6,
    fontVariant: ['tabular-nums'],
  },
  emptyBox: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 22,
  },
  emptyTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    color: colors.ink,
    marginBottom: 8,
  },
  emptyBody: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
});

export default CjenovnikScreen;
