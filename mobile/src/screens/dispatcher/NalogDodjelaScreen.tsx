/**
 * Dispečer: 18 Nalog i dodjela (design/README.md, prototip
 * data-screen-label="18 Dispecer nalog"). Definiciona lista, dodjela
 * majstora, termin (datum + početak, kraj +2h automatski), state akcije
 * po dozvoljenim tranzicijama, i ŽIVI PREVIEW obavještenja klijentu. Ovo
 * je jedini ekran gdje dispečer vidi tačno šta klijent dobija (task
 * napomena: "preview je poenta").
 *
 * Tranzicije (čitanjem JobTransitionService.php potvrđeno, NE prototipov
 * slobodan izbor od 5 stanja): PATCH /admin/jobs/{id} dozvoljava SAMO
 * novo->zakazano (traži majstora i prozor) i zakazano->u_toku (dispečer
 * može umjesto majstora). u_toku->zavrseno ide 422 ("Nalog se zatvara
 * nalazom"): zatvaranje nije na ovom ekranu. Zato je CTA kontekstualan
 * (jedan ember CTA po ekranu, task pravilo), ne statički niz od 5 dugmića:
 * - novo: "Potvrdite zakazivanje" (traži majstora + valjan termin).
 * - zakazano, forma nepromijenjena: "Pokrenite izlazak" (status u_toku).
 * - zakazano, forma promijenjena (novi majstor/termin): "Ažurirajte termin"
 *   (ponovo šalje termin_potvrdjen, docs/API.md dozvoljava).
 * - u_toku / zavrseno: nema ember CTA, samo napomena.
 *
 * Termin: server NE računa kraj sam (čitanjem JobTransitionService::
 * provjeriProzor potvrđeno, traži i start i end kao par); mobilna app
 * računa kraj = početak + 2h i šalje oba (task zahtjev).
 *
 * NAPOMENA na vrijeme: server čuva/vraća termine u UTC (config/app.php),
 * a "zidni sat" koji dispečer upiše (npr. "10:00") se čuva LITERALNO, bez
 * konverzije (curl-om potvrđeno: poslano "2026-08-08T10:00:00" bez offseta
 * vraća se "...+00:00" sa istim brojevima). Zato se ULAZ ovdje pravi kao
 * NAIVAN ISO string (bez "Z"/offseta) direktno od upisanih cifara, NIKAD
 * kroz Date.toISOString() (koji bi primijenio timezone uređaja i
 * pomjerio sat). Računanje kraja (+2h) i prikaz idu kroz iste lokalne
 * Date getter/setter parove, nikad kroz UTC konverziju, pa je ispravno bez
 * obzira na timezone uređaja. Prefil postojećeg termina (kad se nalog već
 * zakazan otvara na ovom ekranu) i dalje koristi formatDate/formatTime
 * (isti obrazac kao ostatak aplikacije, dokumentovana napomena u
 * utils/format.ts); ako dispečer ništa ne mijenja, taj prefil se ne šalje
 * ponovo (samo status/druge izmjene).
 */

