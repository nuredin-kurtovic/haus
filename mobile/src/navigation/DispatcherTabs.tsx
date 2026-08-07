import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import type { DispatcherTabParamList } from './types';
import { TabBar } from './TabBar';
import DanasScreen from '../screens/dispatcher/DanasScreen';
import NaloziScreen from '../screens/dispatcher/NaloziScreen';
import GradoviScreen from '../screens/dispatcher/GradoviScreen';

const Tab = createBottomTabNavigator<DispatcherTabParamList>();

export function DispatcherTabs() {
  return (
    <Tab.Navigator
      screenOptions={{ headerShown: false }}
      tabBar={(props) => <TabBar {...props} />}
    >
      <Tab.Screen name="Danas" component={DanasScreen} />
      <Tab.Screen name="Nalozi" component={NaloziScreen} />
      <Tab.Screen name="Gradovi" component={GradoviScreen} />
    </Tab.Navigator>
  );
}

export default DispatcherTabs;
