// Fare Rule Engine (Phase 7) may attach `fare_pricing` instead of `dynamic_pricing` — the two
// engines are mutually exclusive per request (config('FareRules.engine.enabled') on the backend),
// never both on the same response. Every helper below normalizes fare_pricing into the old
// dynamic_pricing shape so every existing direct-key read across the search/booking components
// keeps working unchanged, regardless of which engine actually priced the brand. When
// fare_pricing is absent this is a no-op passthrough to dynamic_pricing — zero behavior change
// while the flag is off.
//
// Field mapping (docs/FARE_RULE_ENGINE.md §9.1 "Field mapping from the old payload"):
//   gross_payment  <- gross_fare      (old "selling figure" inflated by markup doesn't exist in
//                                       the new system — markup now raises payable, not gross,
//                                       §7.5 — so gross_payment and gross_fare are the same value)
//   total_payable  <- agency_pays
//   commission     <- comm_share      (agency-facing share only, semantics changed — see §7.5)
//   stoppage_discount <- promo
//   service_charge <- service_fee
//   pricing_breakdown <- breakdown
//   applied_rule_ids  <- applied[].rule_id
//   rule_name/rule_id <- applied[0] (the base winner), best-effort — the old payload had one
//                                     rule per brand, the new one can have several (base+addons)
function normalizeFarePricing(farePricing) {
    if (!farePricing) return null;

    const applied = Array.isArray(farePricing.applied) ? farePricing.applied : [];
    const winner = applied.find((a) => a.role === 'base') ?? applied[0] ?? null;

    return {
        gross_fare: farePricing.gross_fare,
        gross_payment: farePricing.gross_fare,
        total_payable: farePricing.agency_pays,
        commission: farePricing.comm_share,
        stoppage_discount: farePricing.promo,
        service_charge: farePricing.service_fee,
        markup: farePricing.markup,
        ait: farePricing.ait,
        ait_precise: farePricing.ait_precise,
        gross_for_ait: farePricing.gross_for_ait,
        pricing_breakdown: farePricing.breakdown ?? [],
        applied_rule_ids: applied.map((a) => a.rule_id),
        rule_applied: !!farePricing.rule_applied,
        rule_name: winner?.name ?? null,
        rule_id: winner?.rule_id ?? null,
        currency: farePricing.currency,
        pricing_provisional: !!farePricing.pricing_provisional,
        _source: 'fare_rule_engine',
    };
}

// Search brand: { baseFare, taxes: number }. Price/confirm: { base_fare, total_taxes, taxes: [] }.
function sumBaseAndTaxes(source) {
    if (!source) return { base: 0, taxes: 0 };

    const rows = source.price_breakdown;
    if (Array.isArray(rows) && rows.length) {
        let base = 0;
        let taxes = 0;
        for (const row of rows) {
            const qty = Math.max(1, Number(row.quantity ?? 1) || 1);
            base += Number(row.baseFare ?? row.base_fare ?? 0) * qty;
            // Search stores total tax as a number; price/confirm stores per-code tax array
            const taxVal = Array.isArray(row.taxes)
                ? Number(row.total_taxes ?? 0)
                : Number(row.taxes ?? row.total_taxes ?? 0);
            taxes += taxVal * qty;
        }
        return { base, taxes };
    }

    return {
        base: Number(source.base_fare ?? source.baseFare ?? 0),
        taxes: Number(source.total_taxes ?? source.totalTaxes ?? 0),
    };
}

function baseTaxHeadLines(base, taxes, gross) {
    if (base > 0 || taxes > 0) {
        return [
            { label: 'Base Fare', amount: base, type: 'line' },
            { label: 'Total Taxes', amount: taxes, type: 'line' },
        ];
    }

    return [{ label: 'Gross fare', amount: gross, type: 'line' }];
}

// Server/full breakdown still leads with "Gross fare" — swap for Base + Taxes when we have them
function withBaseTaxLines(lines, base, taxes) {
    if (!(base > 0 || taxes > 0) || !Array.isArray(lines)) return lines;

    const idx = lines.findIndex((line) => (line.label || '').toLowerCase() === 'gross fare');
    if (idx === -1) return lines;

    const next = lines.slice();
    next.splice(idx, 1, ...baseTaxHeadLines(base, taxes, 0));
    return next;
}

function formatAitActual(precise) {
    const n = Number(precise);
    if (!Number.isFinite(n)) return null;
    return n.toFixed(2);
}

