// Dependency-free build for this standalone browser script. The normal Mix build also emits it.
const fs = require('node:fs');
const crypto = require('node:crypto');
const source = fs.readFileSync('resources/assets/js/committee.js');
fs.writeFileSync('public/js/dist/committee.js', source);
const path = 'public/mix-manifest.json';
const manifest = JSON.parse(fs.readFileSync(path, 'utf8'));
manifest['/js/dist/committee.js'] = '/js/dist/committee.js?id=' + crypto.createHash('md5').update(source).digest('hex');
fs.writeFileSync(path, JSON.stringify(manifest, null, 4) + '\n');
