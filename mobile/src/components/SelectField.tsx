/**
 * Select polje za grad: dugme koje otvara modal listu (44px stavke), bez
 * dodatnih zavisnosti (plain React Native Modal + FlatList). Koristi se
 * svugdje gdje ugovor traži "grad je select aktivnih gradova"
 * (docs/API.md, design/README.md).
 */

import React, { useState } from 'react';
import {
  FlatList,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, fontFamily, spacing, typeScale } from '../theme/tokens';

export interface SelectOption {
  label: string;
  value: number;
}

function Separator() {
  return <View style={styles.separator} />;
}

interface SelectFieldProps {
  label: string;
  placeholder: string;
  options: SelectOption[];
  value: number | null;
  onSelect: (value: number) => void;
  error?: string;
}

export function SelectField({
  label,
  placeholder,
  options,
  value,
  onSelect,
  error,
}: SelectFieldProps) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.value === value);

  return (
    <View style={styles.container}>
      <Text style={styles.label}>{label}</Text>
      <Pressable
        accessibilityRole="button"
        onPress={() => setOpen(true)}
        style={[styles.trigger, !!error && styles.triggerError]}
      >
        <Text style={selected ? styles.value : styles.placeholder}>
          {selected ? selected.label : placeholder}
        </Text>
      </Pressable>
      {!!error && <Text style={styles.error}>{error}</Text>}

      <Modal
        visible={open}
        animationType="slide"
        transparent
        onRequestClose={() => setOpen(false)}
      >
        <View style={styles.overlay}>
          <SafeAreaView style={styles.sheet} edges={['bottom']}>
            <View style={styles.sheetHeader}>
              <Text style={styles.sheetTitle}>{label}</Text>
              <Pressable
                accessibilityRole="button"
                onPress={() => setOpen(false)}
                style={styles.closeButton}
              >
                <Text style={styles.closeLabel}>Zatvorite</Text>
              </Pressable>
            </View>
            <FlatList
              data={options}
              keyExtractor={(item) => String(item.value)}
              renderItem={({ item }) => (
                <Pressable
                  accessibilityRole="button"
                  onPress={() => {
                    onSelect(item.value);
                    setOpen(false);
                  }}
                  style={styles.row}
                >
                  <Text
                    style={[
                      styles.rowLabel,
                      item.value === value && styles.rowLabelActive,
                    ]}
                  >
                    {item.label}
                  </Text>
                </Pressable>
              )}
              ItemSeparatorComponent={Separator}
            />
          </SafeAreaView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.labelToInput,
  },
  label: {
    ...typeScale.eyebrow,
    color: colors.bark,
    letterSpacing: 0.7,
  },
  trigger: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    paddingVertical: 15,
    minHeight: spacing.primaryButtonHeight,
    justifyContent: 'center',
  },
  triggerError: {
    borderColor: colors.error,
  },
  value: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  placeholder: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.grey,
  },
  error: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    lineHeight: 18,
    color: colors.error,
  },
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(37,36,34,0.4)',
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.white,
    maxHeight: '70%',
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.screenPadding,
    paddingVertical: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  sheetTitle: {
    ...typeScale.cardH3,
    color: colors.ink,
  },
  closeButton: {
    minHeight: spacing.touchTargetMin,
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
  },
  closeLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.bark,
  },
  row: {
    minHeight: 44,
    justifyContent: 'center',
    paddingHorizontal: spacing.screenPadding,
  },
  rowLabel: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  rowLabelActive: {
    fontFamily: fontFamily.semiBold,
  },
  separator: {
    height: 1,
    backgroundColor: colors.sand,
  },
});

export default SelectField;
