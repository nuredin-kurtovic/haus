/**
 * Labelirano tekstualno polje sa inline greškom.
 *
 * design/README.md: label 12px/600 uppercase bark, input min-height 52px,
 * border ink (error boja kad polje ima grešku), greška 13px #B03000 ispod
 * polja (CLAUDE.md / task pravila).
 */

import React, { forwardRef } from 'react';
import {
  StyleSheet,
  Text,
  TextInput,
  View,
  type KeyboardTypeOptions,
  type TextInputProps,
} from 'react-native';
import { colors, fontFamily, spacing, typeScale } from '../theme/tokens';

interface TextFieldProps {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  onBlur?: () => void;
  error?: string;
  placeholder?: string;
  secureTextEntry?: boolean;
  keyboardType?: KeyboardTypeOptions;
  autoCapitalize?: TextInputProps['autoCapitalize'];
  autoComplete?: TextInputProps['autoComplete'];
  returnKeyType?: TextInputProps['returnKeyType'];
  onSubmitEditing?: () => void;
}

export const TextField = forwardRef<TextInput, TextFieldProps>(
  function TextFieldInner(
    {
      label,
      value,
      onChangeText,
      onBlur,
      error,
      placeholder,
      secureTextEntry,
      keyboardType,
      autoCapitalize = 'none',
      autoComplete,
      returnKeyType,
      onSubmitEditing,
    },
    ref,
  ) {
    return (
      <View style={styles.container}>
        <Text style={styles.label}>{label}</Text>
        <TextInput
          ref={ref}
          value={value}
          onChangeText={onChangeText}
          onBlur={onBlur}
          placeholder={placeholder}
          placeholderTextColor={colors.grey}
          secureTextEntry={secureTextEntry}
          keyboardType={keyboardType}
          autoCapitalize={autoCapitalize}
          autoComplete={autoComplete}
          returnKeyType={returnKeyType}
          onSubmitEditing={onSubmitEditing}
          style={[styles.input, !!error && styles.inputError]}
        />
        {!!error && <Text style={styles.error}>{error}</Text>}
      </View>
    );
  },
);

const styles = StyleSheet.create({
  container: {
    gap: spacing.labelToInput,
  },
  label: {
    ...typeScale.eyebrow,
    color: colors.bark,
    letterSpacing: 0.7,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    paddingVertical: 15,
    minHeight: spacing.primaryButtonHeight,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  inputError: {
    borderColor: colors.error,
  },
  error: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    lineHeight: 18,
    color: colors.error,
  },
});

export default TextField;
