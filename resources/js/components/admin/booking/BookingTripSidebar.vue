<script setup>
import { computed } from 'vue'
import { formatFareAmount } from '../../../utils/dynamicRulePricingDisplay'

// Fare + baggage come from saved price payload (booking_price_logs / store.priceData)
const props = defineProps({
    price: { type: Object, default: null },
    form: { type: Object, default: null },
})

const currency = computed(() => props.price?.currency ?? 'BDT')

// Amount only — currency lives in card title; Gross Fare still uses fmtMoney
function fmtAmt(n) {
    const num = Number(n)
    if (Number.isNaN(num)) return '—'
    return formatFareAmount(num)
}

function fmtMoney(n) {
    const num = Number(n)
    if (Number.isNaN(num)) return '—'
    return `${currency.value} ${formatFareAmount(num)}`
}

function paxCount(type) {
    const t = String(type || '').toLowerCase()
    const f = props.form || {}
    if (t.startsWith('adu') || t === 'adt') return Number(f.Adult ?? f.adt ?? 0) || null
    if (t.startsWith('chi') || t === 'cnn') return Number(f.Child ?? f.cnn ?? 0) || null
    if (t.startsWith('inf')) return Number(f.Infant ?? f.inf ?? 0) || null
    return null
}

function paxTone(type) {
    const t = String(type || '').toLowerCase()
    if (t.startsWith('chi') || t === 'cnn') return 'child'
    if (t.startsWith('inf')) return 'infant'
    return 'adult'
}

function paxIcon(type) {
    const tone = paxTone(type)
    if (tone === 'child') return 'fa-solid fa-child'
    if (tone === 'infant') return 'fa-solid fa-baby'
    return 'fa-solid fa-user'
}

// Group same PAX type into one row — Base/Tax stay per-person; Total = unit × qty
const breakdownRows = computed(() => {
    const rows = props.price?.price_breakdown
    if (!Array.isArray(rows)) return []

    const grouped = new Map()
    for (const bd of rows) {
        const type = bd.type || bd.passenger_type_code || 'PAX'
        const key = String(bd.passenger_type_code || type).toUpperCase()
        const unitQty = Number(bd.quantity ?? 1) || 1
        const unitTotal = Number(bd.total_price ?? 0)
        const existing = grouped.get(key)
        if (existing) {
            // Same PTC can carry different rates (e.g. Children vs Kids both send CNN
            // with a different age) — sum actual entry totals, don't reuse one unit rate.
            existing.qty += unitQty
            existing.lineTotal += unitTotal * unitQty
            continue
        }
        grouped.set(key, {
            type,
            tone: paxTone(type),
            icon: paxIcon(type),
            qty: unitQty,
            base: Number(bd.base_fare ?? 0),
            tax: Number(bd.total_taxes ?? 0),
            lineTotal: unitTotal * unitQty,
        })
    }

    return [...grouped.values()].map((row) => {
        // Form count fills gaps when TP sent one row but booking has multiple pax
        const formQty = paxCount(row.type)
        if (formQty > row.qty) {
            // Extend using this row's own per-unit rate as the best available estimate
            const perUnit = row.qty > 0 ? row.lineTotal / row.qty : 0
            return { ...row, qty: formQty, lineTotal: perUnit * formQty }
        }
        return row
    })
})

const totals = computed(() => ({
    base: Number(props.price?.base_fare ?? 0),
    tax: Number(props.price?.total_taxes ?? 0),
    gross: Number(
        props.price?.gross_fare
        ?? props.price?.gross_payment
        ?? props.price?.total_price
        ?? 0
    ),
}))

// Baggage from saved products[].baggage (price_payload)
const baggageLegs = computed(() => {
    const products = props.price?.products
    if (!Array.isArray(products) || !products.length) return []

    const groups = { outbound: [], inbound: [] }
    for (const p of products) {
        const dir = String(p.direction || 'outbound').toLowerCase()
        const key = (dir === 'inbound' || dir === 'in' || dir === 'return') ? 'inbound' : 'outbound'
        const bags = Array.isArray(p.baggage) ? p.baggage : []
        for (const b of bags) {
            if (!groups[key].some((x) => x.type === b.type)) {
                groups[key].push({
                    type: b.type,
                    label: b.label || (b.type === 'carry_on' ? 'Carry-On' : 'Checked'),
                    quantity: b.quantity ?? null,
                    weight: b.weight ?? null,
                    included: b.included !== false,
                })
            }
        }
    }

    const legs = []
    if (groups.outbound.length) {
        legs.push({ key: 'outbound', label: 'Outbound', icon: 'fa-plane-departure', bags: groups.outbound })
    }
    if (groups.inbound.length) {
        legs.push({ key: 'inbound', label: 'Inbound', icon: 'fa-plane-arrival', bags: groups.inbound })
    }
    // One-way / no direction tags — still show bags once
    if (!legs.length) {
        const all = []
        for (const list of Object.values(groups)) {
            for (const b of list) {
                if (!all.some((x) => x.type === b.type)) all.push(b)
            }
        }
        // Flatten from products without grouping if groups empty but products have bags
        if (!all.length) {
            for (const p of products) {
                for (const b of (p.baggage || [])) {
                    if (!all.some((x) => x.type === b.type)) {
                        all.push({
                            type: b.type,
                            label: b.label || (b.type === 'carry_on' ? 'Carry-On' : 'Checked'),
                            quantity: b.quantity ?? null,
                            weight: b.weight ?? null,
                            included: b.included !== false,
                        })
                    }
                }
            }
        }
        if (all.length) legs.push({ key: 'all', label: 'Allowance', icon: 'fa-suitcase', bags: all })
    }
    return legs
})

