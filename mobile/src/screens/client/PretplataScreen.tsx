/**
 * 13 Moja pretplata (design/README.md "Screens: mobile", prototip
 * data-screen-label="13 Moja pretplata"). Ink zaglavlje (task pravilo:
 * "ink zaglavlja samo na potvrdi/sumi, 10 i 13"). Registrovan kao
 * "PretplataGlavna" u PretplataStack.
 *
 * GET /client/subscription (curl-om potvrđeno, drugi krug verifikacije,
 * Mini/Plus i Pro korisnik): `package` je puni Package (cijene, rokovi,
 * popusti, garancija), `properties[]` ima `city` kao RAVAN STRING (ne
 * objekat kao na registraciji) i remaining_visits/remaining_inspections
 * PO ADRESI: za Pro to je task-tražena "lista stanova sa preostalim
 * izlascima po stanu". Za Mini/Plus lista ima jedan element, prikazana
 * je ista lista (bez posebnog grananja) jer je i to "properties".
 *
 * Cijena u zaglavlju je `price_paid` (stvarno naplaćeno, uklj. Pro
 * volume popust), NE `package.price_year` (to je cijena po jedinici, za
 * Pro po stanu): price_paid je null samo prije prve uplate, pada back na
 * price_year u tom slučaju.
 *
 * Ulaz u Cjenovnik (ekran 14): task ostavlja izbor mjesta na ovom timu.
 * Odluka: dugme ovdje ("Pogledajte cjenovnik"), jer je logička veza s
 * pretplatom (cjenovnik prikazuje TAČNO popust iz ove pretplate) jača od
 * veze s Profilom. Dokumentovano u finalnom izvještaju.
 */