function aitLineLabel(precise) {
    const actual = formatAitActual(precise);
    return actual ? `AIT (Actual ${actual})` : 'AIT';
}

// Compact/full breakdown labels AIT as the rounded whole-taka charge — append precise
function withAitActualLabel(lines, precise) {
    const actual = formatAitActual(precise);
    if (!actual || !Array.isArray(lines)) return lines;

    const idx = lines.findIndex((line) => {
        const label = (line.label || '').trim().toLowerCase();
        return label === 'ait' || label.startsWith('ait (');
    });
    if (idx === -1) return lines;

    const next = lines.slice();
    next[idx] = { ...next[idx], label: `AIT (Actual ${actual})` };
    return next;
}

// Search flow uses calculateCompact() — omits breakdown/applied for ~1k brands (§7.7-F).
// Flat totals still ship; rebuild the same lines FareRuleCalculator::buildBreakdown uses so
// the agency payable modal can open from branded-fare blue price without a price/confirm call.
function synthesizeBreakdownFromFlat(pricing, base, taxes) {
    const gross = Number(pricing.gross_fare ?? pricing.gross_payment ?? 0);
    const markup = Number(pricing.markup ?? 0);
    const serviceFee = Number(pricing.service_charge ?? 0);
    const commission = Number(pricing.commission ?? 0);
    const promo = Number(pricing.stoppage_discount ?? 0);
    const ait = Number(pricing.ait ?? 0);
    const payable = Number(pricing.total_payable ?? 0);

    const lines = baseTaxHeadLines(base, taxes, gross);
    if (markup > 0) lines.push({ label: 'Markup', amount: markup, type: 'addition' });
    if (serviceFee > 0) lines.push({ label: 'Service fee', amount: serviceFee, type: 'addition' });
    if (commission > 0) lines.push({ label: 'Commission share', amount: commission, type: 'deduction' });
    if (promo > 0) lines.push({ label: 'Promo', amount: promo, type: 'deduction' });
    lines.push({ label: aitLineLabel(pricing.ait_precise), amount: ait, type: 'addition' });
    lines.push({ label: 'Payable', amount: payable, type: 'total' });

    return lines;
}

function ensurePricingBreakdown(pricing, base, taxes) {
    if (!pricing) return null;

    const existing = pricing.pricing_breakdown;
    if (Array.isArray(existing) && existing.length > 0) {
        const split = withAitActualLabel(withBaseTaxLines(existing, base, taxes), pricing.ait_precise);
        if (split === existing) return pricing;
        return { ...pricing, pricing_breakdown: split };
    }

    return {
        ...pricing,
        pricing_breakdown: synthesizeBreakdownFromFlat(pricing, base, taxes),
        _breakdown_synthesized: true,
    };
}

export function formatFareAmount(value) {
    const n = Number(value ?? 0);
    if (!Number.isFinite(n)) return '0';
    // Whole currency units — skip trailing .00
    return Math.round(n).toLocaleString('en-US');
}

// Single source for "what pricing object applies to this brand" — prefer this over reading
// brand.dynamic_pricing or brand.fare_pricing directly anywhere new code is written.
export function brandDynamicPricing(brand) {
    const pricing = normalizeFarePricing(brand?.fare_pricing) ?? brand?.dynamic_pricing ?? null;
    const { base, taxes } = sumBaseAndTaxes(brand);
    return ensurePricingBreakdown(pricing, base, taxes);
}

export function brandGrossFare(brand) {
    const pricing = brandDynamicPricing(brand);
    if (pricing) {
        return Number(pricing.gross_payment ?? pricing.gross_fare ?? 0);
    }
    return Number(brand?.gross_payment ?? brand?.gross_fare ?? brand?.price ?? 0);
}

export function brandTotalPayable(brand) {
    const pricing = brandDynamicPricing(brand);
    if (pricing) {
        return Number(pricing.total_payable ?? 0);
    }
    return Number(brand?.total_payable ?? brand?.price ?? 0);
}

export function brandHasAgentPricing(brand) {
    const pricing = brandDynamicPricing(brand);
    if (pricing?.rule_applied) {
        return true;
    }

    return Math.abs(brandGrossFare(brand) - brandTotalPayable(brand)) > 0.01;
}

export function canShowPayableBreakdown(brand) {
    const breakdown = brandDynamicPricing(brand)?.pricing_breakdown;
    return Array.isArray(breakdown) && breakdown.length > 0;
}
