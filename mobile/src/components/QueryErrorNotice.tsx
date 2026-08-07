/**
 * Poruka greške za query koji ne uspije (npr. API ne radi) + dugme Pokušajte
 * ponovo. Task pravilo: "Ekrani moraju raditi i kad API ne radi: query
 * error state sa porukom i retry, ne crash."
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { colors, fontFamily, spacing, typeScale } from '../theme/tokens';
import Button from './Button';

interface QueryErrorNoticeProps {
  message?: string;
  onRetry: () => void;
}

export function QueryErrorNotice({
  message = 'Nije moguće učitati podatke. Provjerite internet vezu.',
  onRetry,
}: QueryErrorNoticeProps) {
  return (
    <View style={styles.container}>
      <Text style={styles.message}>{message}</Text>
      <Button label="Pokušajte ponovo" variant="ghost" onPress={onRetry} />
    </View>
  );
}

export function QueryLoadingNotice({ label = 'Učitavanje...' }: { label?: string }) {
  return (
    <View style={styles.container}>
      <Text style={styles.loading}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: spacing.screenPadding,
    paddingVertical: spacing.xl,
    gap: spacing.md,
    alignItems: 'flex-start',
  },
  message: {
    ...typeScale.bodySmall,
    color: colors.bark,
  },
  loading: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.bark,
  },
});

export default QueryErrorNotice;
