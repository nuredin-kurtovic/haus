/**
 * Dispečer: 19 Gradovi (design/README.md, prototip data-screen-label="19
 * Dispecer gradovi"). Lista GET /admin/cities: grad, koordinate (tabular),
 * state chip TAPPABLE (potvrda + PATCH status); forma za dodavanje na dnu
 * (naziv, lat, lng), BiH bbox validacija inline (lat 42 do 46, lng 15 do
 * 20) + serverska 422, novi grad ide u u_pripremi (piše na formi, task
 * zahtjev).
 *
 * Curl-om potvrđeno protiv php artisan serve --port=8008 (treći krug
 * verifikacije): GET vraća `properties_count` samo na listi (AdminCity),
 * POST/PATCH odgovor NEMA to polje (obični CityResource); lat 50 vraća 422
 * "Geografska širina mora biti između 42 i 46, unutar granica BiH." kad
 * zahtjev nosi `Accept: application/json` (bez njega Laravel radi redirect,
 * api/client.ts uvijek šalje taj header).
 *
 * Chip ovdje NIJE StateChip komponenta (ta je za status naloga): grad ima
 * samo dva stanja (aktivan/u_pripremi, CityStatus enum ima samo njih dva,
 * "pauziran" je bio pogrešna pretpostavka u prethodnoj fazi, ispravljeno u
 * api/types.ts), pa je poseban mali chip ovdje, isti vizuelni sistem
 * (ink/ivory za aktivan, sand/bark za u_pripremi) kao web prototip.
 */

import React, { useState } from 'react';
import {
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Button from '../../components/Button';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useAdminCitiesQuery, useCreateCityMutation, useUpdateCityMutation } from '../../api/queries';
import { ApiError } from '../../api/client';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { AdminCity } from '../../api/types';

const LAT_MIN = 42;
const LAT_MAX = 46;
const LNG_MIN = 15;
const LNG_MAX = 20;

function formatCoord(value: number): string {
  return value.toFixed(4);
}

