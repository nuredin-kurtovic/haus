/**
 * 15 Profil (design/README.md "Screens: mobile", prototip
 * data-screen-label="15 Profil").
 *
 * Tri prekidača obavještenja: prototip ih grupiše PO SADRŽAJU (termin,
 * nalaz, pregled), ali GET/PUT /client/profile (docs/API.md + čitanjem
 * ProfileController.php potvrđeno) kontroliše samo tri KANALA:
 * notif_push/notif_email/notif_marketing. Model nema polje za "vrstu"
 * obavještenja po kanalu, pa su etikete ovdje prilagođene stvarnom
 * ugovoru (push/mejl/marketing), ne prototipovoj demo podjeli. Odluka
 * dokumentovana i u finalnom izvještaju.
 *
 * Adresa je READ-ONLY (docs/API.md: "adresa se NE mijenja ovdje"), izvor
 * je GET /client/subscription.properties (isti podaci kao ekran 13, ista
 * query, keširano). "Zatražite promjenu adrese" otvara modal sa porukom,
 * POST /client/address-change-request (min 10 znakova, server uzima prvu
 * adresu ako se ne pošalje subscription_property_id).
 *
 * Odjava: auth store logout() već radi POST /auth/logout + čisti
 * keychain/store (src/store/auth.ts); RootNavigator sam prebaci na
 * Welcome čim token postane null.
 */

