// Cache za javne kataloške podatke (paketi, gradovi, cjenovnik, doplate, postavke).
// Svaki endpoint se dohvata jednom po učitavanju stranice i deli među komponentama,
// da naslovna, cijene, cjenovnik i podnožje ne šalju iste zahtjeve više puta.
import { apiGet } from './client';

let packagesPromise = null;
let citiesPromise = null;
let priceListPromise = null;
let surchargesPromise = null;
let settingsPromise = null;

export function fetchPackages() {
  if (!packagesPromise) {
    packagesPromise = apiGet('/packages').then((body) => body.data);
  }
  return packagesPromise;
}

export function fetchCities() {
  if (!citiesPromise) {
    citiesPromise = apiGet('/cities').then((body) => body.data);
  }
  return citiesPromise;
}

export function fetchPriceList() {
  if (!priceListPromise) {
    priceListPromise = apiGet('/price-list').then((body) => body.data);
  }
  return priceListPromise;
}

export function fetchSurcharges() {
  if (!surchargesPromise) {
    surchargesPromise = apiGet('/surcharges').then((body) => body.data);
  }
  return surchargesPromise;
}

export function fetchSettings() {
  if (!settingsPromise) {
    settingsPromise = apiGet('/settings/public').then((body) => body.data);
  }
  return settingsPromise;
}
