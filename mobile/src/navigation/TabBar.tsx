/**
 * Custom bottom tab bar.
 *
 * design/README.md "Mobile-specific rules": ink i bark boje, 3px ember
 * gornja linija na aktivnom tabu, label 11px, weight 600 na aktivnom.
 * Zadržavamo full custom tabBar (umjesto tabBarStyle) da linija bude po
 * tabu, ne po cijeloj traci.
 */

import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { colors, spacing, typeScale } from '../theme/tokens';

export function TabBar({ state, descriptors, navigation }: BottomTabBarProps) {
  // Home indicator ne smije preko labela: traka nosi donji safe area inset.
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.bar, { paddingBottom: insets.bottom }]}>
      {state.routes.map((route, index) => {
        const { options } = descriptors[route.key];
        const label = options.tabBarLabel ?? options.title ?? route.name;
        const isFocused = state.index === index;

        const onPress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: route.key,
            canPreventDefault: true,
          });
          if (!isFocused && !event.defaultPrevented) {
            navigation.navigate(route.name);
          }
        };

        return (
          <Pressable
            key={route.key}
            onPress={onPress}
            accessibilityRole="button"
            accessibilityState={isFocused ? { selected: true } : {}}
            style={styles.item}
          >
            <View style={[styles.topRule, isFocused && styles.topRuleActive]} />
            <Text
              style={[
                styles.label,
                isFocused ? styles.labelActive : styles.labelInactive,
              ]}
            >
              {typeof label === 'string' ? label : route.name}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: colors.white,
    borderTopWidth: 1,
    borderTopColor: colors.sand,
  },
  item: {
    flex: 1,
    minHeight: spacing.touchTargetMin,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: spacing.xs,
    paddingBottom: spacing.sm,
  },
  topRule: {
    width: '60%',
    height: 3,
    marginBottom: spacing.xs,
    backgroundColor: 'transparent',
  },
  topRuleActive: {
    backgroundColor: colors.ember,
  },
  label: {
    ...typeScale.tabLabel,
  },
  labelActive: {
    ...typeScale.tabLabelActive,
    color: colors.ink,
  },
  labelInactive: {
    color: colors.bark,
  },
});

export default TabBar;
