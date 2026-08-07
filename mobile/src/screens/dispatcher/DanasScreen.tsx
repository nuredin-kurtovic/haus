/**
 * Dispečer: 16 Danas (design/README.md, prototip data-screen-label="16
 * Dispecer danas"). Ink zaglavlje sa ivory wordmarkom (task pravilo: "ink
 * zaglavlja samo na Danas + potvrdnim ekranima"), 2x2 KPI grid, ember-
 * headed panel "Rokovi danas", raspored kao 82px 1fr lista.
 *
 * GET /admin/dashboard (curl-om potvrđeno protiv php artisan serve
 * --port=8008, treći krug verifikacije): `kpi` ima 5 polja
 * (novi_danas/aktivni_nalozi/rokovi_danas/prosjek_zavrsetka_h/
 * aktivne_pretplate); task traži 2x2 (4 ćelije), pa je `prosjek_zavrsetka_h`
 * izostavljen iz grida (prosjek sati je manje operativan za "šta gori
 * danas" nego preostala četiri), odluka dokumentovana i u izvještaju.
 *
 * `schedule_today[].window` je TAČAN tekst sa servera (npr. "10:00 do
 * 12:00", riječ "do", ne en dash): prikazuje se kako stigne, ne
 * reformatira se (CLAUDE.md: nikad ne izmišljati format koji server već
 * određuje).
 *
 * Logo: react-native-svg nije instaliran i nema rasterizovane ivory verzije
 * (samo logo-primary.png postoji, za light pozadine, vidi WelcomeScreen.tsx
 * napomenu); "HAUS" wordmark je ivory text kao pragmatična zamjena, isti
 * princip koji je već ustanovljen u prethodnoj fazi. Otvoreno pitanje,
 * dokumentovano u izvještaju.
 */

import React from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useAdminDashboardQuery } from '../../api/queries';
import { formatDateTime } from '../../utils/format';
import { colors, fontFamily, typeScale } from '../../theme/tokens';
import type { AdminJobListItem } from '../../api/types';
import type { DispatcherTabParamList } from '../../navigation/types';

type Nav = BottomTabNavigationProp<DispatcherTabParamList, 'Danas'>;

function pad2(value: number): string {
  return String(value).padStart(2, '0');
}

function todayLabel(): string {
  const now = new Date();
  return `${pad2(now.getDate())}.${pad2(now.getMonth() + 1)}.${now.getFullYear()}.`;
}

