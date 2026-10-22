// Loads the pure gs-geo modules into a vm context, the way the browser concatenates them.
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { repo } from './schema.mjs';

export const engineDir = path.join(repo, 'packages/gov-store/theming/resources/js/gs-geo');
const PURE = ['00-core.js', '10-scale.js', '20-camera.js', '30-url.js', '40-config.js', '50-store.js'];

export function loadGeo(files = PURE) {
  const context = vm.createContext({ Intl, URLSearchParams, Math, JSON, console });
  context.globalThis = context;
  for (const file of files) vm.runInContext(readFileSync(path.join(engineDir, file), 'utf8'), context, { filename: file });
  return context.GsGeo;
}

export const engineFiles = () => readdirSync(engineDir).filter(f => f.endsWith('.js')).sort();
