/**
 * Prijavi/Nalozi/Pretplata su ugniježđeni native-stack navigatori (ekrani
 * 09-10, 11-12, 13-14), ne goli leaf ekrani: vidi navigation/types.ts
 * napomenu na ClientTabParamList i PrijaviStack.tsx/NaloziStack.tsx/
 * PretplataStack.tsx.
 */
import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import type { ClientTabParamList } from './types';
import { TabBar } from './TabBar';
import PocetnaScreen from '../screens/client/PocetnaScreen';
import PrijaviStack from './PrijaviStack';
import NaloziStack from './NaloziStack';
import PretplataStack from './PretplataStack';
import ProfilScreen from '../screens/client/ProfilScreen';

const Tab = createBottomTabNavigator<ClientTabParamList>();

export function ClientTabs() {
  return (
    <Tab.Navigator
      screenOptions={{ headerShown: false }}
      tabBar={(props) => <TabBar {...props} />}
    >
      <Tab.Screen name="Pocetna" component={PocetnaScreen} />
      <Tab.Screen name="Prijavi" component={PrijaviStack} />
      <Tab.Screen name="Nalozi" component={NaloziStack} />
      <Tab.Screen name="Pretplata" component={PretplataStack} />
      <Tab.Screen name="Profil" component={ProfilScreen} />
    </Tab.Navigator>
  );
}

export default ClientTabs;