export function GradoviScreen() {
  const citiesQuery = useAdminCitiesQuery();
  const createCity = useCreateCityMutation();
  const updateCity = useUpdateCityMutation();

  const cities = citiesQuery.data ?? [];
  const activeCount = cities.filter((city) => city.status === 'aktivan').length;

  const [name, setName] = useState('');
  const [lat, setLat] = useState('');
  const [lng, setLng] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formNotice, setFormNotice] = useState('');

  const toggleCity = (city: AdminCity) => {
    const nextStatus = city.status === 'aktivan' ? 'u_pripremi' : 'aktivan';
    Alert.alert(
      city.name,
      nextStatus === 'aktivan'
        ? 'Aktivirajte ovaj grad? Postaje dostupan za nove pretplate.'
        : 'Vratite ovaj grad u pripremu? Neće se pojavljivati pri registraciji.',
      [
        { text: 'Otkažite', style: 'cancel' },
        {
          text: 'Potvrdite',
          onPress: async () => {
            try {
              await updateCity.mutateAsync({ cityId: city.id, payload: { status: nextStatus } });
            } catch (error) {
              const message = error instanceof ApiError ? error.message : 'Nije moguće promijeniti stanje.';
              Alert.alert('Greška', message);
            }
          },
        },
      ],
    );
  };

  const validate = (): boolean => {
    const nextErrors: Record<string, string> = {};
    if (name.trim().length === 0) {
      nextErrors.name = 'Upišite naziv grada.';
    }
    const latValue = Number.parseFloat(lat.replace(',', '.'));
    const lngValue = Number.parseFloat(lng.replace(',', '.'));
    if (Number.isNaN(latValue)) {
      nextErrors.lat = 'Upišite geografsku širinu.';
    } else if (latValue < LAT_MIN || latValue > LAT_MAX) {
      nextErrors.lat = `Geografska širina mora biti između ${LAT_MIN} i ${LAT_MAX}, unutar granica BiH.`;
    }
    if (Number.isNaN(lngValue)) {
      nextErrors.lng = 'Upišite geografsku dužinu.';
    } else if (lngValue < LNG_MIN || lngValue > LNG_MAX) {
      nextErrors.lng = `Geografska dužina mora biti između ${LNG_MIN} i ${LNG_MAX}, unutar granica BiH.`;
    }
    setFieldErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmit = async () => {
    setFormNotice('');
    if (!validate()) {
      return;
    }
    try {
      const response = await createCity.mutateAsync({
        name: name.trim(),
        lat: Number.parseFloat(lat.replace(',', '.')),
        lng: Number.parseFloat(lng.replace(',', '.')),
      });
      setFormNotice(response.message);
      setName('');
      setLat('');
      setLng('');
      setFieldErrors({});
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        const nextErrors: Record<string, string> = {};
        Object.entries(error.errors).forEach(([field, messages]) => {
          nextErrors[field] = messages[0];
        });
        setFieldErrors(nextErrors);
      } else {
        setFormNotice('Nije moguće dodati grad. Pokušajte ponovo.');
      }
    }
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={citiesQuery.isFetching} onRefresh={() => citiesQuery.refetch()} />
        }
      >
      <View style={styles.header}>
        <Text style={styles.headline}>Gradovi</Text>
        <Text style={styles.meta}>
          {cities.length} {cities.length === 1 ? 'grad' : 'gradova'}, {activeCount} aktivnih
        </Text>
      </View>

      {citiesQuery.isLoading && <QueryLoadingNotice label="Učitavanje gradova..." />}
      {citiesQuery.isError && cities.length === 0 && (
        <QueryErrorNotice onRetry={() => citiesQuery.refetch()} />
      )}

      <View style={styles.list}>
        {cities.map((city) => {
          const isActive = city.status === 'aktivan';
          return (
            <View key={city.id} style={styles.row}>
              <View style={styles.rowText}>
                <Text style={styles.cityName}>{city.name}</Text>
                <Text style={styles.cityCoords}>
                  {formatCoord(city.lat)}, {formatCoord(city.lng)}
                  {isActive ? ` · ${city.properties_count} adresa` : ' · nije dostupan za registraciju'}
                </Text>
              </View>
              <Pressable
                accessibilityRole="button"
                onPress={() => toggleCity(city)}
                style={[styles.chip, isActive ? styles.chipActive : styles.chipPending]}
              >
                <Text style={[styles.chipLabel, isActive ? styles.chipLabelActive : styles.chipLabelPending]}>
                  {isActive ? 'Aktivan' : 'U pripremi'}
                </Text>
              </Pressable>
            </View>
          );
        })}
      </View>

      <View style={styles.form}>
        <View style={styles.formHeader}>
          <Text style={styles.formHeaderLabel}>Dodajte grad</Text>
        </View>
        <View style={styles.formBody}>
          <View style={styles.field}>
            <TextInput
              value={name}
              onChangeText={setName}
              placeholder="Ime grada, npr. Brčko"
              placeholderTextColor={colors.grey}
              style={[styles.input, !!fieldErrors.name && styles.inputError]}
            />
            {!!fieldErrors.name && <Text style={styles.fieldError}>{fieldErrors.name}</Text>}
          </View>
          <View style={styles.fieldRow}>
            <View style={styles.fieldHalf}>
              <TextInput
                value={lat}
                onChangeText={setLat}
                placeholder="Lat, npr. 44.8"
                placeholderTextColor={colors.grey}
                keyboardType="numbers-and-punctuation"
                style={[styles.input, !!fieldErrors.lat && styles.inputError]}
              />
              {!!fieldErrors.lat && <Text style={styles.fieldError}>{fieldErrors.lat}</Text>}
            </View>
            <View style={styles.fieldHalf}>
              <TextInput
                value={lng}
                onChangeText={setLng}
                placeholder="Lng, npr. 17.2"
                placeholderTextColor={colors.grey}
                keyboardType="numbers-and-punctuation"
                style={[styles.input, !!fieldErrors.lng && styles.inputError]}
              />
              {!!fieldErrors.lng && <Text style={styles.fieldError}>{fieldErrors.lng}</Text>}
            </View>
          </View>

          {!!formNotice && <Text style={styles.formNotice}>{formNotice}</Text>}

          <Button
            label={createCity.isPending ? 'Dodajemo...' : 'Dodajte'}
            disabled={createCity.isPending}
            onPress={handleSubmit}
          />
          <Text style={styles.formFooterNote}>
            Novi grad ide u stanje u pripremi. Aktivirajte ga tek kad u njemu imate majstora koji
            može držati rok iz paketa.
          </Text>
        </View>
      </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  content: {
    paddingBottom: 40,
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
    marginBottom: 4,
  },
  meta: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  list: {
    gap: 1,
    backgroundColor: colors.sand,
  },
  row: {
    backgroundColor: colors.white,
    paddingHorizontal: 22,
    paddingVertical: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  rowText: {
    flex: 1,
    gap: 4,
  },
  cityName: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    color: colors.ink,
  },
  cityCoords: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  chip: {
    borderWidth: 1,
    paddingHorizontal: 11,
    minHeight: 40,
    justifyContent: 'center',
  },
  chipActive: {
    borderColor: colors.ink,
    backgroundColor: colors.ink,
  },
  chipPending: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  chipLabel: {
    fontFamily: fontFamily.semiBold,
    fontSize: 12,
  },
  chipLabelActive: {
    color: colors.ivory,
  },
  chipLabelPending: {
    color: colors.bark,
  },
  form: {
    borderWidth: 1,
    borderColor: colors.ink,
    margin: 22,
  },
  formHeader: {
    backgroundColor: colors.ink,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  formHeaderLabel: {
    ...typeScale.eyebrow,
    color: colors.sand,
  },
  formBody: {
    padding: 16,
    gap: 14,
  },
  field: {
    gap: 6,
  },
  fieldRow: {
    flexDirection: 'row',
    gap: 12,
  },
  fieldHalf: {
    flex: 1,
    gap: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    padding: 14,
    minHeight: spacing.primaryButtonHeight,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  inputError: {
    borderColor: colors.error,
  },
  fieldError: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.error,
  },
  formNotice: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 20,
    color: colors.ink,
  },
  formFooterNote: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    lineHeight: 19,
    color: colors.bark,
  },
});

export default GradoviScreen;
