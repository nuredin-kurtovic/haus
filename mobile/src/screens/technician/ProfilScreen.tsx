/**
 * Serviser: Profil (task A.4: "ime, zanat ako dostupno, odjava").
 *
 * Zanat NIJE dostupan: curl-om potvrđeno na GET /me (login damir@haus.ba,
 * treći krug verifikacije) da odgovor nosi samo {user: {id,name,email,
 * notif_*}, role, subscription}, bez `technician`/`trade` polja bilo gdje
 * (MeController::show ne učitava tu relaciju). `trade` postoji samo na
 * GET /admin/technicians, koje je dispečerska ruta (role:dispecer), majstor
 * je nema. Zato ovaj ekran prikazuje ime i mejl, a "zanat" izostavlja jer
 * nije dostupan; dokumentovano i u finalnom izvještaju kao otvoreno pitanje
 * (server bi trebao vratiti trade na /me ili dodati /technician/me).
 */

import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuthStore } from '../../store/auth';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';

export function ProfilScreen() {
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.header}>
        <Text style={styles.headline}>Profil</Text>
      </View>

      <View style={styles.body}>
        <View style={styles.nameBlock}>
          <Text style={styles.name}>{user?.name ?? ''}</Text>
          <Text style={styles.email}>{user?.email ?? ''}</Text>
          <Text style={styles.role}>Serviser</Text>
        </View>

        <Pressable accessibilityRole="button" onPress={() => logout()} style={styles.logoutButton}>
          <Text style={styles.logoutLabel}>Odjava</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingHorizontal: 22,
    paddingTop: 20,
    paddingBottom: 18,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
  },
  headline: {
    ...typeScale.sectionH2,
    color: colors.ink,
    letterSpacing: -0.3,
  },
  body: {
    padding: 22,
    gap: 24,
  },
  nameBlock: {
    gap: 3,
  },
  name: {
    fontFamily: fontFamily.semiBold,
    fontSize: 20,
    color: colors.ink,
  },
  email: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
  },
  role: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginTop: 4,
  },
  logoutButton: {
    borderWidth: 1,
    borderColor: colors.sand,
    minHeight: spacing.touchTargetMin + 4,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoutLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.bark,
  },
});

export default ProfilScreen;
