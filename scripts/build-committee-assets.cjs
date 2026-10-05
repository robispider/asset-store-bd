// Dependency-free build for this standalone browser script. The normal Mix build also emits it.
const fs = require('node:fs');
const crypto = require('node:crypto');
const path = 'public/mix-manifest.json';
const manifest = JSON.parse(fs.readFileSync(path, 'utf8'));
for (const [sourcePath, targetPath] of [
    ['resources/assets/js/committee.js', 'public/js/dist/committee.js'],
    ['resources/assets/css/committee.css', 'public/css/dist/committee.css'],
]) {
    const source = fs.readFileSync(sourcePath);
    fs.writeFileSync(targetPath, source);
    manifest['/' + targetPath.replace(/^public\//, '')] = '/' + targetPath.replace(/^public\//, '') + '?id=' + crypto.createHash('md5').update(source).digest('hex');
}
fs.writeFileSync(path, JSON.stringify(manifest, null, 4) + '\n');
