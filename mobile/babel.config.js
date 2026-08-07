module.exports = {
  presets: ['module:@react-native/babel-preset'],
  // zod v4 (node_modules/zod/v4/classic/external.js) koristi
  // `export * as core from ...`; Metro/Babel bez ovog plugina baca
  // "Export namespace should be first transformed by
  // @babel/plugin-transform-export-namespace-from". Paket je već u
  // node_modules (transitivna zavisnost), samo nije bio uključen u
  // presete jer prije ovoga niko nije importovao zod pa se nije bundlao.
  plugins: ['@babel/plugin-transform-export-namespace-from'],
};
