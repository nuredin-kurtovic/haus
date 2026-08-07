/**
 * State chip za prikaz statusa naloga.
 *
 * Mapping je ugovoran, isti na webu i mobile (docs/API.md "Konvencije" i
 * design/README.md "Job lifecycle"):
 *   novo      = ink border  / white fill / ink tekst
 *   zakazano  = bark border / ivory fill / bark tekst
 *   u_toku    = ember border/ ember fill / ivory tekst
 *   zavrseno  = sand border / sand fill  / ink tekst
 *   garancija = ink border  / ink fill   / ivory tekst
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { type ChipState, stateChipStyles, typeScale } from '../theme/tokens';

const CHIP_LABELS: Record<ChipState, string> = {
  novo: 'Novo',
  zakazano: 'Zakazano',
  u_toku: 'U toku',
  zavrseno: 'Završeno',
  garancija: 'Garancija',
};

interface StateChipProps {
  state: ChipState;
  /** Prepiši prikazani tekst; podrazumijevano koristi CHIP_LABELS. */
  label?: string;
}

export function StateChip({ state, label }: StateChipProps) {
  const chipStyle = stateChipStyles[state];

  return (
    <View
      style={[
        styles.chip,
        { backgroundColor: chipStyle.fill, borderColor: chipStyle.border },
      ]}
    >
      <Text style={[styles.label, { color: chipStyle.text }]}>
        {label ?? CHIP_LABELS[state]}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: {
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 4,
    alignSelf: 'flex-start',
    // Nema borderRadius.
  },
  label: {
    ...typeScale.eyebrow,
    letterSpacing: 0.5,
  },
});

export default StateChip;
