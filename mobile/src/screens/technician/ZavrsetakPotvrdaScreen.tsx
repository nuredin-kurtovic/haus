/**
 * Serviser: potvrdni ekran poslije završetka naloga (task A.3, "uspjeh:
 * ink potvrdni ekran sa garancijom i sažetkom računa"). Ink zaglavlje, isti
 * obrazac kao PretplataAktivnaScreen (design/README.md: "ink zaglavlja
 * samo na Danas + potvrdnim ekranima"), bez back dugmeta: nalog je zatvoren,
 * povratak ide na listu, ne na wizard.
 *
 * Parametri stižu iz CompleteJobResponse (POST .../complete), curl-om
 * potvrđeno: `warranty_until` je datum (bez vremena), `invoice` sažeta
 * faktura (labor_total/material_total/total, bez stavki).
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { formatDate } from '../../utils/format';
import { colors, fontFamily, spacing } from '../../theme/tokens';
import type { TechnicianNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<TechnicianNaloziStackParamList, 'ZavrsetakPotvrda'>;
type Route = RouteProp<TechnicianNaloziStackParamList, 'ZavrsetakPotvrda'>;

export function ZavrsetakPotvrdaScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const { number, warrantyUntil, invoice } = route.params;

  const rows: Array<{ k: string; v: string }> = [
    { k: 'Nalog', v: number },
    { k: 'Garancija do', v: formatDate(warrantyUntil) ?? 'Nema' },
  ];
  if (invoice) {
    rows.push({ k: 'Broj računa', v: invoice.number });
    rows.push({ k: 'Rad', v: `${invoice.labor_total} KM` });
    rows.push({ k: 'Materijal', v: `${invoice.material_total} KM` });
    rows.push({ k: 'Ukupno', v: `${invoice.total} KM` });
  }

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.plane}>
        <View style={styles.chip}>
          <Text style={styles.chipLabel}>Nalog zatvoren</Text>
        </View>
        <Text style={styles.headline}>Izvještaj je poslan{'\n'}klijentu.</Text>
      </View>

      <View style={styles.body}>
        <Text style={styles.bodyText}>
          Nalaz, stavke i fotografije su sačuvani. Klijent dobija izvještaj na mejl u roku od 24
          sata.
        </Text>
        <View style={styles.rows}>
          {rows.map((row) => (
            <View key={row.k} style={styles.row}>
              <Text style={styles.rowKey}>{row.k}</Text>
              <Text style={styles.rowValue}>{row.v}</Text>
            </View>
          ))}
        </View>
        <Button
          label="Nazad na moje naloge"
          onPress={() => navigation.popToTop()}
          style={styles.doneButton}
        />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  plane: {
    backgroundColor: colors.ink,
    paddingHorizontal: 26,
    paddingTop: 44,
    paddingBottom: 40,
  },
  chip: {
    alignSelf: 'flex-start',
    backgroundColor: colors.ivory,
    paddingHorizontal: 12,
    paddingVertical: 7,
    marginBottom: 20,
  },
  chipLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 12,
    letterSpacing: 1,
    textTransform: 'uppercase',
    color: colors.ink,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 34,
    lineHeight: 38,
    letterSpacing: -0.5,
    color: colors.ivory,
  },
  body: {
    flex: 1,
    padding: 26,
    gap: spacing.lg,
  },
  bodyText: {
    fontFamily: fontFamily.regular,
    fontSize: 17,
    lineHeight: 25,
    color: colors.ink,
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
    paddingVertical: 13,
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
  doneButton: {
    marginTop: 'auto',
  },
});

export default ZavrsetakPotvrdaScreen;
