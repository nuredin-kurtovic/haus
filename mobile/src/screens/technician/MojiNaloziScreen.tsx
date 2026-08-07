/**
 * Serviser: "Moji nalozi" (task A.1). GET /technician/jobs (curl-om
 * potvrđeno protiv php artisan serve --port=8008, treći krug verifikacije):
 * `{data: TechnicianJobListItem[]}`, samo nalozi dodijeljeni ulogovanom
 * majstoru (docs/API.md).
 *
 * Grupisanje po datumu prozora (Danas / Sutra / dd.mm.gggg., task
 * zahtjev): nalozi bez prozora (nema termina, npr. tek dodijeljen bez
 * zakazivanja) idu u grupu "Bez termina", prikazanu prvu jer traži pažnju
 * (nema termin, a već je na majstoru). Ostale grupe idu hronološki.
 *
 * Red: 4px ember lijeva linija za hitne (isti obrazac kao dispečerska
 * lista, ekran 17), 82px prozor kolona (tabular, kao dispečerski raspored,
 * ekran 16), pa StateChip + broj + HITNO marker + kategorija + klijent +
 * adresa. Filter chipovi po statusu (brojači se računaju lokalno iz
 * učitane liste: /technician/jobs nema meta.counts kao dispečerska lista).
 */

import React, { useMemo, useState } from 'react';
import {
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useTechnicianJobsQuery } from '../../api/queries';
import { dateGroupLabel, formatDateTime, formatWindowRange } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { JobStatus } from '../../api/types';
import type { TechnicianJobListItem } from '../../api/types';
import type { TechnicianNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<TechnicianNaloziStackParamList, 'MojiNalozi'>;

type FilterValue = 'svi' | JobStatus;

const FILTERS: Array<{ value: FilterValue; label: string }> = [
  { value: 'svi', label: 'Svi' },
  { value: 'zakazano', label: 'Zakazano' },
  { value: 'u_toku', label: 'U toku' },
  { value: 'zavrseno', label: 'Završeno' },
  { value: 'novo', label: 'Novo' },
];

interface Group {
  label: string;
  sortTs: number;
  jobs: TechnicianJobListItem[];
}

interface Row {
  type: 'header' | 'job';
  key: string;
  label?: string;
  job?: TechnicianJobListItem;
}

function windowSortTs(job: TechnicianJobListItem): number {
  if (!job.scheduled_window_start) {
    return -1;
  }
  const ts = new Date(job.scheduled_window_start).getTime();
  return Number.isNaN(ts) ? -1 : ts;
}

export function MojiNaloziScreen() {
  const navigation = useNavigation<Nav>();
  const query = useTechnicianJobsQuery();
  const jobs = query.data ?? [];
  const [filter, setFilter] = useState<FilterValue>('svi');

  const counts = useMemo(() => {
    const out: Record<FilterValue, number> = {
      svi: jobs.length,
      novo: 0,
      zakazano: 0,
      u_toku: 0,
      zavrseno: 0,
    };
    jobs.forEach((job) => {
      out[job.status] += 1;
    });
    return out;
  }, [jobs]);

  const visibleJobs = useMemo(
    () => (filter === 'svi' ? jobs : jobs.filter((job) => job.status === filter)),
    [jobs, filter],
  );

  const rows = useMemo<Row[]>(() => {
    const groups = new Map<string, Group>();
    visibleJobs.forEach((job) => {
      const label = dateGroupLabel(job.scheduled_window_start);
      const ts = windowSortTs(job);
      const existing = groups.get(label);
      if (existing) {
        existing.jobs.push(job);
        existing.sortTs = Math.min(existing.sortTs, ts);
      } else {
        groups.set(label, { label, sortTs: ts, jobs: [job] });
      }
    });

    const sortedGroups = Array.from(groups.values()).sort((a, b) => a.sortTs - b.sortTs);

    const out: Row[] = [];
    sortedGroups.forEach((group) => {
      group.jobs.sort((a, b) => windowSortTs(a) - windowSortTs(b));
      out.push({ type: 'header', key: `header-${group.label}`, label: group.label });
      group.jobs.forEach((job) => {
        out.push({ type: 'job', key: `job-${job.id}`, job });
      });
    });
    return out;
  }, [visibleJobs]);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headline}>Moji nalozi</Text>
        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={FILTERS}
          keyExtractor={(item) => item.value}
          contentContainerStyle={styles.filterRow}
          renderItem={({ item }) => {
            const isSelected = filter === item.value;
            return (
              <Pressable
                accessibilityRole="button"
                accessibilityState={{ selected: isSelected }}
                onPress={() => setFilter(item.value)}
                style={[styles.chip, isSelected ? styles.chipSelected : styles.chipDefault]}
              >
                <Text style={[styles.chipLabel, isSelected && styles.chipLabelSelected]}>
                  {item.label} · {counts[item.value]}
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
            Nemate dodijeljenih naloga. Dispečer Vam ih dodjeljuje iz svog pregleda.
          </Text>
        </View>
      )}

      <FlatList
        data={rows}
        keyExtractor={(row) => row.key}
        refreshControl={
          <RefreshControl refreshing={query.isFetching} onRefresh={() => query.refetch()} />
        }
        contentContainerStyle={rows.length > 0 ? styles.list : styles.listEmpty}
        renderItem={({ item }) => {
          if (item.type === 'header') {
            return <Text style={styles.groupHeader}>{item.label}</Text>;
          }
          const job = item.job as TechnicianJobListItem;
          const windowLabel = formatWindowRange(job.scheduled_window_start, job.scheduled_window_end);
          return (
            <Pressable
              accessibilityRole="button"
              onPress={() => navigation.navigate('NalogDetalj', { jobId: job.id })}
              style={styles.row}
            >
              <View style={[styles.leftBar, job.is_emergency && styles.leftBarEmergency]} />
              <View style={styles.windowCol}>
                <Text style={styles.windowText}>{windowLabel ?? 'Nema termina'}</Text>
              </View>
              <View style={styles.contentCol}>
                <View style={styles.topRow}>
                  <StateChip state={chipStateForJob(job)} />
                  <Text style={styles.numberText}>{job.number}</Text>
                  {job.is_emergency && <Text style={styles.hitnoText}>HITNO</Text>}
                </View>
                <Text style={styles.categoryText}>{job.category ?? 'Nalog'}</Text>
                <Text style={styles.metaText}>
                  {job.client.name} · {job.address.street ?? ''}
                  {job.address.city ? `, ${job.address.city}` : ''}
                </Text>
                {!windowLabel && (
                  <Text style={styles.metaText}>Rok {formatDateTime(job.deadline_at)}</Text>
                )}
              </View>
            </Pressable>
          );
        }}
      />
    </View>
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
  },
  headline: {
    ...typeScale.sectionH2,
    color: colors.ink,
    letterSpacing: -0.3,
    paddingHorizontal: 22,
    marginBottom: 12,
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
  listEmpty: {
    flexGrow: 1,
  },
  groupHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
    paddingHorizontal: 22,
    paddingTop: 18,
    paddingBottom: 8,
  },
  row: {
    flexDirection: 'row',
    minHeight: spacing.touchTargetMin + 20,
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
  windowCol: {
    width: 82,
    paddingVertical: 14,
    paddingLeft: 14,
    paddingRight: 6,
  },
  windowText: {
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  contentCol: {
    flex: 1,
    paddingVertical: 14,
    paddingRight: 20,
    gap: 4,
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
  categoryText: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    lineHeight: 20,
    color: colors.ink,
  },
  metaText: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
});

export default MojiNaloziScreen;
