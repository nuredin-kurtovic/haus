/**
 * Segmentirani progress bar (koristi se npr. na "Kako radi" onboarding
 * ekranu). Pravougaoni segmenti, 6px gap, ember za pređeno, sand za
 * ostatak. Nema border radius.
 */

import React from 'react';
import { StyleSheet, View } from 'react-native';
import { colors, spacing } from '../theme/tokens';

interface ProgressBarProps {
  /** Ukupan broj segmenata. */
  total: number;
  /** Broj pređenih (ember) segmenata, 0-indexed inclusive count. */
  current: number;
}

export function ProgressBar({ total, current }: ProgressBarProps) {
  const segments = Array.from({ length: total }, (_, index) => index < current);

  return (
    <View style={styles.row}>
      {segments.map((reached, index) => (
        <View
          key={index}
          style={[
            styles.segment,
            { backgroundColor: reached ? colors.ember : colors.sand },
          ]}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: spacing.progressBarGap,
  },
  segment: {
    flex: 1,
    height: 4,
    // Nema borderRadius: pravougaoni segmenti.
  },
});

export default ProgressBar;
