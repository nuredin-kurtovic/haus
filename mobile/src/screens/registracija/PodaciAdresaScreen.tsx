/**
 * 05 Podaci i adresa, registracija korak 2 od 3 (design/README.md,
 * prototip data-screen-label="05 Registracija podaci").
 *
 * Grad je select samo aktivnih gradova (GET /cities, docs/API.md). Za
 * HAUS Pro (pkg.is_per_apartment, curl-om potvrđeno polje; NE
 * pkg.slug === 'pro', jer je stvarni slug "haus-pro") umjesto jedne
 * adrese ide stepper broja stanova + kartica po stanu.
 *
 * Napomena: docs/API.md kaže "Pro 2+", ali ne navodi tačan minimum ispod
 * kojeg registracija ne prolazi. Task instrukcija eksplicitno traži da se
 * stepper klampuje na minimum 2 stana (ne 1, kako je uobičajeno za ostale
 * steppere u dizajnu) i da se to javi u UI, pa je 2 tvrdi minimum ovdje.
 *
 * VolumeDiscountTier ključevi (curl-om potvrđeno): min/max/pct, ne
 * min_properties/max_properties/discount_percent kako je prva verzija
 * pretpostavila. Lozinka min 8 karaktera (server vraća 422 "Lozinka mora
 * imati najmanje 8 znakova." ispod toga, curl-om potvrđeno).
 */

