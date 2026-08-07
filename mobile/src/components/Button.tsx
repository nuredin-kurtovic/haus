/**
 * Primarno dugme za HAUS mobile.
 *
 * Varijante:
 * - primary: ember ploha, ivory tekst, 52px visine (design/README.md:
 *   "primary buttons are 52px").
 * - ghost: 1px ink border, ink tekst, transparentna pozadina.
 *
 * NIKAD border radius: ivice su uvijek oštre, u skladu sa CLAUDE.md /
 * design/README.md binding pravilima.
 */

import React from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  type StyleProp,
  type ViewStyle,
} from 'react-native';
import { colors, spacing, typeScale } from '../theme/tokens';

export type ButtonVariant = 'primary' | 'ghost';

interface ButtonProps {
  label: string;
  onPress?: () => void;
  variant?: ButtonVariant;
  disabled?: boolean;
  style?: StyleProp<ViewStyle>;
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  disabled = false,
  style,
}: ButtonProps) {
  const isPrimary = variant === 'primary';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled }}
      onPress={disabled ? undefined : onPress}
      style={({ pressed }) => [
        styles.base,
        isPrimary ? styles.primary : styles.ghost,
        disabled && styles.disabled,
        pressed && !disabled && (isPrimary ? styles.primaryPressed : styles.ghostPressed),
        style,
      ]}
    >
      <Text
        style={[
          styles.label,
          isPrimary ? styles.primaryLabel : styles.ghostLabel,
          disabled && styles.disabledLabel,
        ]}
      >
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    height: spacing.primaryButtonHeight,
    minHeight: spacing.touchTargetMin,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
    // Nema borderRadius: HAUS nema zaokruženih ivica nigdje u UI-u.
  },
  primary: {
    backgroundColor: colors.ember,
  },
  primaryPressed: {
    backgroundColor: colors.emberDark,
  },
  ghost: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: colors.ink,
  },
  ghostPressed: {
    backgroundColor: colors.ivory,
  },
  disabled: {
    backgroundColor: colors.sand,
    borderWidth: 0,
  },
  label: {
    ...typeScale.button,
  },
  primaryLabel: {
    color: colors.ivory,
  },
  ghostLabel: {
    color: colors.ink,
  },
  disabledLabel: {
    // Nema svijetlog teksta na sandu: ink tekst čak i u disabled stanju.
    color: colors.ink,
  },
});

export default Button;
