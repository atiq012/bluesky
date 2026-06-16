const MAX_DEPTH = 5;

function appendSearchableParts(value, parts, depth) {
    if (depth > MAX_DEPTH || value == null) {
        return;
    }

    const type = typeof value;

    if (type === 'string' || type === 'number' || type === 'boolean') {
        const text = String(value).trim();
        if (text) {
            parts.push(text);
        }
        return;
    }

    if (type !== 'object') {
        return;
    }

    if (Array.isArray(value)) {
        for (const item of value) {
            appendSearchableParts(item, parts, depth + 1);
        }
        return;
    }

    for (const key of Object.keys(value)) {
        appendSearchableParts(value[key], parts, depth + 1);
    }
}

export function rowSearchHaystack(row) {
    const parts = [];
    appendSearchableParts(row, parts, 0);
    return parts.join(' ').toLowerCase();
}

export function rowMatchesSearch(row, query) {
    const q = String(query ?? '').trim().toLowerCase();
    if (!q) {
        return true;
    }
    return rowSearchHaystack(row).includes(q);
}

export function filterRowsBySearch(rows, query) {
    const q = String(query ?? '').trim();
    if (!q || !Array.isArray(rows)) {
        return rows ?? [];
    }
    return rows.filter((row) => rowMatchesSearch(row, q));
}
