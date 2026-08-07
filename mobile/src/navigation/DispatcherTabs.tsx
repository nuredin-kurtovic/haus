/**
 * Ekrani 16-19 (design/README.md "Screens: mobile"). Nalozi je ugniježđen
 * native-stack (lista + "Nalog i dodjela" detalj), isti obrazac kao
 * klijentski NaloziStack, da tab bar ostane vidljiv na detalju.
 */
import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import type { DispatcherTabParamList } from './types';
import { TabBar } from './TabBar';
import DanasScreen from '../screens/dispatcher/DanasScreen';
import DispatcherNaloziStack from './DispatcherNaloziStack';
import GradoviScreen from '../screens/dispatcher/GradoviScreen';

const Tab = createBottomTabNavigator<DispatcherTabParamList>();

export function DispatcherTabs() {
  return (
    <Tab.Navigator
      screenOptions={{ headerShown: false }}
      tabBar={(props) => <TabBar {...props} />}
    >
      <Tab.Screen name="Danas" component={DanasScreen} />
      <Tab.Screen name="Nalozi" component={DispatcherNaloziStack} />
      <Tab.Screen name="Gradovi" component={GradoviScreen} />
    </Tab.Navigator>
  );
}

export default DispatcherTabs;
