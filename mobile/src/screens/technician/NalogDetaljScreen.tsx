/**
 * Serviser: detalj naloga (task A.2). GET /technician/jobs/{id} (curl-om
 * potvrđeno): klijent, adresa, kategorija, opis, klijentova fotografija
 * (job.photos, type "prije": isti tip koji klijent prilaže pri prijavi
 * kvara, docs/API.md "photo se upisuje kao job_photos.type = prije"),
 * rok, napomena o naplati iz `entitlements.ide_na_naplatu`.
 *
 * Akcije po statusu (task): zakazano -> "Krenuo sam" (POST .../start, uz
 * potvrdu jer klijent odmah dobija obavještenje); u_toku -> "Završite
 * nalog" (ide na wizard ekran ZavrsetakNaloga). Završen nalog prikazuje
 * nalaz i fotografije prije/poslije umjesto akcije.
 */

import React from 'react';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useStartTechnicianJobMutation, useTechnicianJobDetailQuery } from '../../api/queries';
import { resolveMediaUrl, ApiError } from '../../api/client';
import { formatDateTime, formatWindowRange } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { JobPhoto, TechnicianJobDetail } from '../../api/types';
import type { TechnicianNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<TechnicianNaloziStackParamList, 'NalogDetalj'>;
type Route = RouteProp<TechnicianNaloziStackParamList, 'NalogDetalj'>;

function firstPhotoOfType(photos: JobPhoto[], type: JobPhoto['type']): JobPhoto | undefined {
  return photos.find((photo) => photo.type === type);
}

function terminValue(job: TechnicianJobDetail): string {
  const range = formatWindowRange(job.scheduled_window_start, job.scheduled_window_end);
  return range ?? job.preferred_window ?? 'Nije zakazan';
}

export function NalogDetaljScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const jobId = route.params.jobId;
  const query = useTechnicianJobDetailQuery(jobId);
  const startJob = useStartTechnicianJobMutation();
  const job = query.data;

  const handleStart = () => {
    Alert.alert(
      'Krenuli ste na adresu?',
      'Klijent odmah dobija obavještenje da ste krenuli.',
      [
        { text: 'Otkažite', style: 'cancel' },
        {
          text: 'Da, krenuo sam',
          onPress: async () => {
            try {
              await startJob.mutateAsync(jobId);
            } catch (error) {
              const message =
                error instanceof ApiError ? error.message : 'Nije moguće pokrenuti izlazak.';
              Alert.alert('Greška', message);
            }
          },
        },
      ],
    );
  };

  const rows = job
    ? [
        { k: 'Klijent', v: job.client.name },
        {
          k: 'Adresa',
          v: [job.address.street, job.address.city].filter(Boolean).join(', ') || 'Nepoznata',
        },
        { k: 'Kategorija', v: job.category ?? 'Nalog' },
        { k: 'Termin', v: terminValue(job) },
        {
          k: job.status === 'zavrseno' ? 'Završeno' : 'Rok izlaska',
          v: formatDateTime(job.status === 'zavrseno' ? job.deadline_at : job.deadline_at) ?? job.deadline_at,
        },
      ]
    : [];

  const clientPhoto = job ? firstPhotoOfType(job.photos, 'prije') : undefined;

  return (
    <View style={styles.container}>
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
          <View style={styles.topRow}>
            <StateChip state={chipStateForJob(job)} />
            {job.is_emergency && <Text style={styles.hitnoText}>HITNO</Text>}
          </View>
          <Text style={styles.description}>{job.description}</Text>

          <View style={styles.rows}>
            {rows.map((row) => (
              <View key={row.k} style={styles.row}>
                <Text style={styles.rowKey}>{row.k}</Text>
                <Text style={styles.rowValue}>{row.v}</Text>
              </View>
            ))}
          </View>

          {!!job.contact.note && (
            <View style={styles.noteBox}>
              <Text style={styles.noteLabel}>Napomena o pristupu</Text>
              <Text style={styles.noteBody}>{job.contact.note}</Text>
            </View>
          )}

          {!!clientPhoto && (
            <View style={styles.photoSection}>
              <Text style={styles.sectionLabel}>Fotografija klijenta</Text>
              <Image
                source={{ uri: resolveMediaUrl(clientPhoto.url) }}
                style={styles.clientPhoto}
                resizeMode="cover"
              />
            </View>
          )}

          <View style={styles.billingNote}>
            <Text style={styles.billingText}>
              {job.entitlements.ide_na_naplatu
                ? 'Klijent nema preostalih izlazaka. Rad se naplaćuje po cjenovniku.'
                : 'Pokriveno pretplatom.'}
            </Text>
          </View>

          {job.status === 'zavrseno' && (
            <>
              {!!job.findings && (
                <View style={styles.findingsBox}>
                  <Text style={styles.sectionLabel}>Nalaz</Text>
                  <Text style={styles.findingsBody}>{job.findings}</Text>
                </View>
              )}
              {job.photos.length > 0 && (
                <View style={styles.photoGrid}>
                  {job.photos.map((photo, index) => (
                    <View key={`${photo.type}-${index}`} style={styles.photoCell}>
                      <Image
                        source={{ uri: resolveMediaUrl(photo.url) }}
                        style={styles.photoImage}
                        resizeMode="cover"
                      />
                      <Text style={styles.photoTag}>
                        {photo.type === 'prije' ? 'PRIJE' : 'POSLIJE'}
                      </Text>
                    </View>
                  ))}
                </View>
              )}
            </>
          )}

          {job.status === 'zakazano' && (
            <Button
              label={startJob.isPending ? 'Šaljemo...' : 'Krenuo sam'}
              disabled={startJob.isPending}
              onPress={handleStart}
              style={styles.actionButton}
            />
          )}

          {job.status === 'u_toku' && (
            <Button
              label="Završite nalog"
              onPress={() => navigation.navigate('ZavrsetakNaloga', { jobId })}
              style={styles.actionButton}
            />
          )}
        </ScrollView>
      )}
    </View>
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
    paddingBottom: 40,
    gap: 4,
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  hitnoText: {
    fontFamily: fontFamily.bold,
    fontSize: 12,
    letterSpacing: 0.7,
    color: colors.ink,
  },
  description: {
    fontFamily: fontFamily.bold,
    fontSize: 22,
    lineHeight: 28,
    color: colors.ink,
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
    flexShrink: 1,
  },
  noteBox: {
    borderWidth: 1,
    borderColor: colors.sand,
    padding: 16,
    marginBottom: 20,
  },
  noteLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 8,
  },
  noteBody: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.ink,
  },
  photoSection: {
    marginBottom: 20,
    gap: 8,
  },
  sectionLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  clientPhoto: {
    width: '100%',
    height: 180,
    backgroundColor: colors.sand,
  },
  billingNote: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 16,
    marginBottom: 20,
  },
  billingText: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 21,
    color: colors.ink,
  },
  findingsBox: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 18,
    marginBottom: 20,
    gap: 10,
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
  },
  photoImage: {
    width: '100%',
    height: '100%',
  },
  photoTag: {
    position: 'absolute',
    bottom: 8,
    left: 8,
    ...typeScale.eyebrow,
    color: colors.ivory,
    backgroundColor: colors.ink,
    paddingHorizontal: 6,
    paddingVertical: 2,
  },
  actionButton: {
    marginTop: 12,
  },
});

export default NalogDetaljScreen;
