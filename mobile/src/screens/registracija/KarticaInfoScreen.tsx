/**
 * Ekran nakon kartičnog plaćanja na registraciji (ekran 06 -> ovaj -> 07).
 * Pravi Monri WebView/3DS tok još ne postoji (design/README.md "Payments
 * (Monri)": tokenizacija, webhook itd. dolaze u fazi integracije
 * plaćanja). Dok se čeka, ovaj ekran nudi lokalnu simulaciju ishoda preko
 * POST /api/v1/dev/fake-payment (čitanjem FakePaymentController.php
 * potvrđeno: `{reference, outcome: approved|declined}` ->
 * `{processed, subscription_status}`, dostupno SAMO lokalno kad je
 * `services.haus.payment_gateway = fake` i `app.debug = true`).
 * TODO(monri): zamijeniti ovaj ekran pravim WebView 3DS tokom kad Monri
 * integracija stigne; simulacija ostaje samo za lokalni razvoj dotad.
 *
 * `reference` se izvlači iz `payment.redirect_url` (POST /auth/register
 * odgovor), tačnije iz query parametra `ref` (čitanjem FakeGateway.php
 * potvrđeno: redirect je `{APP_URL}/placanje/simulacija?ref={reference}`,
 * NE `reference` kako bi se moglo pretpostaviti iz imena polja).
 *
 * Redoslijed radnji na uspjeh je NAMJERNO drugačiji od doslovnog "refetch
 * /me, commitSession, navigiraj": commitSession upisuje token u
 * store/keychain, a RootNavigator ODMAH prebacuje CIJELI stek na
 * ClientTabs čim token postoji (vidi RootNavigator.tsx), što bi ugasilo
 * ovaj ekran i ekran 07 prije nego što se stigne navigirati/prikazati
 * stanje. Ista napomena postoji u PretplataAktivnaScreen.tsx i
 * PlacanjeScreen.tsx (lead review, avgust 2026). Zato ovdje: (1) GET /me
 * SA EKSPLICITNIM tokenom iz register odgovora (token još nije u
 * keychain-u, obično ga get() ne bi imao odakle pročitati; vidi
 * `tokenOverride` u api/client.ts), (2) upiši svježe stanje pretplate u
 * registrationStore, (3) navigiraj na PretplataAktivna, koji NEPROMIJENJENO
 * zove commitSession na svoje dugme "Uđite u aplikaciju".
 */

import React, { useMemo, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Button from '../../components/Button';
import { get } from '../../api/client';
import { useFakePaymentMutation } from '../../api/queries';
import { useRegistrationStore } from '../../store/registration';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';
import type { MeResponse } from '../../api/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

function extractRefParam(url: string): string | null {
  const match = url.match(/[?&]ref=([^&]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

export function KarticaInfoScreen() {
  const navigation = useNavigation<Nav>();
  const fakePayment = useFakePaymentMutation();
  const draft = useRegistrationStore((state) => state);
  const setResult = useRegistrationStore((state) => state.setResult);

  const [message, setMessage] = useState('');

  const reference = useMemo(() => {
    const redirectUrl = draft.result?.payment?.redirect_url;
    return redirectUrl ? extractRefParam(redirectUrl) : null;
  }, [draft.result]);

  const handleOutcome = async (outcome: 'approved' | 'declined') => {
    if (!reference || !draft.result) {
      return;
    }
    setMessage('');
    try {
      const response = await fakePayment.mutateAsync({ reference, outcome });

      if (outcome === 'declined' || !response.processed) {
        setMessage(
          outcome === 'declined'
            ? 'Uplata je odbijena. Pokušajte ponovo ili izaberite uplatnicu na mejl.'
            : 'Ova uplata je već obrađena.',
        );
        return;
      }

      // GET /me sa eksplicitnim tokenom: registracioni token još nije u
      // keychain-u (commitSession se zove tek na ekranu 07).
      const me = await get<MeResponse>('/me', undefined, {
        tokenOverride: draft.result.token,
      });

      setResult({
        ...draft.result,
        status: me.subscription?.status ?? draft.result.status,
        subscription: {
          ...draft.result.subscription,
          status: me.subscription?.status ?? draft.result.subscription.status,
          starts_at: me.subscription?.starts_at ?? draft.result.subscription.starts_at,
          ends_at: me.subscription?.ends_at ?? draft.result.subscription.ends_at,
          remaining_visits: me.subscription?.remaining_visits ?? draft.result.subscription.remaining_visits,
          remaining_inspections:
            me.subscription?.remaining_inspections ?? draft.result.subscription.remaining_inspections,
          free_interventions:
            me.subscription?.free_interventions ?? draft.result.subscription.free_interventions,
        },
      });

      navigation.replace('PretplataAktivna');
    } catch {
      setMessage('Nije moguće obraditi simulaciju. Provjerite internet i pokušajte ponovo.');
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.body}>
        <Text style={styles.headline}>Plaćanje karticom</Text>
        <Text style={styles.paragraph}>
          Plaćanje karticom se završava u web pretraživaču. Taj korak dolazi u sljedećoj fazi
          (Monri 3-D Secure). Za sada, simulirajte ishod da testirate tok dalje.
        </Text>

        {!reference && (
          <Text style={styles.paragraph}>
            Nema referencu uplate iz registracije, simulacija nije moguća sa ovog ekrana.
          </Text>
        )}

        {!!reference && (
          <View style={styles.buttons}>
            <Button
              label="Simuliraj uspješnu uplatu"
              onPress={() => handleOutcome('approved')}
              disabled={fakePayment.isPending}
            />
            <Button
              label="Simuliraj neuspješnu uplatu"
              variant="ghost"
              onPress={() => handleOutcome('declined')}
              disabled={fakePayment.isPending}
            />
          </View>
        )}

        {!!message && <Text style={styles.message}>{message}</Text>}
      </View>
      <View style={styles.footer}>
        <Button
          label="Nastavite bez simulacije"
          variant="ghost"
          onPress={() => navigation.replace('PretplataAktivna')}
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
  body: {
    flex: 1,
    paddingHorizontal: 26,
    paddingTop: 44,
    gap: spacing.md,
  },
  headline: {
    ...typeScale.screenH2,
    color: colors.ink,
    letterSpacing: -0.4,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 17,
    lineHeight: 25,
    color: colors.bark,
  },
  buttons: {
    gap: 12,
    marginTop: spacing.md,
  },
  message: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 20,
    color: colors.bark,
  },
  footer: {
    paddingHorizontal: 26,
    paddingBottom: 28,
  },
});

export default KarticaInfoScreen;
