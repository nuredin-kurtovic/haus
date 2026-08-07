/**
 * 08 Početna (design/README.md "Screens: mobile", prototip
 * data-screen-label="08 Klijent pocetna").
 *
 * Podaci sa GET /client/dashboard (docs/API.md, curl-om potvrđeno drugi
 * krug verifikacije): pozdrav koristi auth store korisnika (ime, ne
 * dashboard), sve ostalo dolazi sa dashboard-a. 2x2 grid prikazuje tačno
 * ono što task traži (paket, vrijedi do, preostali izlasci, besplatne
 * intervencije), NE prototipovu demo verziju (Paket/Preostalo/Vaš rok/
 * Pregled): dashboard.subscription nema deadline_hours ni datum pregleda,
 * pa bi "Vaš rok"/"Pregled" ćelije bile izmišljene.
 *
 * "Nalog u toku" karticu koraci NEMAJU vrijednosti po koraku (prototip ima
 * "08:12"/"Damir H." itd, ali Job::steps() vraća samo {key,label,done}):
 * prikazuje se samo hairline lista sa 10px kvadratima, ember kad je done.
 *
 * "Zadnje intervencije" redovi koriste RecentJobSummary
 * (DashboardController::zadnjiNalozi), koji NEMA title/description: red
 * prikazuje kategoriju kao glavni tekst (ne opis, koji server ovdje ne
 * šalje).
 */

import React from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useClientDashboardQuery } from '../../api/queries';
import { useAuthStore } from '../../store/auth';
import { formatDate } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { ClientTabParamList } from '../../navigation/types';

// Sva navigacija sa Početne ide na drugi tab (Profil/Prijavi/Nalozi), nikad
// na root stack: obična BottomTabNavigationProp je dovoljna, composite sa
// RootStackParamList je nepotreban i pravi probleme sa overload
// razrješavanjem (navigate() bira pogrešan preklop kad se dva param liste
// spoje bez potrebe).
type Nav = BottomTabNavigationProp<ClientTabParamList, 'Pocetna'>;

function firstName(fullName: string | undefined): string {
  if (!fullName) {
    return '';
  }
  return fullName.trim().split(/\s+/)[0] ?? fullName;
}

