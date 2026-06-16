export function stripNumberCommas(value) {
    if (value == null || value === '') return '';
    return String(value).replace(/,/g, '').trim();
}

export function sanitizeNumericString(value, maxDecimals = 2) {
    let s = stripNumberCommas(value);
    if (s === '') return '';

    s = s.replace(/[^\d.]/g, '');
    const firstDot = s.indexOf('.');
    if (firstDot !== -1) {
        s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');
    }

    if (maxDecimals === 0) {
        return s.split('.')[0];
    }

    if (firstDot === -1) return s;

    const [whole, frac = ''] = s.split('.');
    if (frac === '' && s.endsWith('.')) {
        return `${whole}.`;
    }
    return `${whole}.${frac.slice(0, maxDecimals)}`;
}

export function formatNumberWithCommas(value, maxDecimals = 2) {
    const raw = sanitizeNumericString(value, maxDecimals);
    if (raw === '') return '';
    if (raw === '.') return '0.';

    const hasTrailingDot = raw.endsWith('.');
    const [wholeRaw, fracRaw] = raw.split('.');
    const wholeDigits = wholeRaw === '' ? '0' : wholeRaw;
    const wholeFormatted = Number(wholeDigits).toLocaleString('en-US');

    if (fracRaw === undefined && !hasTrailingDot) {
        return wholeFormatted;
    }
    if (fracRaw === undefined && hasTrailingDot) {
        return `${wholeFormatted}.`;
    }
    return `${wholeFormatted}.${fracRaw}`;
}
