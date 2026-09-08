/**
 * 12 Nalog i nalaz (design/README.md "Screens: mobile", prototip
 * data-screen-label="12 Nalog detalj"). Native-stack detalj iz taba
 * Nalozi (registrovan kao "NalogDetalj" u NaloziStack).
 *
 * GET /client/jobs/{id} (JobDetailResource, curl-om + čitanjem izvora
 * potvrđeno, vidi api/types.ts komentar na JobDetail): findings/photos/
 * invoice su null/[] dok nalog nije završen (nema još admin/tehničar ruta
 * na živom serveru da bi se popunio uzorak), ali ekran mora ispravno
 * renderovati i popunjeno stanje po dokumentovanom obliku resource klase.
 *
 * Slike: photo.url dolazi kao apsolutni Storage URL izgrađen iz APP_URL
 * (localhost:8000). Na iOS simulatoru radi direktno; na Android emulatoru
 * mora ići na 10.0.2.2, isto kao API_BASE_URL. resolveMediaUrl (api/
 * client.ts) radi tu istu Platform-zavisnu zamjenu.
 */

import React from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useClientJobDetailQuery } from '../../api/queries';
import { resolveMediaUrl } from '../../api/client';
import { formatDate, formatDateTime, formatTime } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { JobDetail, JobPhoto } from '../../api/types';
import type { NaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<NaloziStackParamList, 'NalogDetalj'>;
type Route = RouteProp<NaloziStackParamList, 'NalogDetalj'>;

function photoOfType(photos: JobPhoto[], type: JobPhoto['type']): JobPhoto | undefined {
  return photos.find((photo) => photo.type === type);
}

function terminValue(job: JobDetail): string {
  if (job.scheduled_window_start) {
    const start = formatDateTime(job.scheduled_window_start);
    const end = formatTime(job.scheduled_window_end);
    return end ? `${start} – ${end}` : start ?? 'Nije potvrđen';
  }
  return job.preferred_window ?? 'Nije izabran';
}

export function NalogDetaljScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const query = useClientJobDetailQuery(route.params.jobId);
  const job = query.data;

  const rows = job
    ? [
        { k: 'Prijavljeno', v: formatDateTime(job.created_at) ?? job.created_at },
        { k: 'Kategorija', v: job.category ?? 'Nalog' },
        { k: 'Termin', v: terminValue(job) },
        { k: 'Majstor', v: job.technician?.name ?? 'Nije dodijeljen' },
        job.status === 'zavrseno'
          ? { k: 'Garancija do', v: formatDate(job.warranty_until) ?? 'Nema' }
          : { k: 'Rok izlaska', v: formatDateTime(job.deadline_at) ?? job.deadline_at },
        ...(job.property
          ? [{ k: 'Adresa', v: `${job.property.street}, ${job.property.city}` }]
          : []),
        ...(job.is_emergency ? [{ k: 'Hitno', v: 'Da' }] : []),
      ]
    : [];

  const photoPrije = job ? photoOfType(job.photos, 'prije') : undefined;
  const photoPoslije = job ? photoOfType(job.photos, 'poslije') : undefined;

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <View style={styles.header}>
        <Pressable
          accessibilityRole="button"
          onPress={() => navigation.goBack()}
          style={styles.backButton}
        >
          <Text style={styles.backGlyph}>‹</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Nalog {job?.number ?? ''}</Text>
      </View>

      {query.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
      {query.isError && !job && (
        <QueryErrorNotice
          message="Nije moguće učitati nalog. Provjerite internet vezu."
          onRetry={() => query.refetch()}
        />
      )}

      {!!job && (
        <ScrollView contentContainerStyle={styles.body}>
          <StateChip state={chipStateForJob(job)} />
          <Text style={styles.title}>{job.title}</Text>

          <View style={styles.rows}>
            {rows.map((row) => (
              <View key={row.k} style={styles.row}>
                <Text style={styles.rowKey}>{row.k}</Text>
                <Text style={styles.rowValue}>{row.v}</Text>
              </View>
            ))}
          </View>

          {!!job.findings && (
            <View style={styles.findingsBox}>
              <Text style={styles.findingsLabel}>Nalaz majstora</Text>
              <Text style={styles.findingsBody}>{job.findings}</Text>
            </View>
          )}

          {(photoPrije || photoPoslije) && (
            <View style={styles.photoGrid}>
              <View style={styles.photoCell}>
                {photoPrije ? (
                  <Image
                    source={{ uri: resolveMediaUrl(photoPrije.url) }}
                    style={styles.photoImage}
                    resizeMode="cover"
                  />
                ) : (
                  <Text style={styles.photoPlaceholder}>PRIJE</Text>
                )}
              </View>
              <View style={styles.photoCell}>
                {photoPoslije ? (
                  <Image
                    source={{ uri: resolveMediaUrl(photoPoslije.url) }}
                    style={styles.photoImage}
                    resizeMode="cover"
                  />
                ) : (
                  <Text style={styles.photoPlaceholder}>POSLIJE</Text>
                )}
              </View>
            </View>
          )}

          {!!job.invoice && (
            <View style={styles.invoice}>
              <Text style={styles.invoiceSection}>Rad</Text>
              {job.invoice.labor_items.map((item, index) => (
                <View key={`labor-${index}`} style={styles.invoiceRow}>
                  <Text style={styles.invoiceItemName}>
                    {item.name} × {item.qty}
                  </Text>
                  <Text style={styles.invoiceItemValue}>{item.line_total} KM</Text>
                </View>
              ))}
              <View style={styles.invoiceTotalRow}>
                <Text style={styles.invoiceTotalKey}>Ukupno rad</Text>
                <Text style={styles.invoiceTotalValue}>{job.invoice.labor_total} KM</Text>
              </View>

              {job.invoice.materials.length > 0 && (
                <>
                  <Text style={styles.invoiceSection}>Materijal</Text>
                  {job.invoice.materials.map((item, index) => (
                    <View key={`material-${index}`} style={styles.invoiceRow}>
                      <Text style={styles.invoiceItemName}>
                        {item.name} × {item.qty}
                      </Text>
                      <Text style={styles.invoiceItemValue}>{item.line_total} KM</Text>
                    </View>
                  ))}
                  <View style={styles.invoiceTotalRow}>
                    <Text style={styles.invoiceTotalKey}>Ukupno materijal</Text>
                    <Text style={styles.invoiceTotalValue}>{job.invoice.material_total} KM</Text>
                  </View>
                </>
              )}

              <View style={styles.invoiceGrandTotalRow}>
                <Text style={styles.invoiceGrandTotalKey}>Ukupno</Text>
                <Text style={styles.invoiceGrandTotalValue}>{job.invoice.total} KM</Text>
              </View>
            </View>
          )}
        </ScrollView>
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
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 22,
    paddingTop: 20,
    paddingBottom: 18,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
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
  headerTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  body: {
    padding: 22,
    gap: 4,
  },
  title: {
    fontFamily: fontFamily.bold,
    fontSize: 26,
    lineHeight: 32,
    color: colors.ink,
    marginTop: 14,
    marginBottom: 18,
  },
  rows: {
    borderTopWidth: 1,
    borderTopColor: colors.ink,
    marginBottom: 20,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    paddingVertical: 12,
  },
  rowKey: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
  },
  rowValue: {
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
    textAlign: 'right',
  },
  findingsBox: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 18,
    marginBottom: 20,
  },
  findingsLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 10,
  },
  findingsBody: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 23,
    color: colors.ink,
  },
  photoGrid: {
    flexDirection: 'row',
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
    marginBottom: 20,
  },
  photoCell: {
    flex: 1,
    height: 150,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  photoImage: {
    width: '100%',
    height: '100%',
  },
  photoPlaceholder: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  invoice: {
    gap: 2,
  },
  invoiceSection: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginTop: 12,
    marginBottom: 4,
  },
  invoiceRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    paddingVertical: 10,
  },
  invoiceItemName: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.ink,
    flex: 1,
  },
  invoiceItemValue: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  invoiceTotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 14,
    paddingVertical: 10,
  },
  invoiceTotalKey: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.bark,
  },
  invoiceTotalValue: {
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  invoiceGrandTotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 14,
    borderTopWidth: 1,
    borderTopColor: colors.ink,
    paddingVertical: 14,
    marginTop: 6,
  },
  invoiceGrandTotalKey: {
    fontFamily: fontFamily.bold,
    fontSize: 17,
    color: colors.ink,
  },
  invoiceGrandTotalValue: {
    fontFamily: fontFamily.bold,
    fontSize: 17,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
});

export default NalogDetaljScreen;
