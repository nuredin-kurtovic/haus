/**
 * 07 Pretplata aktivna (design/README.md, prototip data-screen-label="07
 * Pretplata aktivna").
 *
 * Prototip ima samo jedno stanje (uvijek "aktivna", jer je statičan demo
 * sa lažnim 900ms "settle"). Stvarni backend razlikuje ishode preko
 * `subscription.status` (curl-om potvrđeno na živi server, lead review
 * avgust 2026): "cekanje_uplate", "aktivna", "ponuda". Bitno: čak i
 * payment_method "kartica" trenutno vraća "cekanje_uplate" jer Monri
 * webhook još ne postoji (fake redirect_url, vidi KarticaInfoScreen), pa
 * ovaj ekran NE pretpostavlja ishod po payment_method-u kako je prva
 * verzija radila. Umjesto route param-a, čita stvarni
 * `result.status`/`result.subscription.status` iz registrationStore.
 *
 * "Uđite u aplikaciju" upisuje sesiju u auth store (keychain + token/role)
 * tek ovdje, ne ranije: RootNavigator prebacuje stack čim auth store ima
 * token, pa mora ostati praznog do ovog tapa da bi ekrani 06 (info
 * placeholder) i 07 uopšte mogli da se prikažu. POST /auth/register ne
 * vraća `role` na user objektu (curl-om potvrđeno), pa se ovdje tvrdo
 * postavlja 'klijent': javna registracija nikad ne pravi dispečera.
 */

import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { useAuthStore } from '../../store/auth';
import { useRegistrationStore } from '../../store/registration';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

function formatDate(iso: string | null): string | null {
  if (!iso) {
    return null;
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return iso;
  }
  const dd = String(date.getDate()).padStart(2, '0');
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  return `${dd}.${mm}.${date.getFullYear()}.`;
}

export function PretplataAktivnaScreen() {
  const navigation = useNavigation<Nav>();
  const commitSession = useAuthStore((state) => state.commitSession);
  const draft = useRegistrationStore((state) => state);
  const resetDraft = useRegistrationStore((state) => state.reset);

  const result = draft.result;

  if (!result || !draft.pkg) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.fallback}>
          <Text style={styles.fallbackText}>
            Nešto je pošlo po zlu sa registracijom. Pokušajte ponovo od početka.
          </Text>
          <Button label="Nazad na početak" onPress={() => navigation.navigate('Welcome')} />
        </View>
      </SafeAreaView>
    );
  }

  const isPro = result.subscription.package.is_per_apartment;
  const status = result.status;

  const copy = {
    aktivna: {
      chip: 'Pretplata je aktivna',
      headline: 'Od danas imate\nkome prijaviti kvar.',
      body: `Nema čekanja. Prvu prijavu možete poslati odmah. Račun smo poslali na ${draft.email}.`,
      planeEmber: true,
    },
    cekanje_uplate: {
      chip: 'Čeka se uplata',
      headline: 'Uplatnicu smo\nposlali na mejl.',
      body:
        draft.paymentMethod === 'kartica'
          ? `Plaćanje karticom još nije završeno. Pretplata se aktivira čim uplata legne. Račun smo poslali na ${draft.email}.`
          : `Pretplata se aktivira čim uplata legne. Uplatnicu i račun smo poslali na ${draft.email}.`,
      planeEmber: false,
    },
    ponuda: {
      chip: 'Zahtjev poslan',
      headline: 'Vaš zahtjev ide\ndispečeru.',
      body: 'Za 10 i više stanova pripremamo poseban dogovor. Dispečer će Vas kontaktirati kroz aplikaciju.',
      planeEmber: false,
    },
  }[status];

  const endsAt = formatDate(result.subscription.ends_at);

  const rows: Array<{ k: string; v: string }> = [
    { k: 'Paket', v: result.subscription.package.name },
    { k: 'Uključeni izlasci', v: String(result.subscription.remaining_visits) },
    { k: 'Godišnji pregled', v: String(result.subscription.remaining_inspections) },
  ];
  if (isPro) {
    rows.push({ k: 'Stanovi', v: String(result.subscription.properties.length) });
  }
  if (endsAt) {
    rows.push({ k: 'Vrijedi do', v: endsAt });
  }
  if (result.invoice) {
    rows.push({ k: 'Broj računa', v: result.invoice.number });
    rows.push({ k: 'Iznos', v: `${result.invoice.total} KM` });
  }

  const handleEnter = async () => {
    await commitSession(result.user, 'klijent', result.token);
    resetDraft();
    // RootNavigator prebacuje na ClientTabs čim token postoji.
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View
        style={[
          styles.plane,
          { backgroundColor: copy.planeEmber ? colors.ember : colors.ink },
        ]}
      >
        <View style={styles.chip}>
          <Text style={styles.chipLabel}>{copy.chip}</Text>
        </View>
        <Text style={styles.headline}>{copy.headline}</Text>
      </View>

      <View style={styles.body}>
        <Text style={styles.bodyText}>{copy.body}</Text>
        <View style={styles.rows}>
          {rows.map((row) => (
            <View key={row.k} style={styles.row}>
              <Text style={styles.rowKey}>{row.k}</Text>
              <Text style={styles.rowValue}>{row.v}</Text>
            </View>
          ))}
        </View>
        <Button
          label="Uđite u aplikaciju"
          onPress={handleEnter}
          style={styles.enterButton}
        />
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  plane: {
    paddingHorizontal: 26,
    paddingTop: 44,
    paddingBottom: 40,
  },
  chip: {
    alignSelf: 'flex-start',
    backgroundColor: colors.ivory,
    paddingHorizontal: 12,
    paddingVertical: 7,
    marginBottom: 20,
  },
  chipLabel: {
    ...typeScale.eyebrow,
    color: colors.ink,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 36,
    lineHeight: 38,
    letterSpacing: -0.5,
    color: colors.ivory,
  },
  body: {
    flex: 1,
    padding: 26,
    gap: spacing.lg,
  },
  bodyText: {
    fontFamily: fontFamily.regular,
    fontSize: 17,
    lineHeight: 25,
    color: colors.ink,
  },
  rows: {
    borderTopWidth: 1,
    borderTopColor: colors.ink,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.sand,
    paddingVertical: 13,
  },
  rowKey: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.bark,
  },
  rowValue: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
    textAlign: 'right',
  },
  enterButton: {
    marginTop: 'auto',
  },
  fallback: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 26,
    gap: spacing.md,
  },
  fallbackText: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.bark,
  },
});

export default PretplataAktivnaScreen;