export function DanasScreen() {
  const navigation = useNavigation<Nav>();
  const query = useAdminDashboardQuery();
  const dashboard = query.data;

  const openJob = (jobId: number) => {
    navigation.navigate('Nalozi', { screen: 'NalogDodjela', params: { jobId } });
  };

  const kpiCells = dashboard
    ? [
        { k: 'Novih danas', v: String(dashboard.kpi.novi_danas) },
        { k: 'Aktivni nalozi', v: String(dashboard.kpi.aktivni_nalozi) },
        { k: 'Rokovi danas', v: String(dashboard.kpi.rokovi_danas) },
        { k: 'Aktivne pretplate', v: String(dashboard.kpi.aktivne_pretplate) },
      ]
    : [];

  const scheduleRows: Array<{ window: string; job: AdminJobListItem }> = dashboard
    ? dashboard.schedule_today.flatMap((group) => group.jobs.map((job) => ({ window: group.window, job })))
    : [];

  return (
    <View style={styles.container}>
      <View style={styles.inkHeader}>
        <View style={styles.inkTopRow}>
          <Text style={styles.wordmark}>HAUS</Text>
          <Text style={styles.dispatcherLabel}>Dispečer</Text>
        </View>
        <Text style={styles.headline}>Danas</Text>
        <Text style={styles.meta}>
          {todayLabel()} · {dashboard ? dashboard.kpi.aktivni_nalozi : 0} aktivnih naloga
        </Text>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.body}
        refreshControl={
          <RefreshControl refreshing={query.isFetching} onRefresh={() => query.refetch()} />
        }
      >
        {query.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
        {query.isError && !dashboard && <QueryErrorNotice onRetry={() => query.refetch()} />}

        {!!dashboard && (
          <>
            <View style={styles.kpiGrid}>
              {kpiCells.map((cell) => (
                <View key={cell.k} style={styles.kpiCell}>
                  <Text style={styles.kpiLabel}>{cell.k}</Text>
                  <Text style={styles.kpiValue}>{cell.v}</Text>
                </View>
              ))}
            </View>

            <View style={styles.deadlinesPanel}>
              <View style={styles.deadlinesHeader}>
                <Text style={styles.deadlinesHeaderLabel}>Rok pada danas</Text>
              </View>
              <View style={styles.deadlinesBody}>
                {dashboard.deadlines_today.length === 0 ? (
                  <Text style={styles.deadlinesEmpty}>Nema rokova koji padaju danas.</Text>
                ) : (
                  dashboard.deadlines_today.map((job) => (
                    <Pressable
                      key={job.id}
                      accessibilityRole="button"
                      onPress={() => openJob(job.id)}
                      style={styles.deadlineRow}
                    >
                      <Text style={styles.deadlineTitle}>
                        {job.number} · {job.address.street ?? job.client.name}
                      </Text>
                      <Text style={styles.deadlineMeta}>
                        {job.is_emergency ? 'Hitno, ' : ''}rok {formatDateTime(job.deadline_at)}
                        {job.technician ? `, ${job.technician.name}` : ', bez majstora'}
                      </Text>
                    </Pressable>
                  ))
                )}
              </View>
            </View>

            <Text style={styles.sectionHeader}>Raspored</Text>
            {scheduleRows.length === 0 ? (
              <Text style={styles.emptyText}>Danas nema zakazanih izlazaka.</Text>
            ) : (
              <View style={styles.schedule}>
                {scheduleRows.map(({ window, job }) => (
                  <Pressable
                    key={job.id}
                    accessibilityRole="button"
                    onPress={() => openJob(job.id)}
                    style={styles.scheduleRow}
                  >
                    <Text style={styles.scheduleWindow}>{window}</Text>
                    <View style={styles.scheduleContent}>
                      <Text style={styles.scheduleJob}>{job.title}</Text>
                      <Text style={styles.scheduleMeta}>
                        {job.technician?.name ?? 'Bez majstora'} · {job.client.name}
                      </Text>
                    </View>
                  </Pressable>
                ))}
              </View>
            )}
          </>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  inkHeader: {
    backgroundColor: colors.ink,
    paddingHorizontal: 22,
    paddingTop: 20,
    paddingBottom: 22,
  },
  inkTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 18,
  },
  wordmark: {
    fontFamily: fontFamily.bold,
    fontSize: 17,
    letterSpacing: 0.5,
    color: colors.ivory,
  },
  dispatcherLabel: {
    ...typeScale.eyebrow,
    color: colors.grey,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 28,
    letterSpacing: -0.3,
    color: colors.ivory,
    marginBottom: 4,
  },
  meta: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.sand,
    fontVariant: ['tabular-nums'],
  },
  scroll: {
    flex: 1,
  },
  body: {
    padding: 22,
    paddingBottom: 40,
  },
  kpiGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
    gap: 1,
    marginBottom: 22,
  },
  kpiCell: {
    width: '49.85%',
    backgroundColor: colors.white,
    paddingVertical: 15,
    paddingHorizontal: 15,
  },
  kpiLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 7,
  },
  kpiValue: {
    fontFamily: fontFamily.bold,
    fontSize: 24,
    lineHeight: 28,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  deadlinesPanel: {
    borderWidth: 1,
    borderColor: colors.ember,
    marginBottom: 22,
  },
  deadlinesHeader: {
    backgroundColor: colors.ember,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  deadlinesHeaderLabel: {
    ...typeScale.eyebrow,
    color: colors.ivory,
  },
  deadlinesBody: {
    padding: 16,
    gap: 12,
  },
  deadlinesEmpty: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
  },
  deadlineRow: {
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    paddingBottom: 10,
  },
  deadlineTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    marginBottom: 2,
  },
  deadlineMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  sectionHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 12,
  },
  emptyText: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
  },
  schedule: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  scheduleRow: {
    backgroundColor: colors.white,
    paddingVertical: 14,
    paddingHorizontal: 16,
    flexDirection: 'row',
    gap: 14,
  },
  scheduleWindow: {
    width: 82,
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  scheduleContent: {
    flex: 1,
  },
  scheduleJob: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    lineHeight: 20,
    color: colors.ink,
    marginBottom: 3,
  },
  scheduleMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
});

export default DanasScreen;
