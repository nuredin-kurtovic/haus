/**
 * 09 Prijavi kvar (design/README.md "Screens: mobile", prototip
 * data-screen-label="09 Prijavi kvar"). Tri koraka, jedno pitanje po
 * ekranu, ProgressBar.
 *
 * Kategorije dolaze sa GET /client/price-list (CLAUDE.md: cjenovnik je
 * podatak iz baze, nikad hardkodiran), NE iz prototipove hardkodirane
 * liste ['Vodoinstalacije','Elektroinstalacije',...]. Zadnja tile "Ne znam
 * kako se zove" je sintetička (ne dolazi sa servera): task odluka je da se
 * takva prijava šalje kao kategorija "Sitni poslovi" (pronađena PO IMENU u
 * istoj listi, ne hardkodiran ID) sa prefiksom "Ne znam kako se zove
 * kvar. " ispred klijentovog opisa, jer server nema posebnu "nepoznato"
 * kategoriju (StoreJobRequest traži postojeći price_category_id).
 * Dokumentovano i u finalnom izvještaju.
 *
 * Pro klijent sa više adresa: izbor adrese ide PRIJE koraka 1, van
 * "Korak X od 3" brojanja (docs traži izbor iz /client/subscription
 * properties, ne iz registracionog drafta). Mini/Plus i Pro sa tačno
 * jednom adresom preskaču ovaj korak (adresa se uzima automatski,
 * StoreJobRequest::stan()).
 *
 * preferred_window je slobodan string na serveru (StoreJobRequest.php:
 * `nullable|string|max:255`, nema enum validaciju), pa četiri opcije ovdje
 * su UI konvencija, ne server ugovor: "prijepodne 08–12",
 * "poslijepodne 12–16", "kasno 16–18", "svejedno" (en dash u rasponu je
 * brand pravilo, design/README.md "No em dashes... En dashes in numeric
 * ranges are correct").
 *
 * Foto je opciono, react-native-image-picker (kamera ili galerija preko
 * Alert.alert izbora). NAPOMENA (van scope-a mobile/src, ali bitno za
 * verifikaciju): native dozvole (NSCameraUsageDescription/
 * NSPhotoLibraryUsageDescription na iOS, CAMERA na Androidu) NISU još
 * dodane u ios/android projekte (provjereno grep-om, nema ih), a ovaj
 * zadatak eksplicitno ne dira native projekte. Dio "Kamera" će vjerovatno
 * pucati na pravom uređaju dok neko ne doda te unose; "Galerija" radi bez
 * posebne dozvole na novijim OS verzijama. Otvoreno pitanje, vidi izvještaj.
 */

import React, { useMemo, useState } from 'react';
import {
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import SelectField from '../../components/SelectField';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import {
  useClientPriceListQuery,
  useClientSubscriptionQuery,
  useCreateJobMutation,
} from '../../api/queries';
import { ApiError } from '../../api/client';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { ClientTabParamList, PrijaviStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<PrijaviStackParamList, 'PrijaviKvar'>;

const UNKNOWN_CATEGORY_LABEL = 'Ne znam kako se zove';
const UNKNOWN_CATEGORY_FALLBACK_NAME = 'Sitni poslovi';
const UNKNOWN_DESCRIPTION_PREFIX = 'Ne znam kako se zove kvar. ';

const TERMIN_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'prijepodne 08–12', label: 'Prijepodne, 08–12' },
  { value: 'poslijepodne 12–16', label: 'Poslijepodne, 12–16' },
  { value: 'kasno 16–18', label: 'Kasno, 16–18' },
  { value: 'svejedno', label: 'Svejedno' },
];

type WizardStep = 'adresa' | 1 | 2 | 3;

interface PhotoDraft {
  uri: string;
  type: string;
  fileName: string;
}

