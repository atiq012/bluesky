#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..', 'resources', 'js', 'components');

function importPath(filePath) {
    const rel = path.relative(path.dirname(filePath), path.join(ROOT, 'common', 'AppBreadcrumbs.vue'));
    return rel.startsWith('.') ? rel.replace(/\\/g, '/') : `./${rel.replace(/\\/g, '/')}`;
}

function addImport(content, importLine) {
    if (content.includes('import AppBreadcrumbs')) return content;

    const setupMatch = content.match(/<script setup[^>]*>/);
    if (setupMatch) {
        const idx = setupMatch.index + setupMatch[0].length;
        return content.slice(0, idx) + `\nimport AppBreadcrumbs from '${importLine}';\n` + content.slice(idx);
    }

    const scriptMatch = content.match(/<script(?![^>]*setup)[^>]*>\n/);
    if (scriptMatch) {
        const idx = scriptMatch.index + scriptMatch[0].length;
        let updated = content.slice(0, idx) + `import AppBreadcrumbs from '${importLine}';\n` + content.slice(idx);
        if (!/components:\s*\{[^}]*AppBreadcrumbs/.test(updated)) {
            if (/components:\s*\{/.test(updated)) {
                updated = updated.replace(/components:\s*\{/, 'components: {\n        AppBreadcrumbs,');
            } else {
                updated = updated.replace(/export default \{/, 'export default {\n    components: { AppBreadcrumbs },');
            }
        }
        return updated;
    }
    return content;
}

function walk(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, files);
        else if (entry.name.endsWith('.vue')) files.push(full);
    }
    return files;
}

let count = 0;
for (const f of walk(ROOT)) {
    const content = fs.readFileSync(f, 'utf8');
    if (!content.includes('<AppBreadcrumbs') || content.includes('import AppBreadcrumbs')) continue;
    const imp = importPath(f);
    const updated = addImport(content, imp);
    if (updated !== content) {
        fs.writeFileSync(f, updated);
        console.log('fixed import:', path.relative(ROOT, f));
        count++;
    }
}
console.log(`Fixed ${count} files.`);