import React, { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useCancelSubscriptionMutation, useClientSubscriptionQuery } from '../../api/queries';
import { formatDate } from '../../utils/format';
import { colors, fontFamily, numeric, spacing, typeScale } from '../../theme/tokens';
import type { InvoiceStatus, InvoiceType, SubscriptionPayment } from '../../api/types';
import type { PretplataStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<PretplataStackParamList, 'PretplataGlavna'>;

const INVOICE_TYPE_LABEL: Record<InvoiceType, string> = {
  pretplata: 'Godišnja pretplata',
  rad: 'Rad i materijal',
};

const INVOICE_STATUS_LABEL: Record<InvoiceStatus, string> = {
  nenaplaceno: 'Nenaplaćeno',
  placeno: 'Plaćeno',
  refundirano: 'Refundirano',
  djelimicno_refundirano: 'Djelimično refundirano',
  bez_naplate: 'Bez naplate',
};

function paymentLine(payment: SubscriptionPayment): string {
  return `${INVOICE_TYPE_LABEL[payment.type]} · ${payment.number}`;
}

export function PretplataScreen() {
  const navigation = useNavigation<Nav>();
  const query = useClientSubscriptionQuery();
  const cancelMutation = useCancelSubscriptionMutation();
  const [cancelMessage, setCancelMessage] = useState('');

  const subscription = query.data;

  const confirmCancel = () => {
    Alert.alert(
      'Otkažite obnovu',
      'Automatska obnova se isključuje. Pretplata i dalje vrijedi do isteka.',
      [
        { text: 'Odustanite', style: 'cancel' },
        {
          text: 'Otkažite obnovu',
          style: 'destructive',
          onPress: async () => {
            try {
              const response = await cancelMutation.mutateAsync();
              setCancelMessage(response.message);
            } catch {
              setCancelMessage('Nije moguće otkazati obnovu. Pokušajte ponovo.');
            }
          },
        },
      ],
    );
  };

  if (query.isLoading) {
    return (
      <SafeAreaView style={styles.container} edges={['top']}>
        <QueryLoadingNotice label="Učitavanje pretplate..." />
      </SafeAreaView>
    );
  }

  if (query.isError || !subscription) {
    return (
      <SafeAreaView style={styles.container} edges={['top']}>
        <QueryErrorNotice
          message="Nije moguće učitati pretplatu. Provjerite internet vezu."
          onRetry={() => query.refetch()}
        />
      </SafeAreaView>
    );
  }

  const pkg = subscription.package;
  const price = subscription.price_paid ?? pkg.price_year;
  const priceUnit = pkg.is_per_apartment ? 'po stanu godišnje' : 'godišnje';

  const rows: Array<{ k: string; v: string }> = [
    { k: 'Popust na rad', v: `${pkg.labor_discount_pct}%` },
    { k: 'Popust na materijal', v: `${pkg.material_discount_pct}%` },
    { k: 'Rok izlaska', v: `${pkg.deadline_hours} h` },
    { k: 'Hitno', v: `${pkg.emergency_deadline_hours} h, bez doplate` },
    { k: 'Garancija na rad', v: `${pkg.warranty_months} mjeseci` },
    { k: 'Obnova', v: subscription.auto_renew ? 'Automatska' : 'Isključena' },
  ];

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent}>
      <View style={styles.headerPlane}>
        <Text style={styles.headerEyebrow}>Vaš paket</Text>
        <View style={styles.headerTopRow}>
          <Text style={styles.headerName}>{pkg.name}</Text>
          <Text style={styles.headerPrice}>
            {price} KM{'\n'}
            <Text style={styles.headerPriceUnit}>{priceUnit}</Text>
          </Text>
        </View>
        <Text style={styles.headerEndsAt}>
          {subscription.ends_at ? `Aktivna do ${formatDate(subscription.ends_at)}` : ''}
        </Text>
      </View>

      <View style={styles.body}>
        <View style={styles.rows}>
          {rows.map((row) => (
            <View key={row.k} style={styles.row}>
              <Text style={styles.rowKey}>{row.k}</Text>
              <Text style={styles.rowValue}>{row.v}</Text>
            </View>
          ))}
        </View>

        {subscription.properties.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionHeader}>
              {pkg.is_per_apartment ? 'Vaši stanovi' : 'Vaša adresa'}
            </Text>
            <View style={styles.propertyList}>
              {subscription.properties.map((property) => (
                <View key={property.id} style={styles.propertyRow}>
                  <Text style={styles.propertyAddress}>
                    {property.street}, {property.city}
                  </Text>
                  <Text style={styles.propertyMeta}>
                    {property.remaining_visits} izlazaka, {property.remaining_inspections} pregleda
                    preostalo
                  </Text>
                </View>
              ))}
            </View>
          </View>
        )}

        <View style={styles.inspectionWrap}>
          <View style={styles.inspectionInner}>
            <Text style={styles.inspectionTitle}>Godišnji pregled</Text>
            <Text style={styles.inspectionBody}>
              Uključen u vaš paket. Preostalo pregleda:{' '}
              {subscription.properties.reduce((sum, p) => sum + p.remaining_inspections, 0)}.
            </Text>
          </View>
        </View>

        <Button
          label="Pogledajte cjenovnik"
          variant="ghost"
          onPress={() => navigation.navigate('Cjenovnik')}
        />

        <View style={styles.section}>
          <Text style={styles.sectionHeader}>Plaćanja</Text>
          {subscription.payments.length === 0 ? (
            <Text style={styles.emptyText}>Još nema historije plaćanja.</Text>
          ) : (
            <View style={styles.paymentList}>
              {subscription.payments.map((payment) => (
                <View key={payment.number} style={styles.paymentRow}>
                  <View style={styles.paymentRowText}>
                    <Text style={styles.paymentDescription}>{paymentLine(payment)}</Text>
                    <Text style={styles.paymentMeta}>
                      {formatDate(payment.created_at)} · {INVOICE_STATUS_LABEL[payment.status]}
                    </Text>
                  </View>
                  <Text style={styles.paymentAmount}>{payment.total} KM</Text>
                </View>
              ))}
            </View>
          )}
        </View>

        {subscription.auto_renew && (
          <Pressable accessibilityRole="button" onPress={confirmCancel} style={styles.cancelButton}>
            <Text style={styles.cancelButtonLabel}>Otkažite obnovu</Text>
          </Pressable>
        )}
        {!!cancelMessage && <Text style={styles.cancelMessage}>{cancelMessage}</Text>}
      </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  scrollContent: {
    paddingBottom: 32,
  },
  headerPlane: {
    backgroundColor: colors.ink,
    paddingHorizontal: 22,
    paddingVertical: 28,
  },
  headerEyebrow: {
    ...typeScale.eyebrow,
    color: colors.grey,
    marginBottom: 8,
  },
  headerTopRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    justifyContent: 'space-between',
    gap: 16,
  },
  headerName: {
    fontFamily: fontFamily.bold,
    fontSize: 28,
    color: colors.ivory,
    letterSpacing: 0.3,
  },
  headerPrice: {
    fontFamily: fontFamily.bold,
    fontSize: 20,
    color: colors.ivory,
    fontVariant: ['tabular-nums'],
    textAlign: 'right',
  },
  headerPriceUnit: {
    fontFamily: fontFamily.regular,
    fontSize: 11,
    color: colors.grey,
  },
  headerEndsAt: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.sand,
    marginTop: 8,
    fontVariant: ['tabular-nums'],
  },
  body: {
    padding: 22,
    gap: 24,
  },
  rows: {
    borderTopWidth: 1,
    borderTopColor: colors.ink,
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
    fontSize: 15,
    color: colors.bark,
  },
  rowValue: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
    textAlign: 'right',
  },
  section: {
    gap: 12,
  },
  sectionHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  propertyList: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  propertyRow: {
    backgroundColor: colors.white,
    paddingVertical: 13,
    paddingHorizontal: 15,
    gap: 4,
  },
  propertyAddress: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    color: colors.ink,
  },
  propertyMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  inspectionWrap: {
    backgroundColor: colors.ember,
    padding: 2,
  },
  inspectionInner: {
    backgroundColor: colors.ivory,
    padding: 18,
  },
  inspectionTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    color: colors.ink,
    marginBottom: 8,
  },
  inspectionBody: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
    ...numeric,
  },
  emptyText: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
  paymentList: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  paymentRow: {
    backgroundColor: colors.white,
    paddingVertical: 14,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 14,
  },
  paymentRowText: {
    flex: 1,
  },
  paymentDescription: {
    fontFamily: fontFamily.medium,
    fontSize: 15,
    color: colors.ink,
    marginBottom: 3,
    ...numeric,
  },
  paymentMeta: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  paymentAmount: {
    fontFamily: fontFamily.bold,
    fontSize: 16,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  cancelButton: {
    borderWidth: 1,
    borderColor: colors.sand,
    minHeight: spacing.touchTargetMin + 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelButtonLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.bark,
  },
  cancelMessage: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
});

export default PretplataScreen;
