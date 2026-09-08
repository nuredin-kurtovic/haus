/**
 * Root navigator. Bira stack po auth stanju:
 * - nema tokena -> Onboarding / Auth / Registracija (jedan stack, bez tab
 *   bara, kako design/README.md nalaže).
 * - klijent -> ClientTabs.
 * - dispecer -> DispatcherTabs.
 * - majstor -> TechnicianTabs (serviser, docs/API.md "Serviser (uloga
 *   majstor)"; login vraća `role` u odgovoru, auth store ga već nosi).
 */

import React, { useEffect } from 'react';
import { StyleSheet, View } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuthStore } from '../store/auth';
import { colors } from '../theme/tokens';
import type { RootStackParamList } from './types';

import WelcomeScreen from '../screens/onboarding/WelcomeScreen';
import KakoRadiScreen from '../screens/onboarding/KakoRadiScreen';
import PrijavaScreen from '../screens/auth/PrijavaScreen';
import IzborPaketaScreen from '../screens/registracija/IzborPaketaScreen';
import PodaciAdresaScreen from '../screens/registracija/PodaciAdresaScreen';
import PlacanjeScreen from '../screens/registracija/PlacanjeScreen';
import KarticaInfoScreen from '../screens/registracija/KarticaInfoScreen';
import PretplataAktivnaScreen from '../screens/registracija/PretplataAktivnaScreen';
import ClientTabs from './ClientTabs';
import DispatcherTabs from './DispatcherTabs';
import TechnicianTabs from './TechnicianTabs';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const token = useAuthStore((state) => state.token);
  const role = useAuthStore((state) => state.role);
  const isHydrating = useAuthStore((state) => state.isHydrating);
  const hydrate = useAuthStore((state) => state.hydrate);

  useEffect(() => {
    hydrate();
  }, [hydrate]);

  if (isHydrating) {
    // Ember platno dok se čita keychain / poziva /me: nastavlja se na launch
    // splash (ista ploha), pa nema bijelog bljeska izmedju splasha i prvog ekrana.
    return <View style={styles.splash} />;
  }

  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      {!token ? (
        <Stack.Group>
          <Stack.Screen name="Welcome" component={WelcomeScreen} />
          <Stack.Screen name="KakoRadi" component={KakoRadiScreen} />
          <Stack.Screen name="Prijava" component={PrijavaScreen} />
          <Stack.Screen name="IzborPaketa" component={IzborPaketaScreen} />
          <Stack.Screen name="PodaciAdresa" component={PodaciAdresaScreen} />
          <Stack.Screen name="Placanje" component={PlacanjeScreen} />
          <Stack.Screen name="KarticaInfo" component={KarticaInfoScreen} />
          <Stack.Screen
            name="PretplataAktivna"
            component={PretplataAktivnaScreen}
          />
        </Stack.Group>
      ) : role === 'dispecer' ? (
        <Stack.Screen name="DispatcherTabs" component={DispatcherTabs} />
      ) : role === 'majstor' ? (
        <Stack.Screen name="TechnicianTabs" component={TechnicianTabs} />
      ) : (
        <Stack.Screen name="ClientTabs" component={ClientTabs} />
      )}
    </Stack.Navigator>
  );
}

const styles = StyleSheet.create({
  splash: {
    flex: 1,
    backgroundColor: colors.ember,
  },
});

export default RootNavigator;
