/**
 * 10 Prijava primljena (design/README.md "Screens: mobile", prototip
 * data-screen-label="10 Prijava primljena"). Ink zaglavlje (task pravilo:
 * "ink zaglavlja samo na potvrdi/sumi, 10 i 13").
 *
 * Prototip ima dugme "Vidite nalog kod dispečera" ali TASK eksplicitno
 * kaže da je to demo-only i da se ne ugrađuje: nema ga ovdje.
 *
 * Chip odluka: task traži "ember chip" na ink ploči. Brand pravilo (ember i
 * ink se nikad ne dodiruju) je inače binding, ali design/README.md već
 * pravi identičan izuzetak za state chip "u_toku" (ember fill + ivory
 * tekst, tabela "Job lifecycle"), koji se u praksi renderuje i na ink
 * pozadinama (npr. redovi liste naloga). Ovaj chip prati isti obrazac
 * (ember fill, ivory tekst, bez posebnog bordera) umjesto prototipove
 * ivory-na-ink verzije, po direktnoj task instrukciji. Dokumentovano i u
 * finalnom izvještaju kao odluka koju treba pregledati sa dizajnom.
 *
 * Svi podaci na ovom ekranu dolaze iz route parametara koje je popunio
 * PrijaviScreen iz lokalnog wizard stanja + POST /client/jobs odgovora
 * (server vraća samo {id, number, deadline_at}, ne cijeli nalog): rok se
 * računa server-side i ne mijenja se, kategorija/hitno/termin su tačno
 * ono što je klijent poslao.
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { formatDateTime } from '../../utils/format';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { ClientTabParamList, PrijaviStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<PrijaviStackParamList, 'PrijavaPrimljena'>;
type Route = RouteProp<PrijaviStackParamList, 'PrijavaPrimljena'>;

export function PrijavaPrimljenaScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const { number, category, isEmergency, preferredWindow, deadlineAt, remainingVisits, totalVisits } =
    route.params;

  const rows: Array<{ k: string; v: string }> = [
    { k: 'Kategorija', v: category },
    { k: 'Hitno', v: isEmergency ? 'Da' : 'Ne' },
    { k: 'Termin', v: preferredWindow },
    { k: 'Rok', v: formatDateTime(deadlineAt) ?? deadlineAt },
  ];

  return (
    <View style={styles.container}>
      <View style={styles.plane}>
        <View style={styles.chip}>
          <Text style={styles.chipLabel}>Primljeno</Text>
        </View>
        <Text style={styles.headline}>Nalog {number}{'\n'}je otvoren.</Text>
        <Text style={styles.planeBody}>
          Potvrdu termina dobijate obavještenjem u aplikaciji, u prozoru od dva sata.
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

        {totalVisits > 0 && (
          <Text style={styles.note}>
            Preostalo vam je {remainingVisits} od {totalVisits} uključenih izlazaka ove godine.
          </Text>
        )}

        <Button
          label="Nazad na početnu"
          onPress={() =>
            navigation
              .getParent<BottomTabNavigationProp<ClientTabParamList>>()
              ?.navigate('Pocetna', undefined)
          }
          style={styles.backButton}
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  plane: {
    backgroundColor: colors.ink,
    paddingHorizontal: 22,
    paddingTop: 40,
    paddingBottom: 34,
  },
  chip: {
    alignSelf: 'flex-start',
    backgroundColor: colors.ember,
    paddingHorizontal: 12,
    paddingVertical: 7,
    marginBottom: 18,
  },
  chipLabel: {
    ...typeScale.eyebrow,
    color: colors.ivory,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 34,
    lineHeight: 37,
    letterSpacing: -0.4,
    color: colors.ivory,
    fontVariant: ['tabular-nums'],
    marginBottom: 10,
  },
  planeBody: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.sand,
  },
  body: {
    flex: 1,
    padding: 22,
    gap: 20,
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
  note: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
  },
  backButton: {
    marginTop: 'auto',
  },
});

export default PrijavaPrimljenaScreen;
