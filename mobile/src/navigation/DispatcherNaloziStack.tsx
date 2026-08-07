/**
 * Stack za dispečerski tab Nalozi: ekran 17 (lista) i ekran 18 (nalog i
 * dodjela). Ugniježđen u DispatcherTabs; tab bar ostaje vidljiv na oba,
 * isti obrazac kao NaloziStack.tsx na klijentskoj strani (vidi
 * navigation/types.ts napomenu na DispatcherNaloziStackParamList).
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { DispatcherNaloziStackParamList } from './types';
import NaloziScreen from '../screens/dispatcher/NaloziScreen';
import NalogDodjelaScreen from '../screens/dispatcher/NalogDodjelaScreen';

const Stack = createNativeStackNavigator<DispatcherNaloziStackParamList>();

export function DispatcherNaloziStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="NaloziList" component={NaloziScreen} />
      <Stack.Screen name="NalogDodjela" component={NalogDodjelaScreen} />
    </Stack.Navigator>
  );
}

export default DispatcherNaloziStack;
