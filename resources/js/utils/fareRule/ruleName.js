// Mirrors app/Services/FareRule/FareRuleNameBuilder.php exactly — WHO · WHERE · WHAT ±VALUE · CASE
// (plan §3.7). For instant keystroke-level preview only; PHP is authoritative and regenerates the
// real name on save. Keep both in sync — this is the one JS mirror this phase ships (§16.6 warns
// against letting mirrors drift; conflict checking instead calls the real API, see LiveChecksPanel).

const SIGN = {
    markup: '+', promo: '-', service_fee: '+', comm_share: '-',
    cashback: '-', commission: '+', plb: '+', gds_incent: '+',
};

const MODE_SUFFIX = {
    fixed: '', pct_base: '%base', pct_base_yq: '%base+yq',
    pct_yq: '%yq', pct_yr: '%yr', pct_total: '%total',
};

function scopeTag(value) {
    switch (value) {
        case 'domestic': return 'DOM';
        case 'international': return 'INTL';
        case 'mixed': return 'MIX';
        case 'none': return 'NONE';
        default: return String(value || '').toUpperCase();
    }
}

// tierLabels: { [key]: label }, agencyNames: { [id]: name } — resolved by the caller, this
// function has no data access of its own (mirrors the PHP builder's design exactly).
function who(rule, agencyNames, tierLabels) {
    if (rule.agencies?.length) {
        return rule.agencies.map((id) => agencyNames?.[id] ?? `Agency #${id}`).join(' + ');
    }
    if (rule.tiers?.length) {
        return rule.tiers.map((key) => tierLabels?.[key] ?? key).join(' + ');
    }
    if (rule.airlines?.length) {
        return rule.airlines.join(' + ');
    }
    if (rule.suppliers?.length) {
        return rule.suppliers.join(' + ');
    }
    return '';
}

function where(rule) {
    const segments = [];

    if (rule.routes?.length) {
        segments.push(rule.routes.map(([o, d]) => `${o}-${d}`).join(' + '));
    }

    const scope = rule.scope || 'any';
    if (scope !== 'any') {
        segments.push('+' + scopeTag(scope));
    }

    const onward = rule.onward || 'any';
    if (onward !== 'any') {
        segments.push('+ONW-' + scopeTag(onward));
    }

    return segments.join(' ');
}

function what(rule) {
    const addonPrefix = rule.addon ? 'EXTRA ' : '';
    const sign = SIGN[rule.type] ?? '+';
    const suffix = MODE_SUFFIX[rule.mode] ?? '';
    const n = Number(rule.value ?? 0);
    const value = Number.isFinite(n) ? String(Math.round(n * 100) / 100).replace(/\.0$/, '') : '0';

    return `${addonPrefix}${rule.type} ${sign}${value}${suffix}`;
}

export function buildRuleName(rule, agencyNames = {}, tierLabels = {}) {
    const parts = [who(rule, agencyNames, tierLabels), where(rule), what(rule), rule.biz_case || ''];
    return parts.filter((p) => p !== '').join(' · ');
}
