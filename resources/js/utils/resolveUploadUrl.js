/**
 * Resolve a DB-stored upload path to a browser-loadable URL.
 * Public uploads live under /uploads/... on the site origin (not the API path).
 */
export function resolveUploadUrl(path) {
    const clean = String(path ?? '').trim();
    if (!clean) return '';

    if (
        clean.startsWith('http://')
        || clean.startsWith('https://')
        || clean.startsWith('blob:')
        || clean.startsWith('data:')
    ) {
        return clean;
    }

    let normalized = clean.replace(/^public\//, '');
    if (!normalized.startsWith('/')) {
        normalized = `/${normalized}`;
    }

    return `${window.location.origin}${normalized}`;
}
