/**
 * Stack za tab Pretplata: ekran 13 (glavni) i ekran 14 (cjenovnik).
 * Ugniježđen u ClientTabs; tab bar ostaje vidljiv na oba.
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { PretplataStackParamList } from './types';
import PretplataScreen from '../screens/client/PretplataScreen';
import CjenovnikScreen from '../screens/client/CjenovnikScreen';

const Stack = createNativeStackNavigator<PretplataStackParamList>();

export function PretplataStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="PretplataGlavna" component={PretplataScreen} />
      <Stack.Screen name="Cjenovnik" component={CjenovnikScreen} />
    </Stack.Navigator>
  );
}

export default PretplataStack;