import React, { useState } from 'react';
import {
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Button from '../../components/Button';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import {
  useAddressChangeRequestMutation,
  useClientProfileQuery,
  useClientSubscriptionQuery,
  useUpdateProfileMutation,
} from '../../api/queries';
import { useAuthStore } from '../../store/auth';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';

const NOTIFICATION_SWITCHES: Array<{
  key: 'push' | 'email' | 'marketing';
  title: string;
  description: string;
}> = [
  {
    key: 'push',
    title: 'Obavještenja u aplikaciji',
    description: 'Termin, majstor krenuo, kašnjenje sa novim vremenom, nalaz.',
  },
  {
    key: 'email',
    title: 'Obavještenja na mejl',
    description: 'Račun, nalaz sa fotografijama, potvrda pretplate.',
  },
  {
    key: 'marketing',
    title: 'Novosti i akcije',
    description: 'Povremene poruke o paketima i akcijama. Nije obavezno.',
  },
];

export function ProfilScreen() {
  const profileQuery = useClientProfileQuery();
  const subscriptionQuery = useClientSubscriptionQuery();
  const updateProfile = useUpdateProfileMutation();
  const addressChangeRequest = useAddressChangeRequestMutation();
  const logout = useAuthStore((state) => state.logout);

  const [addressModalOpen, setAddressModalOpen] = useState(false);
  const [addressMessage, setAddressMessage] = useState('');
  const [addressError, setAddressError] = useState('');
  // Poruka poslije slanja dolazi sa servera (AddressChangeController.php),
  // ne hardkodirana kopija: server je jedini izvor istine za tekst koji
  // korisnik vidi (i za "Vi" konvenciju, curl-om potvrđeno: "Dispečer se
  // javlja na vaš mejl.", malim "v").
  const [sentMessage, setSentMessage] = useState('');

  const profile = profileQuery.data;
  const properties = subscriptionQuery.data?.properties ?? [];

  const toggleNotification = (key: 'push' | 'email' | 'marketing', value: boolean) => {
    if (!profile) {
      return;
    }
    updateProfile.mutate({
      name: profile.name,
      notifications: { ...profile.notifications, [key]: value },
    });
  };

  const submitAddressChange = async () => {
    if (addressMessage.trim().length < 10) {
      setAddressError('Napišite najmanje 10 znakova.');
      return;
    }
    setAddressError('');
    try {
      const response = await addressChangeRequest.mutateAsync({ message: addressMessage.trim() });
      setSentMessage(response.message);
      setAddressMessage('');
    } catch {
      setAddressError('Nije moguće poslati zahtjev. Pokušajte ponovo.');
    }
  };

  const closeAddressModal = () => {
    setAddressModalOpen(false);
    setSentMessage('');
    setAddressError('');
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.header}>
        <Text style={styles.headline}>Profil</Text>
      </View>

      {profileQuery.isLoading && <QueryLoadingNotice label="Učitavanje..." />}
      {profileQuery.isError && !profile && (
        <QueryErrorNotice onRetry={() => profileQuery.refetch()} />
      )}

      {!!profile && (
        <ScrollView contentContainerStyle={styles.body}>
          <View style={styles.nameBlock}>
            <Text style={styles.name}>{profile.name}</Text>
            <Text style={styles.email}>{profile.email}</Text>
          </View>

          <View style={styles.addressCard}>
            <Text style={styles.cardEyebrow}>Adresa</Text>
            {properties.length === 0 ? (
              <Text style={styles.addressLine}>Nema adrese na pretplati.</Text>
            ) : (
              properties.map((property) => (
                <Text key={property.id} style={styles.addressLine}>
                  {property.street}, {property.city}
                </Text>
              ))
            )}
            <Text style={styles.addressNote}>
              Pretplata je vezana za adresu i nije prenosiva.
            </Text>
            <Button
              label="Zatražite promjenu adrese"
              variant="ghost"
              onPress={() => setAddressModalOpen(true)}
              style={styles.addressButton}
            />
          </View>

          <View style={styles.section}>
            <Text style={styles.sectionHeader}>Obavještenja</Text>
            <View style={styles.switchList}>
              {NOTIFICATION_SWITCHES.map((item) => (
                <View key={item.key} style={styles.switchRow}>
                  <View style={styles.switchText}>
                    <Text style={styles.switchTitle}>{item.title}</Text>
                    <Text style={styles.switchDescription}>{item.description}</Text>
                  </View>
                  <Switch
                    value={profile.notifications[item.key]}
                    onValueChange={(value) => toggleNotification(item.key, value)}
                    trackColor={{ false: colors.sand, true: colors.ember }}
                    thumbColor={colors.white}
                  />
                </View>
              ))}
            </View>
          </View>

          <Pressable
            accessibilityRole="button"
            onPress={() => logout()}
            style={styles.logoutButton}
          >
            <Text style={styles.logoutLabel}>Odjava</Text>
          </Pressable>
        </ScrollView>
      )}

      <Modal
        visible={addressModalOpen}
        animationType="slide"
        transparent
        onRequestClose={closeAddressModal}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalSheet}>
            <Text style={styles.modalTitle}>Zahtjev za promjenu adrese</Text>
            {sentMessage ? (
              <>
                <Text style={styles.modalBody}>{sentMessage}</Text>
                <Button label="Zatvorite" onPress={closeAddressModal} style={styles.modalButton} />
              </>
            ) : (
              <>
                <Text style={styles.modalBody}>
                  Napišite koju adresu mijenjate i na koju. Dispečer potvrđuje promjenu.
                </Text>
                <TextInput
                  multiline
                  numberOfLines={4}
                  value={addressMessage}
                  onChangeText={setAddressMessage}
                  placeholder="npr. Selimo se na Alipašinu 12, Sarajevo, od 1. septembra."
                  placeholderTextColor={colors.grey}
                  style={styles.modalTextarea}
                />
                {!!addressError && <Text style={styles.modalError}>{addressError}</Text>}
                <Button
                  label={addressChangeRequest.isPending ? 'Šaljemo...' : 'Pošaljite zahtjev'}
                  disabled={addressChangeRequest.isPending}
                  onPress={submitAddressChange}
                  style={styles.modalButton}
                />
                <Pressable onPress={closeAddressModal} style={styles.modalCancel}>
                  <Text style={styles.modalCancelLabel}>Otkažite</Text>
                </Pressable>
              </>
            )}
          </View>
        </View>
      </Modal>
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
  addressCard: {
    borderWidth: 1,
    borderColor: colors.sand,
    padding: 18,
    gap: 6,
  },
  cardEyebrow: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 4,
  },
  addressLine: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    color: colors.ink,
  },
  addressNote: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
    marginBottom: 8,
  },
  addressButton: {
    marginTop: 6,
  },
  section: {
    gap: 12,
  },
  sectionHeader: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  switchList: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  switchRow: {
    backgroundColor: colors.white,
    padding: 16,
    minHeight: 60,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  switchText: {
    flex: 1,
    gap: 3,
  },
  switchTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
  },
  switchDescription: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    lineHeight: 19,
    color: colors.bark,
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
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(37,36,34,0.4)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    backgroundColor: colors.white,
    padding: 22,
    gap: 14,
  },
  modalTitle: {
    fontFamily: fontFamily.bold,
    fontSize: 22,
    color: colors.ink,
  },
  modalBody: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
  },
  modalTextarea: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    padding: 14,
    minHeight: 100,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
    textAlignVertical: 'top',
  },
  modalError: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.error,
  },
  modalButton: {
    marginTop: 4,
  },
  modalCancel: {
    minHeight: spacing.touchTargetMin,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalCancelLabel: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.bark,
  },
});

export default ProfilScreen;
