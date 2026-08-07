/**
 * 02 Kako radi (design/README.md, prototip data-screen-label="02 Kako
 * radi"). Tri koraka u jednom shellu (lokalni state indexa), copy je
 * prenesen doslovno iz prototipa (const KAKO u <script data-dc-script>).
 *
 * "Preskoči" po task pravilu uvijek vodi na Prijavu (u prototipu vodi na
 * izbor paketa; ovo je namjerna izmjena po uputama zadatka).
 */

import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

interface KakoStep {
  broj: string;
  naslov: string;
  tekst: string;
  stavke: string[];
}

const KAKO: KakoStep[] = [
  {
    broj: 'Korak 1',
    naslov: 'Prijavite kvar u aplikaciji',
    tekst: 'Bez zvanja i bez čekanja na liniji. Izaberete šta se pokvarilo, opišete u dvije rečenice i pošaljete fotografiju ako je imate.',
    stavke: [
      'Prijava traje minutu',
      'Radi 24 sata dnevno',
      'Fotografija pomaže majstoru da dođe sa pravim alatom',
    ],
  },
  {
    broj: 'Korak 2',
    naslov: 'Dobijete termin i cijenu',
    tekst: 'Termin je u prozoru od dva sata, ne "poslije podne". Cijenu rada vidite prije nego što majstor uzme alat i vi je potvrđujete.',
    stavke: [
      'Cjenovnik je javan i isti za sve',
      'Rok izlaska ide iz vašeg paketa',
      'Ako rok padne, sljedeća intervencija je besplatna',
    ],
  },
  {
    broj: 'Korak 3',
    naslov: 'Ostaje vam trag',
    tekst: 'Poslije svake intervencije dobijate nalaz, fotografije prije i poslije, i datum do kojeg traje garancija. Sve to stoji u vašem kartonu.',
    stavke: [
      'Izvještaj sa slikama u roku od 24 sata',
      'Garancija na rad 6 do 12 mjeseci',
      'Karton pamti šta je rađeno i kad',
    ],
  },
];

export function KakoRadiScreen() {
  const navigation = useNavigation<Nav>();
  const [step, setStep] = useState(0);
  const current = KAKO[step];
  const isLast = step === KAKO.length - 1;

  const handleNazad = () => {
    if (step === 0) {
      navigation.navigate('Welcome');
      return;
    }
    setStep((value) => value - 1);
  };

  const handleDalje = () => {
    if (isLast) {
      navigation.navigate('IzborPaketa');
      return;
    }
    setStep((value) => value + 1);
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.topRow}>
        <Text style={styles.stepLabel}>{current.broj}</Text>
        <Pressable
          accessibilityRole="button"
          onPress={() => navigation.navigate('Prijava')}
          style={styles.skipButton}
        >
          <Text style={styles.skipLabel}>Preskoči</Text>
        </Pressable>
      </View>

      <View style={styles.progressRow}>
        <ProgressBar total={KAKO.length} current={step + 1} />
      </View>

      <View style={styles.body}>
        <Text style={styles.headline}>{current.naslov}</Text>
        <Text style={styles.paragraph}>{current.tekst}</Text>
        <View style={styles.list}>
          {current.stavke.map((stavka) => (
            <View key={stavka} style={styles.listItem}>
              <View style={styles.bullet} />
              <Text style={styles.listText}>{stavka}</Text>
            </View>
          ))}
        </View>
      </View>

      <View style={styles.footer}>
        <Button label="Nazad" variant="ghost" onPress={handleNazad} style={styles.backButton} />
        <Button
          label={isLast ? 'Pretplatite se' : 'Dalje'}
          variant="primary"
          onPress={handleDalje}
          style={styles.forwardButton}
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
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 26,
  },
  stepLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  skipButton: {
    minHeight: spacing.touchTargetMin,
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
  },
  skipLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.bark,
  },
  progressRow: {
    paddingHorizontal: 26,
    paddingTop: spacing.sm,
  },
  body: {
    flex: 1,
    paddingHorizontal: 26,
    paddingTop: 44,
  },
  headline: {
    ...typeScale.screenH2,
    fontSize: 34,
    color: colors.ink,
    letterSpacing: -0.4,
    marginBottom: spacing.md,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 17,
    lineHeight: 25,
    color: colors.bark,
    marginBottom: spacing.xl,
  },
  list: {
    gap: 14,
  },
  listItem: {
    flexDirection: 'row',
    gap: 14,
    alignItems: 'flex-start',
  },
  bullet: {
    width: 8,
    height: 8,
    marginTop: 8,
    backgroundColor: colors.ember,
  },
  listText: {
    flex: 1,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.ink,
  },
  footer: {
    flexDirection: 'row',
    gap: 10,
    paddingHorizontal: 26,
    paddingTop: spacing.lg,
    paddingBottom: 28,
  },
  backButton: {
    paddingHorizontal: spacing.lg,
  },
  forwardButton: {
    flex: 1,
  },
});

export default KakoRadiScreen;
