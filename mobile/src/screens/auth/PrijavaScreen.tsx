/**
 * 03 Prijava (design/README.md, prototip data-screen-label="03 Prijava").
 *
 * Email + lozinka, bez telefona. Submit ide kroz auth store login()
 * (POST /auth/login, docs/API.md); uspjeh upisuje token/role u store i
 * RootNavigator sam prebaci na tabove po ulozi. 422 prikazuje poruku.
 *
 * "Prijava za dispečera" je isti login endpoint, nema poseban ulaz (uloga
 * stiže sa servera u /auth/login odgovoru): dugme samo fokusira e-mail
 * polje, u skladu sa task napomenom "ista prijava forma, samo
 * scroll/focus; nema poseban endpoint".
 */

import React, { useRef, useState } from 'react';
import {
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Button from '../../components/Button';
import TextField from '../../components/TextField';
import { ApiError } from '../../api/client';
import { useAuthStore } from '../../store/auth';
import { colors, fontFamily, spacing, typeScale } from '../../theme/tokens';
import type { RootStackParamList } from '../../navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const schema = z.object({
  email: z
    .string()
    .min(1, 'Upišite e-mail.')
    .email('E-mail adresa nije ispravna.'),
  password: z.string().min(1, 'Upišite lozinku.'),
});

type FormValues = z.infer<typeof schema>;

export function PrijavaScreen() {
  const navigation = useNavigation<Nav>();
  const login = useAuthStore((state) => state.login);
  const [serverError, setServerError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const passwordRef = useRef<TextInput>(null);
  const emailRef = useRef<TextInput>(null);

  const { control, handleSubmit, setError } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  });

  const onSubmit = async (values: FormValues) => {
    setServerError('');
    setSubmitting(true);
    try {
      await login(values.email, values.password);
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.status === 422 && error.errors) {
          Object.entries(error.errors).forEach(([field, messages]) => {
            if (field === 'email' || field === 'password') {
              setError(field, { message: messages[0] });
            }
          });
        }
        setServerError(error.message);
      } else {
        setServerError('Došlo je do greške. Pokušajte ponovo.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <Image
          source={require('../../../assets/images/logo-primary.png')}
          style={styles.logo}
          resizeMode="contain"
          accessibilityLabel="HAUS"
        />
        <Text style={styles.headline}>Prijava</Text>
        <Text style={styles.subline}>Vaš karton, intervencije i pretplata.</Text>

        <View style={styles.form}>
          <Controller
            control={control}
            name="email"
            render={({ field, fieldState }) => (
              <TextField
                ref={emailRef}
                label="E-mail"
                value={field.value}
                onChangeText={field.onChange}
                onBlur={field.onBlur}
                error={fieldState.error?.message}
                keyboardType="email-address"
                autoComplete="email"
                returnKeyType="next"
                onSubmitEditing={() => passwordRef.current?.focus()}
              />
            )}
          />
          <Controller
            control={control}
            name="password"
            render={({ field, fieldState }) => (
              <TextField
                ref={passwordRef}
                label="Lozinka"
                value={field.value}
                onChangeText={field.onChange}
                onBlur={field.onBlur}
                error={fieldState.error?.message}
                secureTextEntry
                autoComplete="current-password"
                returnKeyType="done"
                onSubmitEditing={handleSubmit(onSubmit)}
              />
            )}
          />
          {!!serverError && <Text style={styles.serverError}>{serverError}</Text>}
          <Button
            label={submitting ? 'Prijavljujem...' : 'Prijavite se'}
            onPress={handleSubmit(onSubmit)}
            disabled={submitting}
            style={styles.submitButton}
          />
        </View>

        <View style={styles.footer}>
          <View style={styles.registerRow}>
            <Text style={styles.registerText}>Nemate pretplatu? </Text>
            <Pressable
              accessibilityRole="button"
              onPress={() => navigation.navigate('IzborPaketa')}
            >
              <Text style={styles.registerLink}>Registrujte se</Text>
            </Pressable>
          </View>
          <Pressable
            accessibilityRole="button"
            onPress={() => emailRef.current?.focus()}
            style={styles.dispatcherButton}
          >
            <Text style={styles.dispatcherLabel}>Prijava za dispečera</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.ivory,
  },
  content: {
    flexGrow: 1,
    padding: 26,
    paddingTop: 36,
    paddingBottom: 28,
  },
  logo: {
    width: 132,
    height: 29,
    marginBottom: 44,
  },
  headline: {
    ...typeScale.screenH2,
    fontSize: 34,
    color: colors.ink,
    letterSpacing: -0.4,
    marginBottom: 10,
  },
  subline: {
    fontFamily: fontFamily.regular,
    fontSize: 16,
    lineHeight: 24,
    color: colors.bark,
    marginBottom: 32,
  },
  form: {
    gap: spacing.fieldGap,
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
  submitButton: {
    marginTop: 6,
  },
  footer: {
    marginTop: 'auto',
    paddingTop: 32,
    borderTopWidth: 1,
    borderTopColor: colors.sand,
    gap: 12,
  },
  registerRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  registerText: {
    fontFamily: fontFamily.regular,
    fontSize: 15,
    color: colors.bark,
  },
  registerLink: {
    fontFamily: fontFamily.semiBold,
    fontSize: 15,
    color: colors.ink,
    textDecorationLine: 'underline',
    textDecorationColor: colors.ember,
  },
  dispatcherButton: {
    minHeight: spacing.touchTargetMin,
    justifyContent: 'center',
    alignItems: 'flex-start',
  },
  dispatcherLabel: {
    fontFamily: fontFamily.medium,
    fontSize: 14,
    color: colors.bark,
  },
});

export default PrijavaScreen;