export function PocetnaScreen() {
  const navigation = useNavigation<Nav>();
  const user = useAuthStore((state) => state.user);
  const query = useClientDashboardQuery();
  const dashboard = query.data;

  const kartice = dashboard
    ? [
        { k: 'Paket', v: dashboard.subscription?.package.name ?? 'Nema pretplate' },
        { k: 'Vrijedi do', v: formatDate(dashboard.subscription?.ends_at) ?? 'Nema' },
        { k: 'Preostali izlasci', v: String(dashboard.subscription?.remaining_visits ?? 0) },
        { k: 'Besplatne intervencije', v: String(dashboard.subscription?.free_interventions ?? 0) },
      ]
    : [];

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl refreshing={query.isFetching} onRefresh={() => query.refetch()} />
      }
    >
      <View style={styles.headerRow}>
        <View>
          <Text style={styles.greetingSmall}>Dobar dan,</Text>
          <Text style={styles.greetingName}>{firstName(user?.name) || 'Vi'}</Text>
        </View>
        <Pressable
          accessibilityRole="button"
          onPress={() => navigation.navigate('Profil', undefined)}
          style={styles.packageButton}
        >
          <Text style={styles.packageButtonLabel}>
            {dashboard?.subscription?.package.name ?? 'Profil'}
          </Text>
        </Pressable>
      </View>

      <View style={styles.body}>
        {query.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
        {query.isError && !dashboard && (
          <QueryErrorNotice onRetry={() => query.refetch()} />
        )}

        {!!dashboard && (
          <>
            <View style={styles.ctaWrap}>
              <View style={styles.ctaInner}>
                <Text style={styles.ctaHeadline}>Nešto se pokvarilo?</Text>
                <Text style={styles.ctaBody}>
                  Prijava traje minutu. Termin dobijete u prozoru od dva sata.
                </Text>
                <Pressable
                  accessibilityRole="button"
                  onPress={() => navigation.navigate('Prijavi', { screen: 'PrijaviKvar' })}
                  style={styles.ctaButton}
                >
                  <Text style={styles.ctaButtonLabel}>Prijavite kvar</Text>
                </Pressable>
              </View>
            </View>

            <View style={styles.grid}>
              {kartice.map((cell) => (
                <View key={cell.k} style={styles.gridCell}>
                  <Text style={styles.gridLabel}>{cell.k}</Text>
                  <Text style={styles.gridValue}>{cell.v}</Text>
                </View>
              ))}
            </View>

            {!!dashboard.active_job && (
              <View style={styles.activeCard}>
                <View style={styles.activeHeader}>
                  <Text style={styles.activeHeaderLabel}>Nalog u toku</Text>
                  <Text style={styles.activeHeaderNumber}>{dashboard.active_job.number}</Text>
                </View>
                <View style={styles.activeBody}>
                  <Text style={styles.activeCategory}>
                    {dashboard.active_job.category ?? 'Nalog'}
                  </Text>
                  <View style={styles.stepsList}>
                    {dashboard.active_job.steps.map((step) => (
                      <View key={step.key} style={styles.stepRow}>
                        <View
                          style={[
                            styles.stepDot,
                            { backgroundColor: step.done ? colors.ember : colors.white },
                          ]}
                        />
                        <Text
                          style={[
                            styles.stepLabel,
                            step.done && styles.stepLabelDone,
                          ]}
                        >
                          {step.label}
                        </Text>
                      </View>
                    ))}
                  </View>
                </View>
              </View>
            )}

            <View style={styles.sectionHeaderRow}>
              <Text style={styles.sectionHeader}>Zadnje intervencije</Text>
              {dashboard.recent_jobs.length > 0 && (
                <Pressable
                  accessibilityRole="button"
                  onPress={() => navigation.navigate('Nalozi', { screen: 'NaloziList' })}
                >
                  <Text style={styles.seeAllLink}>Sve</Text>
                </Pressable>
              )}
            </View>

            {dashboard.recent_jobs.length === 0 ? (
              <Text style={styles.emptyText}>
                Nemate još nijednu intervenciju. Prijavite kvar kad se nešto pokvari.
              </Text>
            ) : (
              <View style={styles.recentList}>
                {dashboard.recent_jobs.map((job) => (
                  <Pressable
                    key={job.id}
                    accessibilityRole="button"
                    onPress={() =>
                      navigation.navigate('Nalozi', {
                        screen: 'NalogDetalj',
                        params: { jobId: job.id },
                      })
                    }
                    style={styles.recentRow}
                  >
                    <View style={styles.recentRowText}>
                      <Text style={styles.recentCategory}>{job.category ?? 'Nalog'}</Text>
                      <Text style={styles.recentMeta}>
                        {job.number} · {formatDate(job.created_at)}
                      </Text>
                    </View>
                    <StateChip state={chipStateForJob(job)} />
                  </Pressable>
                ))}
              </View>
            )}
          </>
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  content: {
    paddingBottom: 32,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
    paddingHorizontal: 22,
    paddingTop: 20,
    paddingBottom: 22,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  greetingSmall: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
  },
  greetingName: {
    fontFamily: fontFamily.semiBold,
    fontSize: 20,
    color: colors.ink,
    lineHeight: 24,
  },
  packageButton: {
    borderWidth: 1,
    borderColor: colors.ink,
    paddingHorizontal: 13,
    minHeight: spacing.touchTargetMin,
    justifyContent: 'center',
  },
  packageButtonLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 13,
    color: colors.ink,
  },
  body: {
    paddingHorizontal: 22,
    paddingTop: 22,
  },
  ctaWrap: {
    backgroundColor: colors.ember,
    padding: 2,
    marginBottom: 22,
  },
  ctaInner: {
    backgroundColor: colors.ivory,
    padding: 22,
  },
  ctaHeadline: {
    fontFamily: fontFamily.bold,
    fontSize: 24,
    lineHeight: 28,
    color: colors.ink,
    marginBottom: 8,
  },
  ctaBody: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
    marginBottom: 18,
  },
  ctaButton: {
    backgroundColor: colors.ember,
    minHeight: spacing.primaryButtonHeight,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ctaButtonLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 16,
    color: colors.ivory,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
    gap: 1,
    marginBottom: 26,
  },
  gridCell: {
    width: '49.85%',
    backgroundColor: colors.white,
    paddingVertical: 16,
    paddingHorizontal: 15,
  },
  gridLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 8,
  },
  gridValue: {
    fontFamily: fontFamily.bold,
    fontSize: 20,
    lineHeight: 24,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  activeCard: {
    borderWidth: 1,
    borderColor: colors.ink,
    marginBottom: 26,
  },
  activeHeader: {
    backgroundColor: colors.ink,
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  activeHeaderLabel: {
    ...typeScale.eyebrow,
    color: colors.sand,
  },
  activeHeaderNumber: {
    fontFamily: fontFamily.semiBold,
    fontSize: 12,
    color: colors.ivory,
    fontVariant: ['tabular-nums'],
  },
  activeBody: {
    paddingHorizontal: 16,
    paddingVertical: 18,
  },
  activeCategory: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    lineHeight: 23,
    color: colors.ink,
    marginBottom: 12,
  },
  stepsList: {
    borderTopWidth: 1,
    borderTopColor: colors.sand,
  },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    paddingVertical: 11,
  },
  stepDot: {
    width: 10,
    height: 10,
    borderWidth: 1,
    borderColor: colors.ink,
  },
  stepLabel: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
    flex: 1,
  },
  stepLabelDone: {
    fontFamily: fontFamily.medium,
    color: colors.ink,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  seeAllLink: {
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    textDecorationLine: 'underline',
    textDecorationColor: colors.ember,
  },
  emptyText: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
  recentList: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  recentRow: {
    backgroundColor: colors.white,
    paddingVertical: 15,
    paddingHorizontal: 16,
    minHeight: 56,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  recentRowText: {
    flex: 1,
  },
  recentCategory: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    color: colors.ink,
    marginBottom: 3,
  },
  recentMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
});

export default PocetnaScreen;
