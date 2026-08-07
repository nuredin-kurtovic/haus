/**
 * Stack za serviserski tab Nalozi: "Moji nalozi" (lista), "Detalj naloga",
 * "Završetak naloga" (wizard) i "Završetak: potvrda" (ink potvrdni ekran
 * sa garancijom). Ugniježđen u TechnicianTabs; tab bar ostaje vidljiv na
 * svim ekranima osim što potvrda vraća korisnika na listu (reset), isti
 * obrazac kao PrijaviStack.tsx na klijentskoj strani.
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import type { TechnicianNaloziStackParamList } from './types';
import MojiNaloziScreen from '../screens/technician/MojiNaloziScreen';
import NalogDetaljScreen from '../screens/technician/NalogDetaljScreen';
import ZavrsetakNalogaScreen from '../screens/technician/ZavrsetakNalogaScreen';
import ZavrsetakPotvrdaScreen from '../screens/technician/ZavrsetakPotvrdaScreen';

const Stack = createNativeStackNavigator<TechnicianNaloziStackParamList>();

export function TechnicianNaloziStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="MojiNalozi" component={MojiNaloziScreen} />
      <Stack.Screen name="NalogDetalj" component={NalogDetaljScreen} />
      <Stack.Screen name="ZavrsetakNaloga" component={ZavrsetakNalogaScreen} />
      <Stack.Screen name="ZavrsetakPotvrda" component={ZavrsetakPotvrdaScreen} />
    </Stack.Navigator>
  );
}

export default TechnicianNaloziStack;