import React, { useEffect, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import TextField from '../../components/TextField';
import SelectField from '../../components/SelectField';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useActiveCities } from '../../api/queries';
import { useRegistrationStore, type PropertyDraft } from '../../store/registration';
import { colors, fontFamily, numeric, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';
import type { VolumeDiscountTier } from '../../api/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const MIN_PRO_PROPERTIES = 2;
const QUOTE_THRESHOLD = 10; // docs/API.md: "Pro 2+; 10+ vraća {status: ponuda}".

/**
 * Zod pokriva samo polja zajednička svim paketima. Grad/ulica (Mini/Plus)
 * i lista stanova (Pro) se validiraju ručno u onSubmit, po istom obrascu
 * kao i stanovi: tako izbjegavamo grananje šeme po isPro i sve adresne
 * greške ostaju dosljedne (isti stil kao "Validacija per polje/per stan
 * inline" iz task pravila).
 */
const schema = z.object({
  name: z.string().trim().min(3, 'Upišite ime i prezime.'),
  email: z
    .string()
    .min(1, 'Upišite e-mail.')
    .email('E-mail adresa nije ispravna.'),
  password: z.string().min(8, 'Lozinka mora imati bar 8 karaktera.'),
});

type FormValues = z.infer<typeof schema>;

function tierFor(count: number, tiers?: VolumeDiscountTier[]) {
  return tiers?.find(
    (tier) => count >= tier.min && (tier.max == null || count <= tier.max),
  );
}

function discountText(
  count: number,
  tiers: VolumeDiscountTier[] | undefined,
  unitPrice: number,
) {
  if (count >= QUOTE_THRESHOLD) {
    return 'Cijena po dogovoru. Za 10 i više stanova šaljemo zahtjev za ponudu dispečeru.';
  }
  const tier = tierFor(count, tiers);
  if (tier) {
    const range = tier.max != null ? `${tier.min} do ${tier.max}` : `${tier.min} i više`;
    return `Popust ${tier.pct}% na ${range} stanova.`;
  }
  return `${unitPrice} KM po stanu.`;
}

export function PodaciAdresaScreen() {
  const navigation = useNavigation<Nav>();
  const citiesQuery = useActiveCities();
  const pkg = useRegistrationStore((state) => state.pkg);
  const draft = useRegistrationStore((state) => state);
  const setPersonalAndAddress = useRegistrationStore(
    (state) => state.setPersonalAndAddress,
  );
  const isPro = pkg?.is_per_apartment ?? false;

  useEffect(() => {
    if (!pkg) {
      navigation.replace('IzborPaketa');
    }
  }, [pkg, navigation]);

  const { control, handleSubmit } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: draft.name,
      email: draft.email,
      password: draft.password,
    },
  });

  const [cityId, setCityId] = useState<number | null>(draft.cityId);
  const [street, setStreet] = useState(draft.street);
  const [addressErrors, setAddressErrors] = useState<{
    cityId?: string;
    street?: string;
  }>({});

  const [properties, setProperties] = useState<PropertyDraft[]>(() =>
    draft.properties.length >= MIN_PRO_PROPERTIES
      ? draft.properties
      : Array.from({ length: MIN_PRO_PROPERTIES }, () => ({
          cityId: null,
          street: '',
        })),
  );
  const [propertyErrors, setPropertyErrors] = useState<
    Array<{ cityId?: string; street?: string }>
  >([]);

  const cityOptions = citiesQuery.activeCities.map((city) => ({
    label: city.name,
    value: city.id,
  }));

  const addProperty = () =>
    setProperties((prev) => [...prev, { cityId: null, street: '' }]);

  const removeProperty = () =>
    setProperties((prev) =>
      prev.length <= MIN_PRO_PROPERTIES ? prev : prev.slice(0, -1),
    );

  const updateProperty = (index: number, patch: Partial<PropertyDraft>) =>
    setProperties((prev) =>
      prev.map((property, i) => (i === index ? { ...property, ...patch } : property)),
    );

  const onSubmit = handleSubmit((values) => {
    if (isPro) {
      const errors = properties.map((property) => ({
        cityId: property.cityId == null ? 'Izaberite grad.' : undefined,
        street:
          property.street.trim().length < 4 ? 'Upišite ulicu i broj.' : undefined,
      }));
      setPropertyErrors(errors);
      if (errors.some((error) => error.cityId || error.street)) {
        return;
      }
      setPersonalAndAddress({
        name: values.name,
        email: values.email,
        password: values.password,
        cityId: null,
        street: '',
        properties,
      });
    } else {
      const errors = {
        cityId:
          cityId == null
            ? 'Izaberite grad. U listi su samo gradovi u kojima radimo.'
            : undefined,
        street: street.trim().length < 4 ? 'Upišite ulicu i broj.' : undefined,
      };
      setAddressErrors(errors);
      if (errors.cityId || errors.street) {
        return;
      }
      setPersonalAndAddress({
        name: values.name,
        email: values.email,
        password: values.password,
        cityId,
        street,
        properties: [],
      });
    }
    navigation.navigate('Placanje');
  });

  if (!pkg) {
    return <SafeAreaView style={styles.container} />;
  }

  return (
    <SafeAreaView style={styles.container} edges={['top', 'bottom']}>
      <View style={styles.header}>
        <View style={styles.backRow}>
          <Pressable
            accessibilityRole="button"
            onPress={() => navigation.goBack()}
            style={styles.backButton}
          >
            <Text style={styles.backGlyph}>‹</Text>
          </Pressable>
          <Text style={styles.stepLabel}>Korak 2 od 3</Text>
        </View>
        <View style={styles.progressRow}>
          <ProgressBar total={3} current={2} />
        </View>
        <Text style={styles.headline}>Vaši podaci</Text>
        <Text style={styles.paragraph}>
          Pretplata je vezana za jednu adresu i nije prenosiva.
        </Text>
      </View>

      <ScrollView contentContainerStyle={styles.form} keyboardShouldPersistTaps="handled">
        <Controller
          control={control}
          name="name"
          render={({ field, fieldState }) => (
            <TextField
              label="Ime i prezime"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={fieldState.error?.message}
              autoComplete="name"
            />
          )}
        />
        <Controller
          control={control}
          name="email"
          render={({ field, fieldState }) => (
            <TextField
              label="E-mail"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={fieldState.error?.message}
              keyboardType="email-address"
              autoComplete="email"
            />
          )}
        />
        <Controller
          control={control}
          name="password"
          render={({ field, fieldState }) => (
            <TextField
              label="Lozinka"
              value={field.value}
              onChangeText={field.onChange}
              onBlur={field.onBlur}
              error={fieldState.error?.message}
              secureTextEntry
              autoComplete="password-new"
            />
          )}
        />

        {citiesQuery.isLoading && <QueryLoadingNotice label="Učitavanje gradova..." />}
        {citiesQuery.isError && (
          <QueryErrorNotice
            message="Nije moguće učitati gradove. Provjerite internet vezu."
            onRetry={() => citiesQuery.refetch()}
          />
        )}

        {!isPro && !citiesQuery.isLoading && !citiesQuery.isError && (
          <>
            <SelectField
              label="Grad"
              placeholder="Izaberite grad"
              options={cityOptions}
              value={cityId}
              onSelect={setCityId}
              error={addressErrors.cityId}
            />
            <TextField
              label="Ulica i broj"
              value={street}
              onChangeText={setStreet}
              error={addressErrors.street}
              placeholder="Ulica, broj, sprat"
              autoComplete="street-address"
            />
          </>
        )}

        {isPro && !citiesQuery.isLoading && !citiesQuery.isError && (
          <View style={styles.proSection}>
            <View>
              <Text style={styles.proLabel}>Vaši stanovi</Text>
              <Text style={styles.proHint}>
                HAUS Pro se plaća po stanu. 5 izlazaka i pregled dva puta godišnje
                idu po stanu. Minimum je {MIN_PRO_PROPERTIES} stana za HAUS Pro.
              </Text>
            </View>

            <View style={styles.stepperRow}>
              <View>
                <Text style={styles.stepperTitle}>Broj stanova</Text>
                <Text style={styles.stepperSubtitle}>
                  {discountText(properties.length, pkg.volume_discount_tiers, pkg.price_year)}
                </Text>
              </View>
              <View style={styles.stepperControls}>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="Manje stanova"
                  disabled={properties.length <= MIN_PRO_PROPERTIES}
                  onPress={removeProperty}
                  style={[
                    styles.stepperButton,
                    properties.length <= MIN_PRO_PROPERTIES && styles.stepperButtonDisabled,
                  ]}
                >
                  <Text
                    style={[
                      styles.stepperGlyph,
                      properties.length <= MIN_PRO_PROPERTIES && styles.stepperGlyphDisabled,
                    ]}
                  >
                    −
                  </Text>
                </Pressable>
                <Text style={styles.stepperCount}>{properties.length}</Text>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="Više stanova"
                  onPress={addProperty}
                  style={styles.stepperButton}
                >
                  <Text style={styles.stepperGlyph}>+</Text>
                </Pressable>
              </View>
            </View>

            <View style={styles.propertyList}>
              {properties.map((property, index) => (
                <View key={index} style={styles.propertyCard}>
                  <Text style={styles.propertyIndex}>Stan {index + 1}</Text>
                  <SelectField
                    label={`Grad stana ${index + 1}`}
                    placeholder="Izaberite grad"
                    options={cityOptions}
                    value={property.cityId}
                    onSelect={(value) => updateProperty(index, { cityId: value })}
                    error={propertyErrors[index]?.cityId}
                  />
                  <TextField
                    label={`Adresa stana ${index + 1}`}
                    value={property.street}
                    onChangeText={(text) => updateProperty(index, { street: text })}
                    placeholder="Ulica, broj, sprat"
                    error={propertyErrors[index]?.street}
                  />
                </View>
              ))}
            </View>
          </View>
        )}

        <Button label="Nastavite na plaćanje" onPress={onSubmit} style={styles.submitButton} />
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    paddingHorizontal: 26,
    paddingTop: 22,
  },
  backRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    marginBottom: 18,
  },
  backButton: {
    minHeight: spacing.touchTargetMin,
    minWidth: spacing.touchTargetMin,
    justifyContent: 'center',
    alignItems: 'flex-start',
  },
  backGlyph: {
    fontFamily: fontFamily.semiBold,
    fontSize: 22,
    color: colors.ink,
  },
  stepLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    ...numeric,
  },
  progressRow: {
    marginBottom: 26,
  },
  headline: {
    ...typeScale.screenH2,
    color: colors.ink,
    letterSpacing: -0.4,
    marginBottom: 8,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.bark,
    marginBottom: 24,
  },
  form: {
    paddingHorizontal: 26,
    paddingBottom: 26,
    gap: spacing.fieldGap,
  },
  proSection: {
    gap: 14,
  },
  proLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 6,
  },
  proHint: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 21,
    color: colors.bark,
    ...numeric,
  },
  stepperRow: {
    borderWidth: 1,
    borderColor: colors.ink,
    padding: 14,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
  },
  stepperTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
  },
  stepperSubtitle: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    marginTop: 2,
    ...numeric,
  },
  stepperControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 2,
  },
  stepperButton: {
    width: 46,
    height: 46,
    borderWidth: 1,
    borderColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'transparent',
  },
  stepperButtonDisabled: {
    backgroundColor: colors.sand,
  },
  stepperGlyph: {
    fontFamily: fontFamily.semiBold,
    fontSize: 20,
    color: colors.ink,
  },
  stepperGlyphDisabled: {
    color: colors.bark,
  },
  stepperCount: {
    minWidth: 46,
    textAlign: 'center',
    fontFamily: fontFamily.bold,
    fontSize: 20,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  propertyList: {
    gap: 2,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  propertyCard: {
    backgroundColor: colors.white,
    padding: 14,
    paddingHorizontal: 16,
    gap: 12,
  },
  propertyIndex: {
    ...typeScale.eyebrow,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  submitButton: {
    marginTop: spacing.sm,
  },
});

export default PodaciAdresaScreen;
