// Assemble the single-file prototype: page shell + six built gs themes + kit.css + app script.
import {readFileSync, writeFileSync, readdirSync, mkdirSync} from 'node:fs';
const vendor = '../../../public/vendor/gs-theme';
const themes = readdirSync(`${vendor}/themes`).filter(f => f.endsWith('.css')).sort().map(f => readFileSync(`${vendor}/themes/${f}`, 'utf8')).join('\n');
const kitFile = readdirSync(vendor).find(f => /^kit\..*\.css$/.test(f));
const kit = readFileSync(`${vendor}/${kitFile}`, 'utf8').replace(/url\([^)]*\)/g, 'none');
let page = readFileSync('src/page.html', 'utf8');
page = page.replace('/*GS_THEMES*/', () => themes).replace('/*GS_KIT*/', () => kit).replace('/*PF_APP*/', () => readFileSync('src/app.js', 'utf8'));
mkdirSync('artifact', {recursive: true});
writeFileSync('artifact/tracking-portfolio-options.html', page);
console.log('built', kitFile, (page.length / 1024).toFixed(0) + ' KB');
