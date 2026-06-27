export function formatFareAmount(value) {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n.toLocaleString() : '0';
}

export function brandGrossFare(brand) {
    return Number(brand?.gross_payment ?? brand?.gross_fare ?? brand?.price ?? 0);
}

export function brandTotalPayable(brand) {
    return Number(brand?.total_payable ?? brand?.price ?? 0);
}

export function brandHasAgentPricing(brand) {
    if (brand?.dynamic_pricing?.rule_applied) {
        return true;
    }

    return Math.abs(brandGrossFare(brand) - brandTotalPayable(brand)) > 0.01;
}

export function brandDynamicPricing(brand) {
    return brand?.dynamic_pricing ?? null;
}

export function canShowPayableBreakdown(brand) {
    const breakdown = brand?.dynamic_pricing?.pricing_breakdown;
    return Array.isArray(breakdown) && breakdown.length > 0;
}
