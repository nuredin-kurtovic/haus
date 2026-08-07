import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import type { ClientTabParamList } from './types';
import { TabBar } from './TabBar';
import PocetnaScreen from '../screens/client/PocetnaScreen';
import PrijaviScreen from '../screens/client/PrijaviScreen';
import NaloziScreen from '../screens/client/NaloziScreen';
import PretplataScreen from '../screens/client/PretplataScreen';
import ProfilScreen from '../screens/client/ProfilScreen';

const Tab = createBottomTabNavigator<ClientTabParamList>();

export function ClientTabs() {
  return (
    <Tab.Navigator
      screenOptions={{ headerShown: false }}
      tabBar={(props) => <TabBar {...props} />}
    >
      <Tab.Screen name="Pocetna" component={PocetnaScreen} />
      <Tab.Screen name="Prijavi" component={PrijaviScreen} />
      <Tab.Screen name="Nalozi" component={NaloziScreen} />
      <Tab.Screen name="Pretplata" component={PretplataScreen} />
      <Tab.Screen name="Profil" component={ProfilScreen} />
    </Tab.Navigator>
  );
}

export default ClientTabs;