export function PrijaviScreen() {
  const navigation = useNavigation<Nav>();
  const subscriptionQuery = useClientSubscriptionQuery();
  const priceListQuery = useClientPriceListQuery();
  const createJob = useCreateJobMutation();

  const subscription = subscriptionQuery.data;
  const properties = subscription?.properties ?? [];
  const needsAddressStep = properties.length > 1;

  const [step, setStep] = useState<WizardStep>(needsAddressStep ? 'adresa' : 1);
  const [propertyId, setPropertyId] = useState<number | null>(
    properties.length === 1 ? properties[0].id : null,
  );
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [categoryLabel, setCategoryLabel] = useState('');
  const [isUnknownCategory, setIsUnknownCategory] = useState(false);
  const [description, setDescription] = useState('');
  const [photo, setPhoto] = useState<PhotoDraft | null>(null);
  const [isEmergency, setIsEmergency] = useState(false);
  const [preferredWindow, setPreferredWindow] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState('');

  const categories = priceListQuery.data?.data ?? [];
  const fallbackCategory = categories.find(
    (category) => category.name === UNKNOWN_CATEGORY_FALLBACK_NAME,
  );

  const categoryTiles = useMemo(
    () => [
      ...categories.map((category) => ({ id: category.id, label: category.name, isUnknown: false })),
      { id: fallbackCategory?.id ?? null, label: UNKNOWN_CATEGORY_LABEL, isUnknown: true },
    ],
    [categories, fallbackCategory],
  );

  const pkg = subscription?.package;
  const emergencyHours = pkg?.emergency_deadline_hours;
  const standardHours = pkg?.deadline_hours;
  const rokTekst = isEmergency
    ? `Hitni rok je ${emergencyHours ?? '?'} sati, bez doplate.`
    : `Vaš rok je ${standardHours ?? '?'} sati u radnim danima.`;

  const totalVisits = subscription
    ? (pkg?.is_per_apartment ? (pkg?.visits_per_year ?? 0) * properties.length : pkg?.visits_per_year ?? 0)
    : 0;
  const remainingVisits = properties.reduce((sum, property) => sum + property.remaining_visits, 0);

  const pickPhoto = () => {
    Alert.alert('Fotografija', 'Odaberite izvor fotografije.', [
      { text: 'Kamera', onPress: () => runPicker('camera') },
      { text: 'Galerija', onPress: () => runPicker('library') },
      { text: 'Otkažite', style: 'cancel' },
    ]);
  };

  const runPicker = async (source: 'camera' | 'library') => {
    try {
      const response =
        source === 'camera'
          ? await launchCamera({ mediaType: 'photo', quality: 0.8 })
          : await launchImageLibrary({ mediaType: 'photo', quality: 0.8 });
      if (response.didCancel || response.errorCode) {
        return;
      }
      const asset = response.assets?.[0];
      if (asset?.uri) {
        setPhoto({
          uri: asset.uri,
          type: asset.type ?? 'image/jpeg',
          fileName: asset.fileName ?? 'kvar.jpg',
        });
      }
    } catch {
      Alert.alert('Fotografija', 'Nije moguće otvoriti kameru ili galeriju.');
    }
  };

  const goToPocetna = () =>
    navigation
      .getParent<BottomTabNavigationProp<ClientTabParamList>>()
      ?.navigate('Pocetna', undefined);

  const goBack = () => {
    if (step === 'adresa') {
      goToPocetna();
      return;
    }
    if (step === 1) {
      if (needsAddressStep) {
        setStep('adresa');
      } else {
        goToPocetna();
      }
      return;
    }
    setStep((current) => ((current as number) - 1) as WizardStep);
  };

  const handleSubmit = async () => {
    if (!categoryId || !preferredWindow) {
      return;
    }
    setServerError('');
    setSubmitting(true);
    try {
      const finalDescription = isUnknownCategory
        ? `${UNKNOWN_DESCRIPTION_PREFIX}${description.trim()}`
        : description.trim();

      const formData = new FormData();
      formData.append('price_category_id', String(categoryId));
      formData.append('description', finalDescription);
      formData.append('is_emergency', isEmergency ? '1' : '0');
      formData.append('preferred_window', preferredWindow);
      if (propertyId != null) {
        formData.append('subscription_property_id', String(propertyId));
      }
      if (photo) {
        formData.append('photo', {
          uri: photo.uri,
          type: photo.type,
          name: photo.fileName,
        });
      }

      const response = await createJob.mutateAsync(formData);

      navigation.replace('PrijavaPrimljena', {
        jobId: response.job.id,
        number: response.job.number,
        deadlineAt: response.job.deadline_at,
        category: isUnknownCategory ? UNKNOWN_CATEGORY_FALLBACK_NAME : categoryLabel,
        isEmergency,
        preferredWindow,
        remainingVisits,
        totalVisits,
      });
    } catch (error) {
      if (error instanceof ApiError) {
        setServerError(error.message);
      } else {
        setServerError('Nema veze sa serverom. Provjerite internet i pokušajte ponovo.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const propertyOptions = properties.map((property) => ({
    label: `${property.street}, ${property.city}`,
    value: property.id,
  }));

  const isLoadingPrereqs = subscriptionQuery.isLoading || priceListQuery.isLoading;
  const hasPrereqError =
    (subscriptionQuery.isError && !subscription) || (priceListQuery.isError && categories.length === 0);

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View style={styles.backRow}>
          <Pressable
            accessibilityRole="button"
            onPress={goBack}
            style={styles.backButton}
          >
            <Text style={styles.backGlyph}>‹</Text>
          </Pressable>
          <Text style={styles.stepLabel}>
            {step === 'adresa' ? 'Adresa' : `Korak ${step} od 3`}
          </Text>
        </View>
        {step !== 'adresa' && (
          <View style={styles.progressRow}>
            <ProgressBar total={3} current={step} />
          </View>
        )}
      </View>

      {isLoadingPrereqs && <QueryLoadingNotice label="Učitavanje..." />}
      {hasPrereqError && (
        <QueryErrorNotice
          message="Nije moguće učitati podatke potrebne za prijavu. Provjerite internet vezu."
          onRetry={() => {
            subscriptionQuery.refetch();
            priceListQuery.refetch();
          }}
        />
      )}

      {!isLoadingPrereqs && !hasPrereqError && (
        <ScrollView
          contentContainerStyle={styles.scroll}
          keyboardShouldPersistTaps="handled"
        >
          {step === 'adresa' && (
            <View style={styles.stepBlock}>
              <Text style={styles.headline}>Na koju adresu?</Text>
              <Text style={styles.paragraph}>
                Vaša pretplata pokriva više adresa. Izaberite na koju se ovo odnosi.
              </Text>
              <SelectField
                label="Adresa"
                placeholder="Izaberite adresu"
                options={propertyOptions}
                value={propertyId}
                onSelect={setPropertyId}
              />
              <Button
                label="Nastavite"
                disabled={propertyId == null}
                onPress={() => setStep(1)}
                style={styles.forwardButton}
              />
            </View>
          )}

          {step === 1 && (
            <View style={styles.stepBlock}>
              <Text style={styles.headline}>Šta se pokvarilo?</Text>
              <Text style={styles.paragraph}>
                Ako ne znate kako se zove, zadnja stavka je za to.
              </Text>
              <View style={styles.categoryGrid}>
                {categoryTiles.map((tile) => {
                  const isSelected = tile.isUnknown ? isUnknownCategory : categoryId === tile.id;
                  return (
                    <Pressable
                      key={tile.isUnknown ? 'unknown' : tile.id}
                      accessibilityRole="button"
                      accessibilityState={{ selected: isSelected }}
                      disabled={tile.id == null}
                      onPress={() => {
                        setIsUnknownCategory(tile.isUnknown);
                        setCategoryId(tile.id);
                        setCategoryLabel(tile.label);
                      }}
                      style={[
                        styles.categoryTile,
                        isSelected ? styles.categoryTileSelected : styles.categoryTileDefault,
                      ]}
                    >
                      <Text
                        style={[
                          styles.categoryTileLabel,
                          isSelected && styles.categoryTileLabelSelected,
                        ]}
                      >
                        {tile.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>
              <Button
                label="Dalje"
                disabled={!categoryId}
                onPress={() => setStep(2)}
                style={styles.forwardButton}
              />
            </View>
          )}

          {step === 2 && (
            <View style={styles.stepBlock}>
              <Text style={styles.headline}>Opišite kvar</Text>
              <Text style={styles.paragraph}>
                Kratko je dovoljno. Dispečer pita ako mu nešto nije jasno.
              </Text>
              <TextInput
                multiline
                numberOfLines={4}
                value={description}
                onChangeText={setDescription}
                placeholder="npr. Curi ispod sudopere, kaplje na pod."
                placeholderTextColor={colors.grey}
                style={styles.textarea}
              />

              <View style={styles.photoSection}>
                <Text style={styles.photoLabel}>Fotografija</Text>
                <Pressable onPress={pickPhoto} style={styles.photoBox}>
                  {photo ? (
                    <Image source={{ uri: photo.uri }} style={styles.photoPreview} resizeMode="cover" />
                  ) : (
                    <Text style={styles.photoPlaceholder}>
                      Fotografija kvara, slikana telefonom
                    </Text>
                  )}
                </Pressable>
                {!!photo && (
                  <Pressable onPress={() => setPhoto(null)}>
                    <Text style={styles.photoRemove}>Uklonite fotografiju</Text>
                  </Pressable>
                )}
              </View>

              <Pressable
                accessibilityRole="checkbox"
                accessibilityState={{ checked: isEmergency }}
                onPress={() => setIsEmergency((value) => !value)}
                style={[
                  styles.hitnoContainer,
                  isEmergency ? styles.hitnoContainerActive : styles.hitnoContainerDefault,
                ]}
              >
                <View style={[styles.checkbox, isEmergency && styles.checkboxActive]}>
                  {isEmergency && <Text style={styles.checkboxMark}>✓</Text>}
                </View>
                <View style={styles.hitnoText}>
                  <Text style={styles.hitnoTitle}>Hitno: poplava, struja, plin</Text>
                  <Text style={styles.hitnoSubtitle}>
                    Rok je {emergencyHours ?? '?'} sati, bez doplate. Zatvorite ventil ako možete.
                  </Text>
                </View>
              </Pressable>

              <Button
                label="Dalje"
                disabled={description.trim().length < 10}
                onPress={() => setStep(3)}
                style={styles.forwardButton}
              />
            </View>
          )}

          {step === 3 && (
            <View style={styles.stepBlock}>
              <Text style={styles.headline}>Kad vam odgovara?</Text>
              <Text style={styles.paragraph}>{rokTekst}</Text>
              <View style={styles.terminList}>
                {TERMIN_OPTIONS.map((option) => {
                  const isSelected = preferredWindow === option.value;
                  return (
                    <Pressable
                      key={option.value}
                      accessibilityRole="button"
                      accessibilityState={{ selected: isSelected }}
                      onPress={() => setPreferredWindow(option.value)}
                      style={[
                        styles.terminTile,
                        isSelected ? styles.terminTileSelected : styles.terminTileDefault,
                      ]}
                    >
                      <Text
                        style={[
                          styles.terminTileLabel,
                          isSelected && styles.terminTileLabelSelected,
                        ]}
                      >
                        {option.label}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>

              {!!serverError && <Text style={styles.serverError}>{serverError}</Text>}

              <Button
                label={submitting ? 'Šaljemo...' : 'Prijavite kvar'}
                disabled={!preferredWindow || submitting}
                onPress={handleSubmit}
                style={styles.forwardButton}
              />
            </View>
          )}
        </ScrollView>
      )}
    </View>
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
  },
  backRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 16,
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
  },
  progressRow: {
    marginBottom: 24,
  },
  scroll: {
    paddingHorizontal: 22,
    paddingBottom: 26,
    flexGrow: 1,
  },
  stepBlock: {
    flex: 1,
    gap: 18,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 30,
    lineHeight: 33,
    letterSpacing: -0.4,
    color: colors.ink,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
  },
  categoryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  categoryTile: {
    width: '48.5%',
    minHeight: 64,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 16,
    justifyContent: 'center',
  },
  categoryTileDefault: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  categoryTileSelected: {
    borderColor: colors.ink,
    backgroundColor: colors.ivory,
  },
  categoryTileLabel: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 20,
    color: colors.ink,
  },
  categoryTileLabelSelected: {
    fontFamily: fontFamily.semiBold,
  },
  forwardButton: {
    marginTop: 'auto',
  },
  textarea: {
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
  photoSection: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 14,
    gap: 10,
  },
  photoLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
  },
  photoBox: {
    height: 150,
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  photoPreview: {
    width: '100%',
    height: '100%',
  },
  photoPlaceholder: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    color: colors.bark,
    textAlign: 'center',
    paddingHorizontal: 20,
  },
  photoRemove: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.error,
  },
  hitnoContainer: {
    flexDirection: 'row',
    gap: 12,
    borderWidth: 1,
    padding: 16,
    alignItems: 'flex-start',
  },
  hitnoContainerDefault: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  hitnoContainerActive: {
    borderColor: colors.ember,
    backgroundColor: colors.ivory,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderWidth: 1,
    borderColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  checkboxActive: {
    backgroundColor: colors.ember,
    borderColor: colors.ember,
  },
  checkboxMark: {
    color: colors.ivory,
    fontSize: 13,
    fontFamily: fontFamily.bold,
  },
  hitnoText: {
    flex: 1,
    gap: 3,
  },
  hitnoTitle: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
  },
  hitnoSubtitle: {
    fontFamily: fontFamily.regular,
    fontSize: 13,
    lineHeight: 19,
    color: colors.bark,
  },
  terminList: {
    gap: 8,
  },
  terminTile: {
    borderWidth: 1,
    minHeight: 52,
    paddingHorizontal: 16,
    justifyContent: 'center',
  },
  terminTileDefault: {
    borderColor: colors.sand,
    backgroundColor: colors.white,
  },
  terminTileSelected: {
    borderColor: colors.ink,
    backgroundColor: colors.ivory,
  },
  terminTileLabel: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  terminTileLabelSelected: {
    fontFamily: fontFamily.semiBold,
  },
  serverError: {
    borderWidth: 1,
    borderColor: colors.error,
    backgroundColor: colors.white,
    padding: 14,
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 20,
    color: colors.error,
  },
});

export default PrijaviScreen;
