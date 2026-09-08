/**
 * 11 Moje intervencije (design/README.md "Screens: mobile", prototip
 * data-screen-label="11 Moje intervencije"). Registrovan kao "NaloziList"
 * u NaloziStack (vidi navigation/NaloziStack.tsx).
 *
 * Lista GET /client/jobs (curl-om potvrđeno, drugi krug verifikacije):
 * JobListItem ima `technician: {name}|null` (ne string) i
 * `is_emergency` (za HITNO marker), category je naziv iz cjenovnika.
 *
 * Offline (design/README.md "Offline: job list mora čitati iz cache-a"):
 * AsyncStorage NIJE instaliran (native dep, van scope-a ove faze), pa se
 * "keširanje" radi u memoriji preko TanStack Query (`gcTime: Infinity` na
 * useClientJobsQuery, vidi api/queries.ts). To znači da lista ostaje
 * vidljiva ako refetch padne DOK JE APLIKACIJA POKRENUTA (isti session);
 * restart aplikacije briše keš jer nema diska. Pravi offline persist dolazi
 * kad AsyncStorage uđe u zavisnosti; ovdje je samo dokumentovano, ne
 * pretvarano da postoji.
 */

import React from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useClientJobsQuery } from '../../api/queries';
import { formatDate } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, typeScale } from '../../theme/tokens';
import type { JobListItem } from '../../api/types';
import type { NaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<NaloziStackParamList, 'NaloziList'>;

function Separator() {
  return <View style={styles.separator} />;
}

export function NaloziScreen() {
  const navigation = useNavigation<Nav>();
  const query = useClientJobsQuery();
  const jobs = query.data ?? [];

  const renderItem = ({ item }: { item: JobListItem }) => (
    <Pressable
      accessibilityRole="button"
      onPress={() => navigation.navigate('NalogDetalj', { jobId: item.id })}
      style={styles.row}
    >
      <View style={[styles.emergencyRule, item.is_emergency && styles.emergencyRuleActive]} />
      <View style={styles.rowContent}>
        <View style={styles.rowTop}>
          <StateChip state={chipStateForJob(item)} />
          <Text style={styles.number}>{item.number}</Text>
          {item.is_emergency && <Text style={styles.hitno}>HITNO</Text>}
        </View>
        <Text style={styles.title}>{item.title}</Text>
        <Text style={styles.meta}>
          {item.category ?? 'Nalog'}
          {item.technician ? ` · ${item.technician.name}` : ''} · {formatDate(item.created_at)}
        </Text>
      </View>
    </Pressable>
  );

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <Text style={styles.headline}>Moje intervencije</Text>
      </View>

      {query.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
      {query.isError && jobs.length === 0 && (
        <QueryErrorNotice onRetry={() => query.refetch()} />
      )}
      {!query.isLoading && !query.isError && jobs.length === 0 && (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>
            Nemate još nijednu intervenciju. Prijavite kvar kad se nešto pokvari.
          </Text>
        </View>
      )}

      {jobs.length > 0 && (
        <FlatList
          data={jobs}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderItem}
          ItemSeparatorComponent={Separator}
          refreshControl={
            <RefreshControl refreshing={query.isFetching} onRefresh={() => query.refetch()} />
          }
        />
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
    paddingBottom: 18,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  headline: {
    ...typeScale.sectionH2,
    color: colors.ink,
    letterSpacing: -0.3,
  },
  separator: {
    height: 1,
    backgroundColor: colors.sand,
  },
  row: {
    flexDirection: 'row',
    paddingVertical: 18,
    paddingRight: 22,
  },
  emergencyRule: {
    width: 4,
    backgroundColor: 'transparent',
  },
  emergencyRuleActive: {
    backgroundColor: colors.ember,
  },
  rowContent: {
    flex: 1,
    paddingLeft: 18,
    gap: 8,
  },
  rowTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  number: {
    fontFamily: fontFamily.medium,
    fontSize: 12,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  hitno: {
    fontFamily: fontFamily.semiBold,
    fontSize: 11,
    letterSpacing: 0.6,
    color: colors.error,
  },
  title: {
    fontFamily: fontFamily.medium,
    fontSize: 17,
    lineHeight: 23,
    color: colors.ink,
  },
  meta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  empty: {
    padding: 22,
  },
  emptyText: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
  },
});

export default NaloziScreen;
