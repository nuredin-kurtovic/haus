/**
 * Serviser: "Završetak naloga" (task A.3). Pet koraka, jedno pitanje po
 * ekranu u duhu (isti obrazac kao PrijaviScreen wizard): nalaz, stavke
 * rada, materijal, fotografije prije, fotografije poslije. Multipart POST
 * /technician/jobs/{id}/complete (curl-om potvrđeno protiv php artisan
 * serve --port=8008: CompleteJobRequest prima `items`/`materials` kao JSON
 * tekst unutar multipart, `photos_before[]`/`photos_after[]` kao fajlovi;
 * odgovor nosi `warranty_until` i sažetu fakturu, vidi CompleteJobResponse).
 *
 * Stavke rada: pretraga GET /technician/price-list (bez cijena po paketu,
 * TechnicianPriceItem), qty stepper. Prikazuje se SAMO `base_price`: server
 * računa konačan iznos (popust klijentovog paketa, docs/API.md
 * JobCompletionService), ovaj ekran to eksplicitno kaže i ništa ne
 * računa lokalno (task pravilo, CLAUDE.md "snižene cijene se nikad ne
 * upisuju/računaju na klijentu", isti princip primijenjen i ovdje na
 * majstorovoj strani).
 *
 * Fotografije: kamera ili galerija, pattern iz PrijaviScreen.tsx
 * (Alert.alert izbor izvora); galerija dozvoljava izbor više fotografija
 * odjednom (selectionLimit: 0), kamera dodaje jednu po pozivu.
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
import { useNavigation, useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import Button from '../../components/Button';
import ProgressBar from '../../components/ProgressBar';
import { QueryErrorNotice, QueryLoadingNotice } from '../../components/QueryErrorNotice';
import { useCompleteTechnicianJobMutation, useTechnicianPriceListQuery } from '../../api/queries';
import { ApiError } from '../../api/client';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { TechnicianPriceItem } from '../../api/types';
import type { TechnicianNaloziStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<TechnicianNaloziStackParamList, 'ZavrsetakNaloga'>;
type Route = RouteProp<TechnicianNaloziStackParamList, 'ZavrsetakNaloga'>;

type Step = 1 | 2 | 3 | 4 | 5;

interface SelectedItem {
  price_item_id: number;
  name: string;
  base_price: number;
  qty: number;
}

interface MaterialRow {
  key: string;
  name: string;
  purchasePrice: string;
  qty: string;
}

interface PhotoDraft {
  uri: string;
  type: string;
  fileName: string;
}

let materialKeySeq = 0;
function nextMaterialKey(): string {
  materialKeySeq += 1;
  return `material-${materialKeySeq}`;
}

export function ZavrsetakNalogaScreen() {
  const navigation = useNavigation<Nav>();
  const route = useRoute<Route>();
  const jobId = route.params.jobId;

  const priceListQuery = useTechnicianPriceListQuery();
  const completeJob = useCompleteTechnicianJobMutation();

  const [step, setStep] = useState<Step>(1);
  const [findings, setFindings] = useState('');
  const [search, setSearch] = useState('');
  const [selectedItems, setSelectedItems] = useState<Record<number, SelectedItem>>({});
  const [materials, setMaterials] = useState<MaterialRow[]>([]);
  const [photosBefore, setPhotosBefore] = useState<PhotoDraft[]>([]);
  const [photosAfter, setPhotosAfter] = useState<PhotoDraft[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [serverError, setServerError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const categories = priceListQuery.data?.data ?? [];
  const filteredCategories = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) {
      return categories;
    }
    return categories
      .map((category) => ({
        ...category,
        items: category.items.filter((item) => item.name.toLowerCase().includes(needle)),
      }))
      .filter((category) => category.items.length > 0);
  }, [categories, search]);

  const changeQty = (item: TechnicianPriceItem, delta: number) => {
    setSelectedItems((current) => {
      const existing = current[item.id];
      const nextQty = Math.max(0, (existing?.qty ?? 0) + delta);
      const next = { ...current };
      if (nextQty === 0) {
        delete next[item.id];
      } else {
        next[item.id] = {
          price_item_id: item.id,
          name: item.name,
          base_price: item.base_price,
          qty: nextQty,
        };
      }
      return next;
    });
  };

  const selectedItemsList = Object.values(selectedItems);

  const addMaterialRow = () => {
    setMaterials((rows) => [...rows, { key: nextMaterialKey(), name: '', purchasePrice: '', qty: '1' }]);
  };

  const removeMaterialRow = (key: string) => {
    setMaterials((rows) => rows.filter((row) => row.key !== key));
  };

  const updateMaterialRow = (key: string, patchValue: Partial<MaterialRow>) => {
    setMaterials((rows) => rows.map((row) => (row.key === key ? { ...row, ...patchValue } : row)));
  };

  const pickPhoto = (target: 'before' | 'after') => {
    Alert.alert('Fotografija', 'Odaberite izvor fotografije.', [
      { text: 'Kamera', onPress: () => runPicker('camera', target) },
      { text: 'Galerija', onPress: () => runPicker('library', target) },
      { text: 'Otkažite', style: 'cancel' },
    ]);
  };

  const runPicker = async (source: 'camera' | 'library', target: 'before' | 'after') => {
    try {
      const response =
        source === 'camera'
          ? await launchCamera({ mediaType: 'photo', quality: 0.8 })
          : await launchImageLibrary({ mediaType: 'photo', quality: 0.8, selectionLimit: 0 });
      if (response.didCancel || response.errorCode) {
        return;
      }
      const drafts: PhotoDraft[] = (response.assets ?? [])
        .filter((asset) => !!asset.uri)
        .map((asset) => ({
          uri: asset.uri as string,
          type: asset.type ?? 'image/jpeg',
          fileName: asset.fileName ?? 'nalog.jpg',
        }));
      if (drafts.length === 0) {
        return;
      }
      if (target === 'before') {
        setPhotosBefore((current) => [...current, ...drafts]);
      } else {
        setPhotosAfter((current) => [...current, ...drafts]);
      }
    } catch {
      Alert.alert('Fotografija', 'Nije moguće otvoriti kameru ili galeriju.');
    }
  };

  const removePhoto = (target: 'before' | 'after', index: number) => {
    if (target === 'before') {
      setPhotosBefore((current) => current.filter((_, i) => i !== index));
    } else {
      setPhotosAfter((current) => current.filter((_, i) => i !== index));
    }
  };

  const goBack = () => {
    if (step === 1) {
      navigation.goBack();
      return;
    }
    setStep((current) => ((current - 1) as Step));
  };

  const validMaterials = materials
    .map((row) => ({
      name: row.name.trim(),
      purchasePrice: Number.parseFloat(row.purchasePrice.replace(',', '.')),
      qty: Number.parseFloat(row.qty.replace(',', '.')),
    }))
    .filter((row) => row.name.length > 0 && !Number.isNaN(row.purchasePrice) && !Number.isNaN(row.qty) && row.qty > 0);

  const handleSubmit = async () => {
    setServerError('');
    setFieldErrors({});
    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('findings', findings.trim());
      formData.append(
        'items',
        JSON.stringify(selectedItemsList.map((item) => ({ price_item_id: item.price_item_id, qty: item.qty }))),
      );
      formData.append(
        'materials',
        JSON.stringify(
          validMaterials.map((row) => ({
            name: row.name,
            purchase_price: row.purchasePrice,
            qty: row.qty,
          })),
        ),
      );
      photosBefore.forEach((photo) => {
        formData.append('photos_before[]', { uri: photo.uri, type: photo.type, name: photo.fileName } as never);
      });
      photosAfter.forEach((photo) => {
        formData.append('photos_after[]', { uri: photo.uri, type: photo.type, name: photo.fileName } as never);
      });

      const response = await completeJob.mutateAsync({ jobId, formData });

      navigation.replace('ZavrsetakPotvrda', {
        jobId,
        number: response.data.number,
        warrantyUntil: response.data.warranty_until,
        invoice: response.data.invoice,
      });
    } catch (error) {
      if (error instanceof ApiError) {
        setServerError(error.message);
        setFieldErrors(error.errors ?? {});
      } else {
        setServerError('Nema veze sa serverom. Provjerite internet i pokušajte ponovo.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const stepTitles: Record<Step, string> = {
    1: 'Nalaz',
    2: 'Stavke rada',
    3: 'Materijal',
    4: 'Fotografije prije',
    5: 'Fotografije poslije',
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View style={styles.backRow}>
          <Pressable accessibilityRole="button" onPress={goBack} style={styles.backButton}>
            <Text style={styles.backGlyph}>‹</Text>
          </Pressable>
          <Text style={styles.stepLabel}>Korak {step} od 5 · {stepTitles[step]}</Text>
        </View>
        <View style={styles.progressRow}>
          <ProgressBar total={5} current={step} />
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        {step === 1 && (
          <View style={styles.stepBlock}>
            <Text style={styles.headline}>Šta ste zatekli i šta ste uradili?</Text>
            <Text style={styles.paragraph}>
              Nalaz ide klijentu u izvještaju. Najmanje 10 znakova.
            </Text>
            <TextInput
              multiline
              numberOfLines={6}
              value={findings}
              onChangeText={setFindings}
              placeholder="npr. Zamijenjena baterija sudopere, provjereno curenje, nema više kapanja."
              placeholderTextColor={colors.grey}
              style={styles.textarea}
            />
            <Button
              label="Dalje"
              disabled={findings.trim().length < 10}
              onPress={() => setStep(2)}
              style={styles.forwardButton}
            />
          </View>
        )}

        {step === 2 && (
          <View style={styles.stepBlock}>
            <Text style={styles.headline}>Koje stavke rada ste odradili?</Text>
            <Text style={styles.paragraph}>
              Ovo je osnovna cijena po stavci. Konačan iznos na računu, uz popust klijentovog
              paketa, računa server.
            </Text>

            {priceListQuery.isLoading && <QueryLoadingNotice label="Učitavanje cjenovnika..." />}
            {priceListQuery.isError && categories.length === 0 && (
              <QueryErrorNotice onRetry={() => priceListQuery.refetch()} />
            )}

            {selectedItemsList.length > 0 && (
              <View style={styles.selectedBox}>
                <Text style={styles.sectionLabel}>Dodane stavke</Text>
                {selectedItemsList.map((item) => (
                  <View key={item.price_item_id} style={styles.selectedRow}>
                    <Text style={styles.selectedName}>
                      {item.name} × {item.qty}
                    </Text>
                    <Text style={styles.selectedPrice}>{item.base_price * item.qty} KM</Text>
                  </View>
                ))}
              </View>
            )}

            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Pretraga: baterija, bojler, brava"
              placeholderTextColor={colors.grey}
              style={styles.searchInput}
              autoCapitalize="none"
            />

            <View style={styles.priceList}>
              {filteredCategories.map((category) => (
                <View key={category.id} style={styles.priceCategory}>
                  <Text style={styles.priceCategoryLabel}>{category.name}</Text>
                  {category.items.map((item) => {
                    const qty = selectedItems[item.id]?.qty ?? 0;
                    return (
                      <View key={item.id} style={styles.priceRow}>
                        <View style={styles.priceRowText}>
                          <Text style={styles.priceRowName}>{item.name}</Text>
                          <Text style={styles.priceRowBase}>{item.base_price} KM</Text>
                        </View>
                        <View style={styles.stepper}>
                          <Pressable
                            accessibilityRole="button"
                            disabled={qty === 0}
                            onPress={() => changeQty(item, -1)}
                            style={[styles.stepperButton, qty === 0 && styles.stepperButtonDisabled]}
                          >
                            <Text style={styles.stepperGlyph}>−</Text>
                          </Pressable>
                          <Text style={styles.stepperCount}>{qty}</Text>
                          <Pressable
                            accessibilityRole="button"
                            onPress={() => changeQty(item, 1)}
                            style={styles.stepperButton}
                          >
                            <Text style={styles.stepperGlyph}>+</Text>
                          </Pressable>
                        </View>
                      </View>
                    );
                  })}
                </View>
              ))}
            </View>

            <Button label="Dalje" onPress={() => setStep(3)} style={styles.forwardButton} />
          </View>
        )}

        {step === 3 && (
          <View style={styles.stepBlock}>
            <Text style={styles.headline}>Koji materijal ste utrošili?</Text>
            <Text style={styles.paragraph}>
              Nabavna cijena, ne cijena za klijenta: server dodaje maržu i popust paketa.
              Materijal je opcion, možete preskočiti.
            </Text>

            {materials.map((row) => (
              <View key={row.key} style={styles.materialRow}>
                <TextInput
                  value={row.name}
                  onChangeText={(text) => updateMaterialRow(row.key, { name: text })}
                  placeholder="Naziv materijala"
                  placeholderTextColor={colors.grey}
                  style={styles.materialInputName}
                />
                <View style={styles.materialInlineRow}>
                  <TextInput
                    value={row.purchasePrice}
                    onChangeText={(text) => updateMaterialRow(row.key, { purchasePrice: text })}
                    placeholder="Nabavna cijena"
                    placeholderTextColor={colors.grey}
                    keyboardType="decimal-pad"
                    style={styles.materialInputSmall}
                  />
                  <TextInput
                    value={row.qty}
                    onChangeText={(text) => updateMaterialRow(row.key, { qty: text })}
                    placeholder="Količina"
                    placeholderTextColor={colors.grey}
                    keyboardType="decimal-pad"
                    style={styles.materialInputSmall}
                  />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => removeMaterialRow(row.key)}
                    style={styles.materialRemove}
                  >
                    <Text style={styles.materialRemoveLabel}>Uklonite</Text>
                  </Pressable>
                </View>
              </View>
            ))}

            <Button
              label="Dodajte materijal"
              variant="ghost"
              onPress={addMaterialRow}
              style={styles.addMaterialButton}
            />

            <Button label="Dalje" onPress={() => setStep(4)} style={styles.forwardButton} />
          </View>
        )}

        {step === 4 && (
          <View style={styles.stepBlock}>
            <Text style={styles.headline}>Fotografije prije rada</Text>
            <Text style={styles.paragraph}>Najmanje jedna fotografija, kamera ili galerija.</Text>
            <View style={styles.photoGrid}>
              {photosBefore.map((photo, index) => (
                <View key={`${photo.uri}-${index}`} style={styles.photoThumbWrap}>
                  <Image source={{ uri: photo.uri }} style={styles.photoThumb} resizeMode="cover" />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => removePhoto('before', index)}
                    style={styles.photoRemove}
                  >
                    <Text style={styles.photoRemoveLabel}>×</Text>
                  </Pressable>
                </View>
              ))}
              <Pressable
                accessibilityRole="button"
                onPress={() => pickPhoto('before')}
                style={styles.photoAddTile}
              >
                <Text style={styles.photoAddGlyph}>+</Text>
              </Pressable>
            </View>
            <Button
              label="Dalje"
              disabled={photosBefore.length === 0}
              onPress={() => setStep(5)}
              style={styles.forwardButton}
            />
          </View>
        )}

        {step === 5 && (
          <View style={styles.stepBlock}>
            <Text style={styles.headline}>Fotografije poslije rada</Text>
            <Text style={styles.paragraph}>Najmanje jedna fotografija, kamera ili galerija.</Text>
            <View style={styles.photoGrid}>
              {photosAfter.map((photo, index) => (
                <View key={`${photo.uri}-${index}`} style={styles.photoThumbWrap}>
                  <Image source={{ uri: photo.uri }} style={styles.photoThumb} resizeMode="cover" />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => removePhoto('after', index)}
                    style={styles.photoRemove}
                  >
                    <Text style={styles.photoRemoveLabel}>×</Text>
                  </Pressable>
                </View>
              ))}
              <Pressable
                accessibilityRole="button"
                onPress={() => pickPhoto('after')}
                style={styles.photoAddTile}
              >
                <Text style={styles.photoAddGlyph}>+</Text>
              </Pressable>
            </View>

            {!!serverError && (
              <View style={styles.errorBox}>
                <Text style={styles.errorText}>{serverError}</Text>
                {Object.entries(fieldErrors).map(([field, messages]) => (
                  <Text key={field} style={styles.errorText}>
                    {messages[0]}
                  </Text>
                ))}
              </View>
            )}

            <Button
              label={submitting ? 'Zatvaramo nalog...' : 'Završite nalog'}
              disabled={photosAfter.length === 0 || submitting}
              onPress={handleSubmit}
              style={styles.forwardButton}
            />
          </View>
        )}
      </ScrollView>
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
    paddingBottom: 32,
    flexGrow: 1,
  },
  stepBlock: {
    flex: 1,
    gap: 16,
  },
  headline: {
    fontFamily: fontFamily.bold,
    fontSize: 26,
    lineHeight: 31,
    letterSpacing: -0.3,
    color: colors.ink,
  },
  paragraph: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    lineHeight: 22,
    color: colors.bark,
  },
  textarea: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    padding: 14,
    minHeight: 140,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
    textAlignVertical: 'top',
  },
  forwardButton: {
    marginTop: 'auto',
  },
  sectionLabel: {
    ...typeScale.eyebrow,
    color: colors.bark,
    marginBottom: 8,
  },
  selectedBox: {
    borderWidth: 1,
    borderColor: colors.sand,
    backgroundColor: colors.ivory,
    padding: 14,
    gap: 6,
  },
  selectedRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  selectedName: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.ink,
    flex: 1,
  },
  selectedPrice: {
    fontFamily: fontFamily.semiBold,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  searchInput: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 14,
    minHeight: spacing.primaryButtonHeight,
    fontFamily: fontFamily.regular,
    fontSize: 16,
    color: colors.ink,
  },
  priceList: {
    gap: 18,
  },
  priceCategory: {
    gap: 1,
    backgroundColor: colors.sand,
    borderWidth: 1,
    borderColor: colors.sand,
  },
  priceCategoryLabel: {
    ...typeScale.eyebrow,
    color: colors.ivory,
    backgroundColor: colors.bark,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  priceRow: {
    backgroundColor: colors.white,
    paddingVertical: 10,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  priceRowText: {
    flex: 1,
  },
  priceRowName: {
    fontFamily: fontFamily.regular,
    fontSize: 14,
    lineHeight: 19,
    color: colors.ink,
  },
  priceRowBase: {
    fontFamily: fontFamily.regular,
    fontSize: 12,
    color: colors.bark,
    fontVariant: ['tabular-nums'],
  },
  stepper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  stepperButton: {
    width: 32,
    height: 32,
    borderWidth: 1,
    borderColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepperButtonDisabled: {
    borderColor: colors.sand,
    backgroundColor: colors.sand,
  },
  stepperGlyph: {
    fontFamily: fontFamily.semiBold,
    fontSize: 17,
    color: colors.ink,
  },
  stepperCount: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
    minWidth: 18,
    textAlign: 'center',
  },
  materialRow: {
    borderWidth: 1,
    borderColor: colors.sand,
    padding: 12,
    gap: 8,
  },
  materialInputName: {
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 12,
    minHeight: 44,
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.ink,
  },
  materialInlineRow: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
  },
  materialInputSmall: {
    flex: 1,
    borderWidth: 1,
    borderColor: colors.ink,
    backgroundColor: colors.white,
    paddingHorizontal: 10,
    minHeight: 44,
    fontFamily: fontFamily.regular,
    fontSize: 14,
    color: colors.ink,
    fontVariant: ['tabular-nums'],
  },
  materialRemove: {
    minHeight: 44,
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  materialRemoveLabel: {
    fontFamily: fontFamily.medium,
    fontSize: 13,
    color: colors.error,
  },
  addMaterialButton: {
    alignSelf: 'flex-start',
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  photoThumbWrap: {
    width: 96,
    height: 96,
    position: 'relative',
  },
  photoThumb: {
    width: '100%',
    height: '100%',
  },
  photoRemove: {
    position: 'absolute',
    top: -8,
    right: -8,
    width: 24,
    height: 24,
    backgroundColor: colors.ink,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoRemoveLabel: {
    color: colors.ivory,
    fontSize: 15,
    fontFamily: fontFamily.semiBold,
  },
  photoAddTile: {
    width: 96,
    height: 96,
    borderWidth: 1,
    borderColor: colors.sand,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoAddGlyph: {
    fontSize: 28,
    fontFamily: fontFamily.regular,
    color: colors.bark,
  },
  errorBox: {
    borderWidth: 1,
    borderColor: colors.error,
    backgroundColor: colors.white,
    padding: 14,
    gap: 4,
  },
  errorText: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    lineHeight: 20,
    color: colors.error,
  },
});

export default ZavrsetakNalogaScreen;
