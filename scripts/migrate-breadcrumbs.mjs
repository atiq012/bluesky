#!/usr/bin/env node
/**
 * Migrate page-breadcrumb blocks to AppBreadcrumbs.vue
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..', 'resources', 'js', 'components');

const ICON_MAP = [
    [/flight|booking|ticketing|pnr|search/i, 'fa-solid fa-plane'],
    [/agent|agency|b2b/i, 'fa-solid fa-building'],
    [/travell?er|user/i, 'fa-solid fa-users'],
    [/deposit|payment|bank|mfs/i, 'fa-solid fa-wallet'],
    [/helpdesk|support/i, 'fa-solid fa-headset'],
    [/role|permission/i, 'fa-solid fa-key'],
    [/category/i, 'fa-solid fa-tags'],
    [/zone|area|office|department|designation/i, 'fa-solid fa-map-location-dot'],
    [/airline|aircraft/i, 'fa-solid fa-plane-up'],
];

function pickIcon(title) {
    for (const [re, icon] of ICON_MAP) {
        if (re.test(title)) return icon;
    }
    return 'fa-solid fa-sliders';
}

function importPath(filePath) {
    const rel = path.relative(path.dirname(filePath), path.join(ROOT, 'common', 'AppBreadcrumbs.vue'));
    return rel.startsWith('.') ? rel : `./${rel}`;
}

function parseBreadcrumbBlock(html) {
    const titleMatch = html.match(/breadcrumb-title[^>]*>([^<]+)</);
    if (!titleMatch) return null;
    const title = titleMatch[1].trim();

    const items = [];
    const itemRe = /<li[^>]*class="[^"]*breadcrumb-item[^"]*"[^>]*>([\s\S]*?)<\/li>/gi;
    let m;
    while ((m = itemRe.exec(html)) !== null) {
        const inner = m[0];
        const isActive = /breadcrumb-item active/.test(inner) || /aria-current="page"/.test(inner);
        const linkMatch = inner.match(/<router-link\s+:to="(\{[^"]+\})"[^>]*>([^<]+)<\/router-link>/);
        if (linkMatch && !isActive) {
            items.push({ label: linkMatch[2].trim(), to: linkMatch[1] });
        } else {
            const text = inner.replace(/<[^>]+>/g, '').trim();
            if (text) items.push({ label: text });
        }
    }

    const actionsMatch = html.match(/<div class="ms-auto">([\s\S]*?)<\/div>\s*<\/div>\s*$/);
    const actions = actionsMatch ? actionsMatch[1].trim() : null;

    return { title, items, actions };
}

function formatBreadcrumbs(items) {
    return items
        .map((item) => {
            if (item.to) {
                return `            { label: '${item.label.replace(/'/g, "\\'")}', to: ${item.to} }`;
            }
            return `            { label: '${item.label.replace(/'/g, "\\'")}' }`;
        })
        .join(',\n');
}

function backToFromItems(items) {
    const withTo = items.filter((i) => i.to);
    if (withTo.length >= 2) return withTo[withTo.length - 1].to;
    if (withTo.length === 1) return withTo[0].to;
    return "{ name: 'Home' }";
}

function addImport(content, importLine) {
    if (content.includes('AppBreadcrumbs')) return content;
    if (content.includes('<script setup>')) {
        return content.replace(
            /<script setup>\n/,
            `<script setup>\nimport AppBreadcrumbs from '${importLine}';\n`
        );
    }
    if (content.includes('<script setup ')) {
        return content.replace(
            /<script setup[^>]*>\n/,
            (m) => `${m}import AppBreadcrumbs from '${importLine}';\n`
        );
    }
    // options API with import block
    const scriptMatch = content.match(/<script[^>]*>\n([\s\S]*?)<\/script>/);
    if (!scriptMatch) return content;
    const script = scriptMatch[1];
    if (script.includes('import ')) {
        const lastImport = [...script.matchAll(/^import .+$/gm)].pop();
        if (lastImport) {
            const idx = scriptMatch.index + '<script'.length + scriptMatch[0].indexOf(lastImport[0]) + lastImport[0].length;
            return content.slice(0, idx) + `\nimport AppBreadcrumbs from '${importLine}';` + content.slice(idx);
        }
    }
    return content.replace(/<script([^>]*)>\n/, `<script$1>\nimport AppBreadcrumbs from '${importLine}';\n`);
}

function registerComponent(content) {
    if (content.includes('<script setup')) return content;
    if (/components:\s*\{[^}]*AppBreadcrumbs/.test(content)) return content;
    if (/components:\s*\{/.test(content)) {
        return content.replace(/components:\s*\{/, 'components: {\n        AppBreadcrumbs,');
    }
    return content.replace(/export default \{/, 'export default {\n    components: { AppBreadcrumbs },');
}

function migrateFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    if (content.includes('AppBreadcrumbs')) return false;

    // Standard page-breadcrumb
    const pageBcRe = /<div class="page-breadcrumb[\s\S]*?<\/div>\s*\n\s*<\/div>(?:\s*\n\s*<\/div>)?/m;
    let match = content.match(pageBcRe);

    // helpdesk-style custom breadcrumb
    const helpdeskRe =
        /<div class="d-flex flex-wrap align-items-center gap-2 mb-3">\s*<div class="flex-grow-1">[\s\S]*?<nav aria-label="breadcrumb">[\s\S]*?<\/nav>[\s\S]*?<\/div>\s*(?:<div class="ms-auto">[\s\S]*?<\/div>)?\s*<\/div>/m;

    if (!match) {
        const hd = content.match(helpdeskRe);
        if (!hd) return false;
        match = hd;
    }

    const block = match[0];
    const parsed = parseBreadcrumbBlock(block);
    if (!parsed || !parsed.items.length) return false;

    const imp = importPath(filePath).replace(/\\/g, '/');
    const icon = pickIcon(parsed.title);
    const backTo = backToFromItems(parsed.items);
    const crumbs = formatBreadcrumbs(parsed.items);

    let replacement = `    <AppBreadcrumbs
        title="${parsed.title.replace(/"/g, '&quot;')}"
        icon="${icon}"
        :back-to="${backTo}"
        :breadcrumbs="[
${crumbs},
        ]"
    `;

    if (parsed.actions) {
        replacement += `>
        <template #actions>
            ${parsed.actions}
        </template>
    </AppBreadcrumbs>`;
    } else {
        replacement += '/>';
    }

    content = content.replace(block, replacement);
    content = addImport(content, imp);
    content = registerComponent(content);
    fs.writeFileSync(filePath, content);
    return true;
}

function walk(dir, files = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, files);
        else if (entry.name.endsWith('.vue')) files.push(full);
    }
    return files;
}

const files = walk(ROOT);
let count = 0;
for (const f of files) {
    if (migrateFile(f)) {
        console.log('migrated:', path.relative(ROOT, f));
        count++;
    }
}
console.log(`Done. ${count} files migrated.`);
