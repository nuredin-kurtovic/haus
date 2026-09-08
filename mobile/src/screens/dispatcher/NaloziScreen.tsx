/**
 * Dispečer: 17 Nalozi (design/README.md, prototip data-screen-label="17
 * Dispecer nalozi"). Filter chipovi sa brojačima (meta.counts, prati
 * pretragu `q`, ne filter stanja, docs/API.md), redovi sa 4px lijevom
 * linijom (ember za hitne), StateChip, broj + HITNO marker, opis, dvije
 * tabular meta linije (rok; termin/majstor). Pretraga q. Paginacija:
 * dugme "Učitajte još" (task: "jednostavno").
 *
 * GET /admin/jobs curl-om potvrđeno protiv php artisan serve --port=8008
 * (treći krug verifikacije): `{data, meta: {counts, total, per_page,
 * current_page, last_page}}`.
 */

import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useAdminJobsInfiniteQuery } from '../../api/queries';
import { formatDateTime, formatWindowRange } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { AdminJobListItem, JobStatus } from '../../api/types';
import type { DispatcherNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<DispatcherNaloziStackParamList, 'NaloziList'>;

type FilterValue = 'svi' | JobStatus;

const FILTERS: Array<{ value: FilterValue; label: string; countKey: 'ukupno' | JobStatus }> = [
  { value: 'svi', label: 'Svi', countKey: 'ukupno' },
  { value: 'novo', label: 'Novo', countKey: 'novo' },
  { value: 'zakazano', label: 'Zakazano', countKey: 'zakazano' },
  { value: 'u_toku', label: 'U toku', countKey: 'u_toku' },
  { value: 'zavrseno', label: 'Završeno', countKey: 'zavrseno' },
];

export function NaloziScreen() {
  const navigation = useNavigation<Nav>();
  const [filter, setFilter] = useState<FilterValue>('svi');
  const [searchInput, setSearchInput] = useState('');
  const [q, setQ] = useState('');

  useEffect(() => {
    const timer = setTimeout(() => setQ(searchInput.trim()), 350);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const query = useAdminJobsInfiniteQuery({
    status: filter === 'svi' ? undefined : filter,
    q,
  });

  const jobs: AdminJobListItem[] = query.data?.pages.flatMap((page) => page.data) ?? [];
  const counts = query.data?.pages[0]?.meta.counts;

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <Text style={styles.headline}>Nalozi</Text>
        <TextInput
          value={searchInput}
          onChangeText={setSearchInput}
          placeholder="Pretraga: broj, opis, klijent, ulica"
          placeholderTextColor={colors.grey}
          style={styles.searchInput}
          autoCapitalize="none"
        />
        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={FILTERS}
          keyExtractor={(item) => item.value}
          contentContainerStyle={styles.filterRow}
          renderItem={({ item }) => {
            const isSelected = filter === item.value;
            const count = counts ? counts[item.countKey] : undefined;
            return (
              <Pressable
                accessibilityRole="button"
                accessibilityState={{ selected: isSelected }}
                onPress={() => setFilter(item.value)}
                style={[styles.chip, isSelected ? styles.chipSelected : styles.chipDefault]}
              >
                <Text style={[styles.chipLabel, isSelected && styles.chipLabelSelected]}>
                  {item.label}{count != null ? ` · ${count}` : ''}
                </Text>
              </Pressable>
            );
          }}
        />
      </View>

      {query.isLoading && <QueryLoadingNotice label="Učitavanje naloga..." />}
      {query.isError && jobs.length === 0 && (
        <QueryErrorNotice
          message="Nije moguće učitati naloge. Provjerite internet vezu."
          onRetry={() => query.refetch()}
        />
      )}
      {!query.isLoading && jobs.length === 0 && !query.isError && (
        <View style={styles.emptyBox}>
          <Text style={styles.emptyText}>
            Nema naloga za ovaj filter i pretragu. Promijenite filter ili pretragu.
          </Text>
        </View>
      )}

      <FlatList
        data={jobs}
        keyExtractor={(job) => String(job.id)}
        contentContainerStyle={styles.list}
        renderItem={({ item: job }) => {
          const windowLabel = formatWindowRange(job.scheduled_window_start, job.scheduled_window_end);
          return (
            <Pressable
              accessibilityRole="button"
              onPress={() => navigation.navigate('NalogDodjela', { jobId: job.id })}
              style={styles.row}
            >
              <View style={[styles.leftBar, job.is_emergency && styles.leftBarEmergency]} />
              <View style={styles.rowContent}>
                <View style={styles.topRow}>
                  <StateChip state={chipStateForJob(job)} />
                  <Text style={styles.numberText}>{job.number}</Text>
                  {job.is_emergency && <Text style={styles.hitnoText}>HITNO</Text>}
                </View>
                <Text style={styles.title}>{job.title}</Text>
                <Text style={styles.metaText}>Rok {formatDateTime(job.deadline_at)}</Text>
                <Text style={styles.metaText}>
                  {windowLabel ?? 'Nije zakazano'} · {job.technician?.name ?? 'Bez majstora'}
                </Text>
              </View>
            </Pressable>
          );
        }}
        ListFooterComponent={
          query.hasNextPage ? (
            <Pressable
              accessibilityRole="button"
              onPress={() => query.fetchNextPage()}
              disabled={query.isFetchingNextPage}
              style={styles.loadMoreButton}
            >
              {query.isFetchingNextPage ? (
                <ActivityIndicator color={colors.ink} />
              ) : (
                <Text style={styles.loadMoreLabel}>Učitajte još</Text>
              )}
            </Pressable>
          ) : null
        }
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingTop: 20,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    gap: 12,
  },
  headline: {
    ...typeScale.sectionH2,
    color: colors.ink,
    letterSpacing: -0.3,
    paddingHorizontal: 22,
  },
  searchInput: {
    marginHorizontal: 22,
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    minHeight: 48,
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.ink,
  },
  filterRow: {
    paddingHorizontal: 22,
    gap: 6,
  },
  chip: {
    borderWidth: 1,
    paddingHorizontal: 12,
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
    fontVariant: ['tabular-nums'],
  },
  chipLabelSelected: {
    color: colors.ivory,
  },
  emptyBox: {
    padding: 26,
  },
  emptyText: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
  },
  list: {
    paddingBottom: 26,
  },
  row: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  leftBar: {
    width: 4,
    backgroundColor: 'transparent',
  },
  leftBarEmergency: {
    backgroundColor: colors.ember,
  },
  rowContent: {
    flex: 1,
    paddingVertical: 15,
    paddingHorizontal: 18,
    gap: 5,
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  numberText: {
    fontFamily: fontFamily.medium,
    fontSize: 12,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  hitnoText: {
    fontFamily: fontFamily.bold,
    fontSize: 11,
    letterSpacing: 0.7,
    color: colors.ink,
  },
  title: {
    fontFamily: fontFamily.medium,
    fontSize: 16,
    lineHeight: 21,
    color: colors.ink,
  },
  metaText: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  loadMoreButton: {
    margin: 22,
    borderWidth: 1,
    borderColor: colors.ink,
    minHeight: spacing.touchTargetMin + 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadMoreLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
  },
});

export default NaloziScreen;
