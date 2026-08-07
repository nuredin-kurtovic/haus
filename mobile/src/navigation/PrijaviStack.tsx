/**
 * Stack za tab Prijavi: ekran 09 (wizard) i ekran 10 (potvrda). Ugniježđen
 * u ClientTabs; tab bar ostaje vidljiv na oba (React Navigation default za
 * ugniježđene stack-ove, vidi navigation/types.ts napomenu).
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { PrijaviStackParamList } from './types';
import PrijaviScreen from '../screens/client/PrijaviScreen';
import PrijavaPrimljenaScreen from '../screens/client/PrijavaPrimljenaScreen';

const Stack = createNativeStackNavigator<PrijaviStackParamList>();

export function PrijaviStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="PrijaviKvar" component={PrijaviScreen} />
      <Stack.Screen name="PrijavaPrimljena" component={PrijavaPrimljenaScreen} />
    </Stack.Navigator>
  );
}

export default PrijaviStack;
