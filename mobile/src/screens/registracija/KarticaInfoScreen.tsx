/**
 * InfoScreen placeholder za kartično plaćanje (task: "za sada otvori
 * InfoScreen 'Plaćanje karticom se završava u web pretraživaču' placeholder,
 * WebView tok dolazi u fazi plaćanja").
 *
 * Server je vratio payment.redirect_url (Monri) u POST /auth/register
 * odgovoru (docs/API.md), ali stvarni WebView tok za 3-D Secure još nije
 * napravljen (design/README.md "Payments (Monri)": tokenizacija, webhook
 * itd. dolaze u fazi integracije plaćanja). Ovaj ekran samo najavljuje šta
 * dolazi i vodi dalje na ekran 07 u "kartica" stanju.
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function KarticaInfoScreen() {
  const navigation = useNavigation<Nav>();

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.body}>
        <Text style={styles.headline}>Plaćanje karticom</Text>
        <Text style={styles.paragraph}>
          Plaćanje karticom se završava u web pretraživaču. Taj korak dolazi u
          sljedećoj fazi. Za sada nastavljamo bez unosa podataka kartice.
        </Text>
      </View>
      <View style={styles.footer}>
        <Button
          label="Nastavite"
          onPress={() => navigation.replace('PretplataAktivna')}
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
  body: {
    flex: 1,
    paddingHorizontal: 26,
    paddingTop: 44,
    gap: spacing.md,
  },
  headline: {
    ...typeScale.screenH2,
    color: colors.ink,
    letterSpacing: -0.4,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 17,
    lineHeight: 25,
    color: colors.bark,
  },
  footer: {
    paddingHorizontal: 26,
    paddingBottom: 28,
  },
});

export default KarticaInfoScreen;
