/**
 * 01 Welcome (design/README.md "Screens: mobile", prototip ekran
 * data-screen-label="01 Welcome").
 *
 * Imenuje problem umjesto da objašnjava proizvod: naslov u četiri kratka
 * reda, fotografija majstora, ember-wrapped ivory footer sa dva dugmeta.
 * Bez tab bara (onboarding je van tab navigacije).
 *
 * Logo: react-native-svg nije instaliran u projektu i task izričito traži
 * da se ne dodaje native zavisnost za ovaj zadatak, pa je logo-primary.svg
 * (design/logo-primary.svg) rasterizovan u PNG (@1x/@2x/@3x) makOS
 * alatima (qlmanage thumbnail + alpha-key preko bijele pozadine, skripta u
 * scratchpadu, nije dio repoa) i sačuvan u mobile/assets/images/. Vidi
 * napomenu u završnom izvještaju za detalje i alternativu ako se
 * react-native-svg ipak instalira kasnije.
 */

import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { colors, fontFamily, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function WelcomeScreen() {
  const navigation = useNavigation<Nav>();

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.header}>
        <Image
          source={require('../../../assets/images/logo-primary.png')}
          style={styles.logo}
          resizeMode="contain"
          accessibilityLabel="HAUS"
        />
        <Text style={styles.headline}>
          Pukla cijev.{'\n'}Nestalo struje.{'\n'}Vrata se ne{'\n'}zatvaraju.
        </Text>
        <Text style={styles.subline}>
          Ne tražite majstora. Ne pregovarate cijenu. Ne čekate cijeli dan.
        </Text>
      </View>

      <View style={styles.photoArea}>
        <Image
          source={require('../../../assets/images/majstor.png')}
          style={styles.photo}
          resizeMode="contain"
          accessibilityLabel="HAUS majstor"
        />
      </View>

      <View style={styles.emberWrap}>
        <View style={styles.ivoryInner}>
          <Button
            label="Pretplatite se"
            variant="primary"
            onPress={() => navigation.navigate('KakoRadi')}
          />
          <Button
            label="Imam pretplatu"
            variant="ghost"
            onPress={() => navigation.navigate('Prijava')}
          />
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingHorizontal: 26,
    paddingTop: 30,
  },
  logo: {
    width: 140,
    height: 30,
    marginBottom: 32,
  },
  headline: {
    ...typeScale.welcomeHeadline,
    color: colors.ink,
    letterSpacing: -0.4,
    marginBottom: 14,
  },
  subline: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 23,
    color: colors.bark,
  },
  photoArea: {
    flex: 1,
    minHeight: 150,
    overflow: 'hidden',
    justifyContent: 'flex-end',
  },
  photo: {
    // Cijela figura vidljiva, skalirana na preostalu visinu i oslonjena na
    // dno (na ember footer), po prototipu. Cover je rezao glavu na visokim
    // ekranima. 701/1024 je prirodni odnos majstor.png.
    height: '100%',
    aspectRatio: 701 / 1024,
    alignSelf: 'center',
  },
  emberWrap: {
    backgroundColor: colors.ember,
    padding: 3,
    paddingBottom: 0,
  },
  ivoryInner: {
    backgroundColor: colors.ivory,
    paddingHorizontal: 24,
    paddingTop: 22,
    paddingBottom: 26,
    gap: 10,
  },
});

export default WelcomeScreen;