import React, { useEffect, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import StateChip from '../../components/StateChip';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import {
  useAdminJobDetailQuery,
  useAdminTechniciansQuery,
  useNotificationPreviewQuery,
  useUpdateAdminJobMutation,
} from '../../api/queries';
import { ApiError } from '../../api/client';
import { formatDate, formatDateTime, formatTime } from '../../utils/format';
import { chipStateForJob } from '../../utils/jobs';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { DispatcherNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<DispatcherNaloziStackParamList, 'NalogDodjela'>;
type Route = RouteProp<DispatcherNaloziStackParamList, 'NalogDodjela'>;

const DATE_RE = /^(\d{2})\.(\d{2})\.(\d{4})\.?$/;
const TIME_RE = /^([01]\d|2[0-3]):([0-5]\d)$/;

function pad2(value: number): string {
  return String(value).padStart(2, '0');
}

/** Naivan lokalni Date (dan/mjesec/godina/sat/minuta upisani direktno, nema UTC konverzije). */
function parseLocalDateTime(dateText: string, timeText: string): Date | null {
  const dateMatch = DATE_RE.exec(dateText.trim());
  const timeMatch = TIME_RE.exec(timeText.trim());
  if (!dateMatch || !timeMatch) {
    return null;
  }
  const day = Number(dateMatch[1]);
  const month = Number(dateMatch[2]);
  const year = Number(dateMatch[3]);
  const hour = Number(timeMatch[1]);
  const minute = Number(timeMatch[2]);
  const candidate = new Date(year, month - 1, day, hour, minute, 0, 0);
  if (
    candidate.getFullYear() !== year ||
    candidate.getMonth() !== month - 1 ||
    candidate.getDate() !== day
  ) {
    return null;
  }
  return candidate;
}

/** "yyyy-mm-ddThh:mm:00", bez offseta: Carbon::parse ga uzima kao literalne cifre (vidi napomenu iznad). */
function toNaiveIso(date: Date): string {
  return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}T${pad2(
    date.getHours(),
  )}:${pad2(date.getMinutes())}:00`;
}

function dateChipLabel(date: Date): string {
  return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}.${date.getFullYear()}.`;
}

export function NalogDodjelaScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const jobId = route.params.jobId;

  const jobQuery = useAdminJobDetailQuery(jobId);
  const techniciansQuery = useAdminTechniciansQuery();
  const updateJob = useUpdateAdminJobMutation();

  const job = jobQuery.data;

  const [technicianId, setTechnicianId] = useState<number | null>(null);
  const [dateText, setDateText] = useState('');
  const [timeText, setTimeText] = useState('');
  const [touched, setTouched] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  // Prefil kad nalog stigne (jednom po jobId, ne na svaki refetch da ne
  // pregazi ono što je dispečer već počeo da mijenja).
  useEffect(() => {
    if (!job) {
      return;
    }
    setTechnicianId(job.technician?.id ?? null);
    setDateText(job.scheduled_window_start ? formatDate(job.scheduled_window_start)?.replace(/\.$/, '.') ?? '' : '');
    setTimeText(job.scheduled_window_start ? formatTime(job.scheduled_window_start) ?? '' : '');
    setTouched(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [job?.id]);

  const startDate = useMemo(() => parseLocalDateTime(dateText, timeText), [dateText, timeText]);
  const endDate = useMemo(() => {
    if (!startDate) {
      return null;
    }
    const end = new Date(startDate);
    end.setHours(end.getHours() + 2);
    return end;
  }, [startDate]);

  const startIso = startDate ? toNaiveIso(startDate) : null;
  const endIso = endDate ? toNaiveIso(endDate) : null;
  const startLabel = startDate ? `${pad2(startDate.getHours())}:${pad2(startDate.getMinutes())}` : null;
  const endLabel = endDate ? `${pad2(endDate.getHours())}:${pad2(endDate.getMinutes())}` : null;

  const markTouched = () => setTouched(true);

  const pickToday = () => {
    setDateText(dateChipLabel(new Date()));
    markTouched();
  };
  const pickTomorrow = () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    setDateText(dateChipLabel(tomorrow));
    markTouched();
  };

  // Ciljna tranzicija koju CTA sprovodi, po pravilima JobTransitionService.
  const ctaTarget: 'zakazano' | 'u_toku' | null = !job
    ? null
    : job.status === 'novo'
      ? 'zakazano'
      : job.status === 'zakazano'
        ? touched
          ? 'zakazano'
          : 'u_toku'
        : null;

  const canConfirmSchedule = technicianId != null && !!startIso && !!endIso;

  const previewParams = useMemo(() => {
    if (!ctaTarget) {
      return null;
    }
    if (ctaTarget === 'zakazano') {
      if (!canConfirmSchedule) {
        return null;
      }
      return {
        status: 'zakazano' as const,
        technician_id: technicianId ?? undefined,
        scheduled_window_start: startIso ?? undefined,
        scheduled_window_end: endIso ?? undefined,
      };
    }
    return { status: 'u_toku' as const };
  }, [ctaTarget, canConfirmSchedule, technicianId, startIso, endIso]);

  const previewQuery = useNotificationPreviewQuery(jobId, previewParams);

  const handleConfirm = async () => {
    if (!ctaTarget) {
      return;
    }
    setErrors({});
    try {
      if (ctaTarget === 'zakazano') {
        if (!canConfirmSchedule) {
          return;
        }
        await updateJob.mutateAsync({
          jobId,
          payload: {
            technician_id: technicianId!,
            scheduled_window_start: startIso!,
            scheduled_window_end: endIso!,
            status: 'zakazano',
          },
        });
      } else {
        await updateJob.mutateAsync({ jobId, payload: { status: 'u_toku' } });
      }
      setTouched(false);
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? { _: [error.message] });
      }
    }
  };

  const handleAssignOnly = async () => {
    if (technicianId == null) {
      return;
    }
    setErrors({});
    try {
      await updateJob.mutateAsync({ jobId, payload: { technician_id: technicianId } });
      setTouched(false);
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.errors ?? { _: [error.message] });
      }
    }
  };

  const rows: Array<{ k: string; v: string }> = job
    ? [
        { k: 'Klijent', v: job.client.name },
        { k: 'Adresa', v: [job.address.street, job.address.city].filter(Boolean).join(', ') || 'Nepoznata' },
        { k: 'Kategorija', v: job.category ?? 'Nalog' },
        { k: 'Opis', v: job.description },
        { k: 'Rok', v: formatDateTime(job.deadline_at) ?? job.deadline_at },
      ]
    : [];

  const errorEntries = Object.entries(errors);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Pressable accessibilityRole="button" onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backGlyph}>‹</Text>
        </Pressable>
        <Text style={styles.headerTitle}>Nalog {job?.number ?? ''}</Text>
      </View>

      {jobQuery.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
      {jobQuery.isError && !job && (
        <QueryErrorNotice
          message="Nije moguće učitati nalog. Provjerite internet vezu."
          onRetry={() => jobQuery.refetch()}
        />
      )}

      {!!job && (
        <ScrollView contentContainerStyle={styles.body}>
          <View style={styles.topRow}>
            <StateChip state={chipStateForJob(job)} />
            {job.is_emergency && <Text style={styles.hitnoText}>HITNO</Text>}
          </View>

          <View style={styles.rows}>
            {rows.map((row) => (
              <View key={row.k} style={styles.row}>
                <Text style={styles.rowKey}>{row.k}</Text>
                <Text style={styles.rowValue}>{row.v}</Text>
              </View>
            ))}
          </View>

          {job.status !== 'zavrseno' && (
            <>
              <Text style={styles.sectionLabel}>Dodijelite majstora</Text>
              {techniciansQuery.isLoading && <QueryLoadingNotice label="Učitavanje majstora..." />}
              <View style={styles.technicianList}>
                {(techniciansQuery.data ?? []).map((technician) => {
                  const isSelected = technicianId === technician.id;
                  return (
                    <Pressable
                      key={technician.id}
                      accessibilityRole="button"
                      accessibilityState={{ selected: isSelected }}
                      onPress={() => {
                        setTechnicianId(technician.id);
                        markTouched();
                      }}
                      style={[
                        styles.technicianRow,
                        isSelected ? styles.technicianRowSelected : styles.technicianRowDefault,
                      ]}
                    >
                      <Text style={[styles.technicianName, isSelected && styles.technicianNameSelected]}>
                        {technician.name}
                      </Text>
                      <Text style={styles.technicianMeta}>
                        {technician.trade} · {technician.open_jobs_count} otvorenih
                      </Text>
                    </Pressable>
                  );
                })}
              </View>

              <Text style={styles.sectionLabel}>Termin</Text>
              <View style={styles.dateChipRow}>
                <Pressable accessibilityRole="button" onPress={pickToday} style={styles.dateChip}>
                  <Text style={styles.dateChipLabel}>Danas</Text>
                </Pressable>
                <Pressable accessibilityRole="button" onPress={pickTomorrow} style={styles.dateChip}>
                  <Text style={styles.dateChipLabel}>Sutra</Text>
                </Pressable>
              </View>
              <View style={styles.fieldRow}>
                <View style={styles.fieldHalf}>
                  <Text style={styles.fieldLabel}>Datum (dd.mm.gggg.)</Text>
                  <TextInput
                    value={dateText}
                    onChangeText={(text) => {
                      setDateText(text);
                      markTouched();
                    }}
                    placeholder="08.08.2026."
                    placeholderTextColor={colors.grey}
                    style={styles.fieldInput}
                  />
                </View>
                <View style={styles.fieldHalf}>
                  <Text style={styles.fieldLabel}>Početak (HH:mm)</Text>
                  <TextInput
                    value={timeText}
                    onChangeText={(text) => {
                      setTimeText(text);
                      markTouched();
                    }}
                    placeholder="10:00"
                    placeholderTextColor={colors.grey}
                    keyboardType="numbers-and-punctuation"
                    style={styles.fieldInput}
                  />
                </View>
              </View>
              <View style={styles.endRow}>
                <Text style={styles.endLabel}>Kraj (računa se automatski, +2h)</Text>
                <Text style={styles.endValue}>
                  {startLabel && endLabel ? `${startLabel}–${endLabel}` : 'Upišite datum i početak'}
                </Text>
              </View>

              {errorEntries.length > 0 && (
                <View style={styles.errorBox}>
                  {errorEntries.map(([field, messages]) => (
                    <Text key={field} style={styles.errorText}>
                      {messages[0]}
                    </Text>
                  ))}
                </View>
              )}

              {job.status === 'novo' && technicianId != null && !canConfirmSchedule && (
                <Button
                  label="Sačuvajte majstora bez termina"
                  variant="ghost"
                  onPress={handleAssignOnly}
                  style={styles.secondaryButton}
                />
              )}

              <View style={styles.previewPanel}>
                <Text style={styles.previewLabel}>Obavještenje klijentu</Text>
                {!previewParams && (
                  <Text style={styles.previewBody}>
                    Izaberite majstora i termin da vidite tačan tekst obavještenja.
                  </Text>
                )}
                {previewQuery.isFetching && !previewQuery.data && (
                  <Text style={styles.previewBody}>Učitavanje pregleda...</Text>
                )}
                {previewQuery.isError && (
                  <Text style={styles.previewBody}>Nije moguće učitati pregled obavještenja.</Text>
                )}
                {!!previewQuery.data && (
                  <Text style={styles.previewBody}>{previewQuery.data.data.body}</Text>
                )}
              </View>

              {ctaTarget && (
                <Button
                  label={
                    updateJob.isPending
                      ? 'Šaljemo...'
                      : ctaTarget === 'zakazano'
                        ? touched && job.status === 'zakazano'
                          ? 'Ažurirajte termin'
                          : 'Potvrdite zakazivanje'
                        : 'Pokrenite izlazak'
                  }
                  disabled={(ctaTarget === 'zakazano' && !canConfirmSchedule) || updateJob.isPending}
                  onPress={handleConfirm}
                  style={styles.ctaButton}
                />
              )}
            </>
          )}

          {job.status === 'u_toku' && (
            <Text style={styles.infoNote}>
              Nalog je u toku. Zatvara se kroz završetak naloga, u mobilnoj aplikaciji majstora.
            </Text>
          )}
          {job.status === 'zavrseno' && (
            <Text style={styles.infoNote}>Nalog je završen i više se ne mijenja.</Text>
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
    marginBottom: 16,
  },
  hitnoText: {
    fontFamily: fontFamily.bold,
    fontSize: 12,
    letterSpacing: 0.7,
    color: colors.ink,
  },
  rows: {
    borderTopWidth: 1,
    borderTopColor: colors.ink,
    marginBottom: 24,
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
    flexBasis: '65%',
  },
  sectionLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 10,
    marginTop: 6,
  },
  technicianList: {
    gap: 8,
    marginBottom: 20,
  },
  technicianRow: {
    borderWidth: 1,
    minHeight: 52,
    paddingHorizontal: 16,
    paddingVertical: 10,
    justifyContent: 'center',
  },
  technicianRowDefault: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  technicianRowSelected: {
    borderColor: colors.ink,
    backgroundColor: colors.ivory,
  },
  technicianName: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    color: colors.ink,
  },
  technicianNameSelected: {
    fontFamily: fontFamily.semiBold,
  },
  technicianMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  dateChipRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  dateChip: {
    borderWidth: 1,
    borderColor: colors.sand,
    paddingHorizontal: 14,
    minHeight: 40,
    justifyContent: 'center',
  },
  dateChipLabel: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.ink,
  },
  fieldRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  fieldHalf: {
    flex: 1,
    gap: 6,
  },
  fieldLabel: {
    ...typeScale.eyebrow,
    fontSize: 11,
    color: colors.bark,
  },
  fieldInput: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 12,
    minHeight: 48,
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  endRow: {
    borderWidth: 1,
    borderColor: colors.sand,
    padding: 14,
    marginBottom: 20,
  },
  endLabel: {
    ...typeScale.eyebrow,
    fontSize: 11,
    color: colors.bark,
    marginBottom: 6,
  },
  endValue: {
    fontFamily: fontFamily.semiBold,
    fontSize: 16,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  errorBox: {
    borderWidth: 1,
    borderColor: colors.error,
    padding: 12,
    marginBottom: 16,
    gap: 4,
  },
  errorText: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    lineHeight: 19,
    color: colors.error,
  },
  secondaryButton: {
    marginBottom: 16,
  },
  previewPanel: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 16,
    marginBottom: 22,
  },
  previewLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 8,
  },
  previewBody: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.ink,
  },
  ctaButton: {
    marginBottom: 8,
  },
  infoNote: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
});

export default NalogDodjelaScreen;
