/**
 * Stack za tab Nalozi: ekran 11 (lista) i ekran 12 (detalj + nalaz).
 * Ugniježđen u ClientTabs; tab bar ostaje vidljiv na oba.
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { NaloziStackParamList } from './types';
import NaloziScreen from '../screens/client/NaloziScreen';
import NalogDetaljScreen from '../screens/client/NalogDetaljScreen';

const Stack = createNativeStackNavigator<NaloziStackParamList>();

export function NaloziStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="NaloziList" component={NaloziScreen} />
      <Stack.Screen name="NalogDetalj" component={NalogDetaljScreen} />
    </Stack.Navigator>
  );
}

export default NaloziStack;
