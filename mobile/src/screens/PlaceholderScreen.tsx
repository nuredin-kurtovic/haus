/**
 * Placeholder ekran. Sljedeća faza gradi stvarne ekrane; ovaj temelj samo
 * verifikuje da navigacija i tema radi: bijela pozadina, ink tekst,
 * Poppins font, naziv ekrana.
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, spacing, typeScale } from '../theme/tokens';

interface PlaceholderScreenProps {
  title: string;
}

export function PlaceholderScreen({ title }: PlaceholderScreenProps) {
  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.content}>
        <Text style={styles.title}>{title}</Text>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  content: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.screenPadding,
  },
  title: {
    ...typeScale.screenH2,
    color: colors.ink,
    textAlign: 'center',
  },
});

export default PlaceholderScreen;