function bagDetail(bag) {
    if (bag.quantity && bag.weight) return `${bag.quantity}× ${bag.weight}`
    if (bag.weight) return bag.weight
    if (bag.quantity) return `${bag.quantity} pc`
    return '—'
}
</script>

<template>
    <div class="bts">
        <!-- Fare Summary — from saved price_breakdown -->
        <section class="bts-card bts-card--fare">
            <header class="bts-head">
                <span class="bts-ico bts-ico--fare"><i class="fa-brands fa-hive" /></span>
                <span class="bts-title">Fare Summary ({{ currency }})</span>
            </header>

            <div v-if="!price" class="bts-empty">No fare data</div>
            <template v-else>
                <div v-if="breakdownRows.length" class="bts-table-wrap">
                    <table class="bts-table">
                        <thead>
                            <tr>
                                <th>PAX</th>
                                <th class="bts-amt">Base Fare (/p)</th>
                                <th class="bts-amt">Tax (/p)</th>
                                <th class="bts-amt">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, i) in breakdownRows"
                                :key="i"
                                :class="`bts-tr--${row.tone}`"
                            >
                                <td>
                                    <span class="bts-pax" :class="`bts-pax--${row.tone}`">
                                        <i :class="row.icon" />
                                        {{ row.type }}
                                        <em>×{{ row.qty }}</em>
                                    </span>
                                </td>
                                <td class="bts-amt">{{ fmtAmt(row.base) }}</td>
                                <td class="bts-amt">{{ fmtAmt(row.tax) }}</td>
                                <td class="bts-amt bts-amt--strong">{{ fmtAmt(row.lineTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bts-totals">
                    <div class="bts-tot-row">
                        <span><i class="fa-solid fa-ticket bts-tot-ico bts-tot-ico--base" /> Base Fare</span>
                        <strong class="bts-amt">{{ fmtAmt(totals.base) }}</strong>
                    </div>
                    <div class="bts-tot-row">
                        <span><i class="fa-solid fa-landmark bts-tot-ico bts-tot-ico--tax" /> Tax</span>
                        <strong class="bts-amt">{{ fmtAmt(totals.tax) }}</strong>
                    </div>
                    <div class="bts-tot-row bts-tot-row--gross">
                        <span>Gross Fare</span>
                        <strong>{{ fmtMoney(totals.gross) }}</strong>
                    </div>
                </div>
            </template>
        </section>

        <!-- Baggage — from saved products[].baggage -->
        <section class="bts-card bts-card--bag">
            <header class="bts-head">
                <span class="bts-ico bts-ico--bag"><i class="fa-solid fa-suitcase-rolling" /></span>
                <span class="bts-title">Baggage Information</span>
            </header>

            <div v-if="!baggageLegs.length" class="bts-empty">No baggage info</div>
            <template v-else>
                <div v-for="leg in baggageLegs" :key="leg.key" class="bts-leg">
                    <div class="bts-leg-label" :class="leg.key === 'inbound' ? 'bts-leg-label--in' : ''">
                        <i class="fa-solid" :class="leg.icon" />
                        {{ leg.label }}
                    </div>
                    <table class="bts-bag-table">
                        <tbody>
                            <tr
                                v-for="bag in leg.bags"
                                :key="`${leg.key}-${bag.type}`"
                                :class="bag.type === 'carry_on' ? 'bts-bag--cabin' : 'bts-bag--check'"
                            >
                                <td class="bts-bag-ico">
                                    <i
                                        :class="bag.type === 'carry_on'
                                            ? 'bx bx-briefcase-alt-2'
                                            : 'bx bxs-briefcase'"
                                    />
                                </td>
                                <td class="bts-bag-name">{{ bag.label }}</td>
                                <td class="bts-bag-amt">{{ bagDetail(bag) }}</td>
                                <td class="bts-bag-status">
                                    <span v-if="bag.included" class="bts-pill bts-pill--ok">Incl.</span>
                                    <span v-else class="bts-pill bts-pill--fee">Fee</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.bts {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.bts-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.65rem 0.7rem 0.7rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 4px 14px rgba(2, 125, 226, 0.05);
}

.bts-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}

.bts-ico {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.bts-ico--fare {
    color: #7239ea;
    background: linear-gradient(135deg, rgba(114, 57, 234, 0.12), rgba(168, 85, 247, 0.1));
}

.bts-ico--bag {
    color: #027de2;
    background: linear-gradient(135deg, rgba(2, 125, 226, 0.12), rgba(56, 189, 248, 0.1));
}

.bts-title {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #334155;
}

.bts-table-wrap {
    overflow-x: auto;
    margin-bottom: 0.45rem;
}

.bts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.68rem;
}

.bts-table th {
    text-align: left;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-size: 0.58rem;
    padding: 0.15rem 0.25rem 0.35rem;
    border-bottom: 1px solid #e2e8f0;
}

.bts-table th.bts-amt,
.bts-table td.bts-amt {
    text-align: right;
}

.bts-table td {
    padding: 0.35rem 0.25rem;
    vertical-align: middle;
    color: #0f172a;
    border-bottom: 1px solid #f1f5f9;
    text-align: left;
}

.bts-table tbody tr:last-child td { border-bottom: none; }

.bts-amt {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    font-weight: 600;
}

.bts-amt--strong { font-weight: 800; color: #7239ea; }

.bts-pax {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 700;
    white-space: nowrap;
}

.bts-pax i { font-size: 0.65rem; }
.bts-pax em { font-style: normal; opacity: 0.65; font-weight: 600; }

.bts-pax--adult { color: #7239ea; }
.bts-pax--child { color: #027de2; }
.bts-pax--infant { color: #059669; }

.bts-tr--adult td:first-child { border-left: 2px solid #7239ea; padding-left: 0.35rem; }
.bts-tr--child td:first-child { border-left: 2px solid #027de2; padding-left: 0.35rem; }
.bts-tr--infant td:first-child { border-left: 2px solid #059669; padding-left: 0.35rem; }

.bts-totals {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding-top: 0.4rem;
    border-top: 1px dashed #e2e8f0;
}

.bts-tot-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.72rem;
    color: #64748b;
}

.bts-tot-row strong {
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.bts-tot-row--gross {
    margin-top: 0.15rem;
    padding-top: 0.35rem;
    border-top: 1px solid #e2e8f0;
    font-weight: 700;
    color: #334155;
}

.bts-tot-row--gross strong {
    font-size: 0.9rem;
    font-weight: 800;
    color: #7239ea;
}

.bts-tot-ico { width: 12px; text-align: center; margin-right: 0.2rem; }
.bts-tot-ico--base { color: #7239ea; }
.bts-tot-ico--tax { color: #f59e0b; }

.bts-leg + .bts-leg {
    margin-top: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px dashed #e2e8f0;
}

.bts-leg-label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #2563eb;
    margin-bottom: 0.3rem;
}

.bts-leg-label--in { color: #059669; }

.bts-bag-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.7rem;
}

.bts-bag-table td {
    padding: 0.3rem 0.25rem;
    vertical-align: middle;
}

.bts-bag-ico {
    width: 22px;
    font-size: 1rem;
}

.bts-bag--cabin .bts-bag-ico { color: #f59e0b; }
.bts-bag--cabin .bts-bag-name { color: #b45309; font-weight: 700; }
.bts-bag--check .bts-bag-ico { color: #027de2; }
.bts-bag--check .bts-bag-name { color: #1d4ed8; font-weight: 700; }

.bts-bag-amt {
    text-align: right;
    font-weight: 650;
    font-variant-numeric: tabular-nums;
    color: #475569;
    white-space: nowrap;
}

.bts-bag-status {
    text-align: right;
    width: 40px;
}

.bts-pill {
    display: inline-block;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
}

.bts-pill--ok {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.bts-pill--fee {
    background: rgba(245, 158, 11, 0.15);
    color: #b45309;
}

.bts-empty {
    font-size: 0.72rem;
    color: #94a3b8;
    text-align: center;
    padding: 0.4rem 0;
}

[data-bs-theme="dark"] .bts-card {
    background: var(--bs-card-bg);
    border-color: var(--bs-border-color);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}

[data-bs-theme="dark"] .bts-title,
[data-bs-theme="dark"] .bts-tot-row strong,
[data-bs-theme="dark"] .bts-table td {
    color: var(--bs-body-color);
}

[data-bs-theme="dark"] .bts-table th,
[data-bs-theme="dark"] .bts-totals,
[data-bs-theme="dark"] .bts-tot-row--gross,
[data-bs-theme="dark"] .bts-leg + .bts-leg {
    border-color: var(--bs-border-color);
}

[data-bs-theme="dark"] .bts-bag--cabin .bts-bag-name { color: #fbbf24; }
[data-bs-theme="dark"] .bts-bag--check .bts-bag-name { color: #7dd3fc; }
</style>
