// Legacy HTML builders (jQuery strings, document.write, tippy allowHTML) — escape user/DB values.
// New code: Vue {{ }} instead of building HTML strings.
export function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"'`]/g, (ch) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
        "`": "&#96;",
    })[ch]);
}

// URL attributes need scheme check — escapeHtml alone does not block javascript:.
export function safeUrl(value) {
    const url = String(value ?? "").trim();
    return /^(https?:|\/|data:image\/)/i.test(url) ? url : "";
}

// Excel/Sheets treat cells starting with = + - @ as formulas.
export function escapeCsvFormula(value) {
    const s = String(value ?? "");
    if (/^[=+\-@\t\r]/.test(s)) {
        return `'${s}`;
    }
    return s;
}
