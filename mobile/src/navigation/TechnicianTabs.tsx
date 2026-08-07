/**
 * Tabovi za ulogu majstor (serviser): Nalozi, Profil (task A, design/
 * README.md "Mobile-specific rules" ne navodi serviserske tabove
 * eksplicitno jer prototip nema serviserski flow; ime i redoslijed su task
 * odluka, isti ink/bark + 3px ember gornja linija TabBar kao klijent i
 * dispečer).
 */
import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import type { TechnicianTabParamList } from './types';
import { TabBar } from './TabBar';
import TechnicianNaloziStack from './TechnicianNaloziStack';
import ProfilScreen from '../screens/technician/ProfilScreen';

const Tab = createBottomTabNavigator<TechnicianTabParamList>();

export function TechnicianTabs() {
  return (
    <Tab.Navigator
      screenOptions={{ headerShown: false }}
      tabBar={(props) => <TabBar {...props} />}
    >
      <Tab.Screen name="Nalozi" component={TechnicianNaloziStack} />
      <Tab.Screen name="Profil" component={ProfilScreen} />
    </Tab.Navigator>
  );
}

export default TechnicianTabs;
