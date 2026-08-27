<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { computed, reactive, ref } from 'vue';
import axiosInstance from '../../../axiosInstance';
import { runAction } from '../../../utils/runAction';
import { formatDate, formatDateTime, formatDuration, formatTime } from '../../../utils/dateUtils';
import { formatFareAmount } from '../../../utils/dynamicRulePricingDisplay';

const pnrInput = ref('');
const loading = ref(false);
const searched = ref(false);
const errorMsg = ref('');

const attempt = ref(null);
const price = ref(null);
const travelers = ref([]);
const fareRuleSegments = ref([]);
const activityLogs = ref([]);

const open = reactive({
    route: true,
    flight: true,
    passengers: true,
    fareDetails: true,
    fareRules: true,
    activity: false,
});
function toggleSection(key) {
    open[key] = !open[key];
}

const currency = computed(() => price.value?.currency ?? 'BDT');
function fmtMoney(n) {
    const num = Number(n);
    if (Number.isNaN(num)) return '—';
    return `${currency.value} ${formatFareAmount(num)}`;
}

async function onSearch() {
    const pnr = pnrInput.value.trim();
    if (!pnr) {
        errorMsg.value = 'Enter a GDS or Airline PNR.';
        return;
    }

    await runAction(async () => {
        try {
            const res = await axiosInstance.get('v2/pnr/search', { params: { pnr } });
            const data = res.data?.data ?? {};
            attempt.value = data.attempt ?? null;
            price.value = data.price ?? null;
            travelers.value = data.travelers ?? [];
            fareRuleSegments.value = data.fare_rules_segments ?? [];
            activityLogs.value = data.activity_logs ?? [];
            errorMsg.value = '';
        } catch (e) {
            attempt.value = null;
            price.value = null;
            travelers.value = [];
            fareRuleSegments.value = [];
            activityLogs.value = [];
            // errorMsg.value = e?.response?.data?.message ?? 'No booking found for this PNR.';
        } finally {
            searched.value = true;
        }
    }, { setLoading: (v) => (loading.value = v) });
}

function onInputKeyup(e) {
    if (e.key === 'Enter') onSearch();
}

// ── Passenger Details ───────────────────────────────────────────────
const PAX_BADGE = { ADT: 'ADT', CNN: 'CHD', KID: 'CHD', INF: 'INF', INS: 'INF' };

const passengers = computed(() =>
    travelers.value.map((t) => {
        const phone = t.phone;
        const email = t.email;
        let contact = '—';
        if (phone && email) contact = `${email}\n${phone}`;
        else if (phone) contact = phone;
        else if (email) contact = email;

        return {
            name: t.name || '—',
            gender: t.gender ?? '—',
            paxType: PAX_BADGE[t.pax_type] ?? (t.pax_type || 'ADT'),
            dob: t.dob ? formatDate(t.dob) : '—',
            passport: t.passport_no || '—',
            contact,
        };
    }),
);

// ── Fare Details ────────────────────────────────────────────────────
const paxBreakdown = computed(() =>
    (price.value?.price_breakdown ?? []).map((bd) => ({
        type: bd.type,
        quantity: Number(bd.quantity ?? 1),
        baseFare: Number(bd.base_fare ?? 0) * Number(bd.quantity ?? 1),
        tax: Number(bd.total_taxes ?? 0) * Number(bd.quantity ?? 1),
        total: Number(bd.total_price ?? 0) * Number(bd.quantity ?? 1),
    })),
);

const fareTotals = computed(() => ({
    baseFare: Number(price.value?.base_fare ?? 0),
    tax: Number(price.value?.total_taxes ?? 0),
    grossFare: Number(price.value?.total_price ?? 0),
}));

function paxTone(type) {
    if (type === 'Child' || type === 'Kids') return 'child';
    if (type === 'Infant' || type === 'Infant (seat)') return 'infant';
    return 'adult';
}

// ── Flight Details — built from the priced products (outbound/inbound) ──
function titleCaseCity(name) {
    const s = String(name ?? '').trim();
    if (!s) return '';
    if (s.length <= 3 && s === s.toUpperCase()) return s;
    return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatReviewDate(date) {
    if (!date) return '';
    const d = new Date(`${date}T00:00:00`);
    if (Number.isNaN(d.getTime())) return date;
    const day = d.getDate();
    const month = d.toLocaleDateString('en-US', { month: 'long' });
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' });
    return `${day} ${month}, ${weekday}`;
}

function formatLegDate(date) {
    if (!date) return '';
    const d = new Date(`${date}T00:00:00`);
    if (Number.isNaN(d.getTime())) return date;
    const day = d.getDate();
    const month = d.toLocaleDateString('en-US', { month: 'short' });
    const year = d.getFullYear();
    const weekday = d.toLocaleDateString('en-US', { weekday: 'long' });
    return `${day} ${month},${year}, ${weekday}`;
}

function carryOnLabel(allowance) {
    const bag = (allowance ?? []).find((b) => b.type === 'carry_on');
    return bag ? `Cabin-${bag.quantity ?? 1}pc` : '—';
}

function checkedLabel(allowance) {
    const bag = (allowance ?? []).find((b) => b.type === 'checked');
    return bag?.weight ? `Check In- ${bag.weight}/person` : '—';
}

function productsToSegments(products) {
    if (!Array.isArray(products)) return [];
    return products.map((p) => {
        const f = p.flight ?? {};
        const dep = f.departure ?? {};
        const arrival = f.arrival ?? {};
        return {
            airline_name: f.carrier ?? '—',
            flight_number: `${f.carrier ?? ''}${f.number ?? ''}`,
            equipment: f.equipment ?? '—',
            cabin_class: p.cabin ?? '',
            booking_code: p.class_of_service ?? '',
            departure_code: dep.location ?? '—',
            arrival_code: arrival.location ?? '—',
            departure_time: formatTime(dep.date, dep.time),
            departure_date: dep.date ?? '',
            arrival_time: formatTime(arrival.date, arrival.time),
            arrival_date: arrival.date ?? '',
            originTerminal: dep.terminal ?? '—',
            destinationTerminal: arrival.terminal ?? '—',
            stops: f.stops ?? 0,
            flightTime1: formatDuration(p.total_duration) || '—',
        };
    });
}

function productsByDirection(products) {
    const outbound = [];
    const inbound = [];
    (products ?? []).forEach((p, i) => {
        if (p.direction === 'inbound') inbound.push(p);
        else if (p.direction === 'outbound') outbound.push(p);
        else if (i === 0) outbound.push(p);
        else inbound.push(p);
    });
    return { outbound, inbound };
}

function refundLabel(direction) {
    const seg = fareRuleSegments.value.find((s) => s.direction === direction);
    if (!seg || !seg.cancellation?.length) return null;
    const freeCancel = seg.cancellation.some((c) => c.permitted && (!c.amount || Number(c.amount.value) === 0));
    return freeCancel ? 'Refundable' : 'Non Refundable';
}

function buildLeg(key, label, icon, products) {
    const segments = productsToSegments(products).map((seg, i, arr) => ({ ...seg, lastitem: i === arr.length - 1 }));
    const stopCount = Math.max(...segments.map((s) => s.stops ?? 0), 0);
    return {
        key,
        label,
        icon,
        duration: segments[0]?.flightTime1 && segments[0].flightTime1 !== '—' ? segments[0].flightTime1 : '—',
        stopLabel: stopCount === 0 ? 'Non-stop' : `${stopCount} Stop${stopCount > 1 ? 's' : ''}`,
        refundable: refundLabel(key),
        fareBasisCode: products[0]?.fare_basis_code ?? '',
        baggageAllowance: products[0]?.baggage ?? [],
        segments,
    };
}

const journeyLegs = computed(() => {
    const { outbound, inbound } = productsByDirection(price.value?.products ?? []);
    const legs = [];
    if (outbound.length) legs.push(buildLeg('outbound', 'Outbound', 'fa-solid fa-plane-departure', outbound));
    if (inbound.length) legs.push(buildLeg('inbound', 'Return', 'fa-solid fa-plane-arrival', inbound));
    return legs;
});

const hasFlights = computed(() => journeyLegs.value.some((leg) => leg.segments.length > 0));

// ── Fare Rules ──────────────────────────────────────────────────────
function formatRuleTiming(timing) {
    if (!timing) return '—';
    return String(timing).replace(/([a-z])([A-Z])/g, '$1 $2').replace(/_/g, ' ');
}

function formatRuleAmount(amount) {
    if (!amount) return '—';
    const code = amount.code ?? '';
    const value = Number(amount.value ?? 0);
    return `${code} ${value.toLocaleString()}`.trim();
}

function ruleCardLabel(seg) {
    const leg = journeyLegs.value.find((l) => l.key === seg.direction);
    if (leg?.segments?.length) {
        const first = leg.segments[0];
        const last = leg.segments[leg.segments.length - 1];
        return `${first.departure_code}→${last.arrival_code}`;
    }
    return seg.flightRef || 'Segment';
}

// ── Top card — route / status / meta ─────────────────────────────────
const routeSummary = computed(() =>
    journeyLegs.value
        .filter((leg) => leg.segments.length)
        .map((leg) => `${leg.segments[0].departure_code} - ${leg.segments[leg.segments.length - 1].arrival_code}`)
        .join('  |  '),
);

const issueDate = computed(() => attempt.value?.ticketed_at || attempt.value?.confirmed_at || null);

function statusLabel(status) {
    if (status == 'committed') return 'Booked';
    return actionLabel(status);
}

const shareCopied = ref(false);
async function onShare() {
    const text = `PNR ${attempt.value?.gds_pnr || attempt.value?.airline_pnr || ''} — ${routeSummary.value}`.trim();
    try {
        await navigator.clipboard.writeText(text);
        shareCopied.value = true;
        setTimeout(() => (shareCopied.value = false), 1500);
    } catch {
        // clipboard unavailable — silently ignore
    }
}

function onPrint() {
    const prevOpen = { ...open };
    Object.keys(open).forEach((key) => (open[key] = true));

    function restore() {
        Object.assign(open, prevOpen);
        window.removeEventListener('afterprint', restore);
    }
    window.addEventListener('afterprint', restore);

    window.print();
}

// ── Activity Log ────────────────────────────────────────────────────
function actionLabel(actionType) {
    if (!actionType) return '—';
    return String(actionType).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const activityColumns = [
    { field: 'action_type', title: 'Action', sort: false },
    { field: 'user_name', title: 'User', sort: false },
    { field: 'status_change', title: 'Status', sort: false },
    { field: 'created_at', title: 'Time', sort: false },
];

const activityRows = computed(() =>
    activityLogs.value.map((log, i) => ({
        id: log.id ?? i,
        action_type: actionLabel(log.action_type),
        user_name: log.user_name || '—',
        status_change: `${log.status_before ?? '—'} → ${log.status_after ?? '—'}`,
        created_at: formatDateTime(log.created_at) || '—',
    })),
);
</script>

<template>
    <div class="container-fluid px-2 px-md-3">
        <AppBreadcrumbs title="Flight PNR" :back-to="{ name: 'apiManagement' }" :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Flight PNR' },
        ]" />

        <div class="pnr-print-area">
            <!-- PNR Form Card -->
            <div class="row">
                <div class="col-12">
                    <div class="pnr-card">
                        <div class="section-heading">
                            <div class="section-heading-left">
                                <span class="bar-blue"></span> Flight PNR
                            </div>
                            <button v-if="searched && !loading && attempt" type="button"
                                class="btn-print-link pnr-print-hide" @click="onPrint">
                                <i class="fa-solid fa-print" aria-hidden="true" /> Print
                            </button>
                        </div>

                        <div class="pnr-form-row pnr-print-hide">
                            <label class="form-label-custom">PNR</label>
                            <div class="pnr-input-group">
                                <input v-model="pnrInput" type="text" class="input-custom pnr-input"
                                    placeholder="Enter GDS or Airline PNR" @keyup="onInputKeyup" />
                                <button type="button" class="btn-check-pnr" :disabled="loading" @click="onSearch">
                                    <span v-if="loading" class="spinner-border spinner-border-sm me-1" role="status"
                                        aria-hidden="true"></span>
                                    Check
                                </button>
                            </div>
                            <div v-if="errorMsg" class="pnr-error">{{ errorMsg }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Result segment — hidden until a search has been made -->
            <div v-if="searched && !loading && attempt" class="row mt-3 ps-result-row g-3">
                <div class="col-lg-8 d-flex flex-column gap-3">
                    <section class="ps-section">
                        <button type="button" class="ps-section__head" @click="toggleSection('route')">
                            <span>Route Details</span>
                            <i class="fa-solid" :class="open.route ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>
                        <div v-show="open.route" class="ps-section__body">
                            <div class="pnr-route-row">
                                <div class="pnr-route-text">{{ routeSummary || '—' }}</div>
                                <button type="button" class="btn-share-link pnr-print-hide" @click="onShare">
                                    <!-- <i class="fa-solid fa-paper-plane" aria-hidden="true" /> {{ shareCopied ? 'Copied' :
                                    'Share' }} -->
                                </button>
                                <span class="pnr-status-pill" :class="`pnr-status-pill--${attempt.status}`">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true" /> {{
                                        statusLabel(attempt.status) }}
                                </span>
                            </div>

                            <div class="pnr-meta-row">
                                <div class="pnr-meta-item" v-if="attempt.agency_name">
                                    <span class="pnr-meta-label">Agency</span>
                                    <span class="pnr-meta-value">{{ attempt.agency_name || '—' }}</span>
                                </div>
                                <div class="pnr-meta-item">
                                    <span class="pnr-meta-label">Booking Id</span>
                                    <span class="pnr-meta-value">{{ attempt.booking_code || '—' }}</span>
                                </div>
                                <div class="pnr-meta-item">
                                    <span class="pnr-meta-label">GDS PNR</span>
                                    <span class="pnr-meta-value">{{ attempt.gds_pnr || '—' }}</span>
                                </div>
                                <div class="pnr-meta-item">
                                    <span class="pnr-meta-label">Airline PNR</span>
                                    <span class="pnr-meta-value">{{ attempt.airline_pnr || '—' }}</span>
                                </div>
                                <div class="pnr-meta-item">
                                    <span class="pnr-meta-label">Issue Date</span>
                                    <span class="pnr-meta-value">{{ formatDate(issueDate) || '—' }}</span>
                                </div>
                            </div>

                            <div v-if="attempt.commit_error" class="pnr-card-error">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" /> {{
                                    attempt.commit_error }}
                            </div>
                        </div>
                    </section>
                    <!-- Flight Details -->
                    <section class="ps-section">
                        <button type="button" class="ps-section__head" @click="toggleSection('flight')">
                            <span>Flight Details</span>
                            <i class="fa-solid" :class="open.flight ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>

                        <div v-show="open.flight" class="ps-section__body ps-section__body--flush">
                            <div v-if="!hasFlights" class="ps-empty">Flight details unavailable.</div>

                            <div v-for="leg in journeyLegs" :key="leg.key" class="ps-leg">
                                <div class="ps-leg__head">
                                    <span class="ps-leg__title"><i :class="leg.icon" aria-hidden="true" /> {{ leg.label
                                    }} Flight</span>
                                    <span class="ps-leg__sep">|</span>
                                    <span class="ps-leg__route" v-if="leg.segments.length">
                                        {{ titleCaseCity(leg.segments[0].departure_code) }}
                                        <i class="fa-solid fa-arrow-right" aria-hidden="true" />
                                        {{ titleCaseCity(leg.segments[leg.segments.length - 1].arrival_code) }}
                                    </span>
                                    <span class="ps-leg__sep">|</span>
                                    <span class="ps-leg__meta"><i class="fa-solid fa-calendar" aria-hidden="true" /> {{
                                        formatLegDate(leg.segments[0]?.departure_date) }}</span>
                                    <span class="ps-leg__sep">|</span>
                                    <span class="ps-leg__meta"><i class="fa-solid fa-clock" aria-hidden="true" /> {{
                                        leg.duration }}</span>
                                    <span v-if="leg.refundable" class="ps-leg__nonref">{{ leg.refundable }}</span>
                                </div>

                                <article v-for="(seg, idx) in leg.segments" :key="`${leg.key}-${idx}`" class="ps-seg">
                                    <div class="ps-seg__airline">
                                        <div class="ps-seg__logo ps-seg__logo--ph"><i class="fa-solid fa-plane"
                                                aria-hidden="true" /></div>
                                        <div>
                                            <div class="ps-seg__airline-name">{{ seg.airline_name }}</div>
                                            <div class="ps-seg__flightno">{{ seg.flight_number }}</div>
                                            <div class="ps-seg__aircraft">{{ seg.equipment }}</div>
                                            <div class="ps-seg__operator">Operated By {{ seg.airline_name }}</div>
                                        </div>
                                    </div>

                                    <div class="ps-seg__point">
                                        <div class="ps-seg__code">{{ seg.departure_code }}</div>
                                        <div class="ps-seg__time">
                                            <span class="ps-seg__time-val">{{ seg.departure_time || '—' }}</span>
                                            <span class="ps-seg__time-sep">|</span>
                                            <span class="ps-seg__time-date">{{ formatReviewDate(seg.departure_date)
                                            }}</span>
                                        </div>
                                        <div class="ps-seg__terminal">Terminal: {{ seg.originTerminal }}</div>
                                    </div>

                                    <div class="ps-seg__mid">
                                        <div class="ps-seg__dur-text">{{ seg.flightTime1 }}</div>
                                        <div class="ps-seg__track">
                                            <span class="ps-seg__dot" />
                                            <span class="ps-seg__line"><i class="fa-solid fa-plane ps-seg__plane-ico"
                                                    aria-hidden="true" /></span>
                                            <span class="ps-seg__dot ps-seg__dot--arr" />
                                        </div>
                                        <div class="ps-seg__stoptype">{{ leg.stopLabel }}</div>
                                    </div>

                                    <div class="ps-seg__point ps-seg__point--arr">
                                        <div class="ps-seg__code">{{ seg.arrival_code }}</div>
                                        <div class="ps-seg__time">
                                            <span class="ps-seg__time-val">{{ seg.arrival_time || '—' }}</span>
                                            <span class="ps-seg__time-sep">|</span>
                                            <span class="ps-seg__time-date">{{ formatReviewDate(seg.arrival_date)
                                            }}</span>
                                        </div>
                                        <div class="ps-seg__terminal">Terminal: {{ seg.destinationTerminal }}</div>
                                    </div>

                                    <div class="ps-seg__info">
                                        <span class="ps-seg__info-cabin">Cabin: {{ seg.cabin_class || 'Economy' }}, RBD:
                                            {{ seg.booking_code
                                                || '—' }}</span>
                                        <span class="ps-seg__info-sep">|</span>
                                        <span class="ps-seg__info-fare">Fare Basis: {{ leg.fareBasisCode || '—'
                                        }}</span>
                                        <span class="ps-seg__info-sep">|</span>
                                        <span class="ps-seg__info-bag">Baggage: {{ carryOnLabel(leg.baggageAllowance)
                                        }}, {{
                                                checkedLabel(leg.baggageAllowance) }}</span>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <!-- Passenger Details -->
                    <section class="ps-section">
                        <button type="button" class="ps-section__head" @click="toggleSection('passengers')">
                            <span>Passenger Details</span>
                            <i class="fa-solid" :class="open.passengers ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>

                        <div v-show="open.passengers" class="ps-section__body ps-section__body--flush">
                            <div class="table-responsive">
                                <table class="ps-pax-table">
                                    <thead>
                                        <tr>
                                            <th>Passenger</th>
                                            <th>Date of Birth</th>
                                            <th>Passport</th>
                                            <th>Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, pi) in passengers" :key="pi">
                                            <td>
                                                <div class="ps-pax__info">
                                                    <span>{{ p.name }}</span>
                                                    <span class="ps-pax__gender">
                                                        <span class="ps-pax__type-badge"
                                                            :class="`ps-pax__type-badge--${p.paxType.toLowerCase()}`">{{
                                                                p.paxType }}</span>
                                                        {{ p.gender }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>{{ p.dob }}</td>
                                            <td>{{ p.passport }}</td>
                                            <td class="ps-pax__contact">{{ p.contact }}</td>
                                        </tr>
                                        <tr v-if="!passengers.length">
                                            <td colspan="4" class="ps-empty">No passengers recorded.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Fare Details -->
                    <section class="ps-section">
                        <button type="button" class="ps-section__head" @click="toggleSection('fareDetails')">
                            <span>Fare Details</span>
                            <i class="fa-solid" :class="open.fareDetails ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>

                        <div v-show="open.fareDetails" class="ps-section__body ps-section__body--flush">
                            <div class="ps-fd">
                                <div class="table-responsive ps-fd__table-wrap">
                                    <table class="ps-fd-table">
                                        <thead>
                                            <tr>
                                                <th>PAX</th>
                                                <th>Base Fare</th>
                                                <th>Tax</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="bd in paxBreakdown" :key="bd.type"
                                                :class="`ps-fd-row--${paxTone(bd.type)}`">
                                                <td>{{ bd.type }} × {{ bd.quantity }}</td>
                                                <td>{{ fmtMoney(bd.baseFare) }}</td>
                                                <td>{{ fmtMoney(bd.tax) }}</td>
                                                <td>{{ fmtMoney(bd.total) }}</td>
                                            </tr>
                                            <tr v-if="!paxBreakdown.length">
                                                <td colspan="4" class="ps-empty">Fare breakdown unavailable.</td>
                                            </tr>
                                        </tbody>
                                        <tfoot v-if="paxBreakdown.length">
                                            <tr>
                                                <td>Total</td>
                                                <td>{{ fmtMoney(fareTotals.baseFare) }}</td>
                                                <td>{{ fmtMoney(fareTotals.tax) }}</td>
                                                <td>{{ fmtMoney(fareTotals.grossFare) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="ps-fd__gross">
                                    <span class="ps-fd__gross-label">Gross Fare</span>
                                    <span class="ps-fd__gross-val">{{ fmtMoney(fareTotals.grossFare) }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Fare Rules -->
                    <section class="ps-section">
                        <button type="button" class="ps-section__head" @click="toggleSection('fareRules')">
                            <span>Fare Rules</span>
                            <i class="fa-solid" :class="open.fareRules ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>

                        <div v-show="open.fareRules" class="ps-section__body">
                            <div class="ps-rules-note">
                                <i class="fa-solid fa-circle-info" aria-hidden="true" />
                                <span>Based on the fare rule policy, the pricing and schedule of the flight can be
                                    changed.</span>
                            </div>

                            <div v-if="!fareRuleSegments.length" class="ps-empty">No structured fare rules recorded for
                                this booking.</div>

                            <article v-for="(seg, sIdx) in fareRuleSegments"
                                :key="`${seg.direction}-${seg.flightRef}-${sIdx}`" class="ps-rule-card">
                                <header class="ps-rule-card__head">
                                    <i class="fa-solid fa-plane" aria-hidden="true" />
                                    <span>{{ ruleCardLabel(seg) }}</span>
                                    <span class="ps-rule-dir">{{ seg.direction === 'inbound' ? 'Return' : 'Outbound'
                                    }}</span>
                                </header>

                                <div class="ps-rule-tables">
                                    <div class="ps-rule-block">
                                        <div class="ps-rule-block__title"><i class="fa-solid fa-ban"
                                                aria-hidden="true" /> Cancellation
                                        </div>
                                        <div v-if="!seg.cancellation?.length" class="ps-rule-empty">No data</div>
                                        <table v-else class="ps-rule-table">
                                            <thead>
                                                <tr>
                                                    <th>Condition</th>
                                                    <th>Status</th>
                                                    <th>Charge</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(c, ci) in seg.cancellation" :key="ci">
                                                    <td>{{ formatRuleTiming(c.timing) }}</td>
                                                    <td><span class="ps-rule-chip"
                                                            :class="c.permitted ? 'ps-rule-chip--ok' : 'ps-rule-chip--no'">{{
                                                                c.permitted ?
                                                                    'Permitted' : 'Not Permitted' }}</span></td>
                                                    <td>{{ formatRuleAmount(c.amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="ps-rule-block">
                                        <div class="ps-rule-block__title"><i class="fa-solid fa-arrow-right-arrow-left"
                                                aria-hidden="true" /> Changes</div>
                                        <div v-if="!seg.changes?.length" class="ps-rule-empty">No data</div>
                                        <table v-else class="ps-rule-table">
                                            <thead>
                                                <tr>
                                                    <th>Condition</th>
                                                    <th>Status</th>
                                                    <th>Charge</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(c, ci) in seg.changes" :key="ci">
                                                    <td>{{ formatRuleTiming(c.timing) }}</td>
                                                    <td><span class="ps-rule-chip"
                                                            :class="c.permitted ? 'ps-rule-chip--ok' : 'ps-rule-chip--no'">{{
                                                                c.permitted ?
                                                                    'Permitted' : 'Not Permitted' }}</span></td>
                                                    <td>{{ formatRuleAmount(c.amount) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <!-- Activity Log -->
                    <section class="ps-section pnr-print-hide">
                        <button type="button" class="ps-section__head" @click="toggleSection('activity')">
                            <span>Activity Log</span>
                            <i class="fa-solid" :class="open.activity ? 'fa-chevron-up' : 'fa-chevron-down'"
                                aria-hidden="true" />
                        </button>

                        <div v-show="open.activity" class="ps-section__body">
                            <DataTable table-id="pnr-search-activity-log" :rows="activityRows"
                                :columns="activityColumns" :striped="false" :loading="false" :sortable="false"
                                :page-size="10" search-placeholder="Search activity"
                                empty-state-text="No activity recorded" no-match-text="No matching entries" />
                        </div>
                    </section>
                </div>

                <!-- Fare summary -->
                <div class="col-lg-4 pnr-print-hide">
                    <div class="ps-summary">
                        <div class="ps-summary__row"><span><i class="fa-solid fa-file-invoice" aria-hidden="true" />
                                Total Base
                                Fare</span><strong>{{ fmtMoney(fareTotals.baseFare) }}</strong></div>
                        <div class="ps-summary__row"><span><i class="fa-solid fa-landmark" aria-hidden="true" /> Total
                                Tax</span><strong>{{ fmtMoney(fareTotals.tax) }}</strong></div>
                        <div class="ps-summary__row"><span><i class="fa-solid fa-receipt" aria-hidden="true" /> Gross
                                Fare</span>
                            <!-- <strong>{{ fmtMoney(fareTotals.grossFare) }}</strong> -->
                            <strong>{{ fmtMoney(fareTotals.tax + fareTotals.baseFare) }}</strong>
                        </div>
                        <div class="ps-summary__divider"></div>
                        <!-- <div class="ps-summary__row ps-summary__row--payable"><span><i class="fa-solid fa-wallet"
                                    aria-hidden="true" />
                                PAX Payable</span><strong>{{ fmtMoney(fareTotals.grossFare) }}</strong></div> -->
                    </div>
                </div>
            </div>

            <div v-else-if="searched && !loading && !attempt" class="row mt-3">
                <div class="col-12">
                    <div class="ps-notfound">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true" />
                        <span>{{ errorMsg || 'No booking found for this PNR.' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Base Styles */
.pnr-card {
    background: #fff;
    border-radius: 12px;
    border: 1.5px solid #e0e4f0;
    padding: 20px 16px 24px;
    margin: 0;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.section-heading-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
}

.bar-blue {
    width: 5px;
    height: 22px;
    border-radius: 3px;
    background: linear-gradient(180deg, #5b8cf7, #9b59f7);
    flex-shrink: 0;
}

.pnr-form-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label-custom {
    font-size: 13px;
    font-weight: 600;
    color: #222;
    margin-bottom: 6px;
    display: block;
}

.pnr-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.pnr-input {
    flex: 1 1 auto;
    min-width: 200px;
    border: 1.5px solid #e2e5f0;
    border-radius: 7px;
    padding: 11px 16px;
    font-size: 13px;
    color: #333;
    background: #fff;
    outline: none;
    transition: border-color 0.2s;
    height: 44px;
}

.pnr-input:focus {
    border-color: #7c3aed;
}

.pnr-input::placeholder {
    color: #b0b4c4;
}

.btn-check-pnr {
    background: #3b79f2;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 11px 32px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
    height: 44px;
    min-width: 100px;
    flex-shrink: 0;
}

.btn-check-pnr:hover:not(:disabled) {
    background: #2c6bfa;
}

.btn-check-pnr:active:not(:disabled) {
    transform: scale(0.98);
}

.btn-check-pnr:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.pnr-error {
    color: #dc2626;
    font-size: 12.5px;
    margin-top: 4px;
}

/* ── Top card — print link ──────────────────────────────────────── */
.btn-print-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    padding: 0;
    color: #3b79f2;
    font-size: 13px;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}

.btn-print-link:hover {
    color: #2c6bfa;
}

/* ── Top card — route / share / status row ────────────────────────── */
.pnr-route-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.pnr-route-text {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a2e;
}

.btn-share-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    padding: 0;
    color: #3b79f2;
    font-size: 13px;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}

.btn-share-link:hover {
    color: #2c6bfa;
}

.pnr-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    text-transform: capitalize;
    background: #eef0f6;
    color: #475569;
}

.pnr-status-pill--confirmed,
.pnr-status-pill--ticketed,
.pnr-status-pill--committed {
    background: #e7f7ee;
    color: #1a1a2e;
}

.pnr-status-pill--confirmed i,
.pnr-status-pill--ticketed i,
.pnr-status-pill--committed i {
    color: #16a34a;
}

.pnr-status-pill--cancelled,
.pnr-status-pill--voided,
.pnr-status-pill--failed {
    background: #fdecec;
    color: #7f1d1d;
}

.pnr-status-pill--cancelled i,
.pnr-status-pill--voided i,
.pnr-status-pill--failed i {
    color: #dc2626;
}

/* ── Top card — meta grid ──────────────────────────────────────────── */
.pnr-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px 40px;
    margin-top: 18px;
}

.pnr-meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 8rem;
}

.pnr-meta-label {
    font-size: 12px;
    color: #94a3b8;
}

.pnr-meta-value {
    font-size: 14px;
    font-weight: 700;
    color: #1a1a2e;
}

.pnr-card-error {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 16px;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fef2f2;
    color: #dc2626;
    font-size: 13px;
}

/* Mobile — stack route/share/status */
@media screen and (max-width: 640px) {
    .pnr-route-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .pnr-status-pill {
        margin-left: 0;
    }
}

/* ── Result sections ─────────────────────────────────────────────── */
.ps-result-row {
    --ps-primary: #027de2;
    --ps-teal: #0fb3a6;
    --ps-purple: #7239ea;
    --ps-surface: #ffffff;
    --ps-border: #e2e8f0;
    --ps-soft: #eef1f6;
    --ps-header: #e2e8f0;
    --ps-seg-tint: #f7f9fe;
    --ps-muted: #64748b;
    --ps-text: #0f172a;
    --ps-sub: #475569;
    --ps-radius: 12px;
    --ps-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 6px 20px rgba(2, 125, 226, 0.07);
}

.ps-notfound {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 1.25rem 1.5rem;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: var(--ps-radius, 12px);
    color: #9a3412;
    font-size: 0.9rem;
}

.ps-section {
    background: var(--ps-surface);
    border: 1px solid var(--ps-border);
    border-radius: var(--ps-radius);
    box-shadow: var(--ps-shadow);
    overflow: hidden;
}

.ps-section__head {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--ps-header);
    border: none;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--ps-text);
    cursor: pointer;
}

.ps-section__head i {
    color: var(--ps-muted);
    font-size: 0.8rem;
}

.ps-section__body {
    padding: 1rem 1.25rem;
}

.ps-section__body--flush {
    padding: 0;
}

.ps-empty {
    padding: 1.5rem 1rem;
    text-align: center;
    color: var(--ps-muted);
    font-size: 0.9rem;
}

/* ── Leg / segment ──────────────────────────────────────────────── */
.ps-leg:not(:last-child) {
    border-bottom: 1px solid var(--ps-border);
}

.ps-leg__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    row-gap: 0.35rem;
    column-gap: 0.6rem;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid var(--ps-border);
}

.ps-leg__title {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ps-primary);
    flex-shrink: 0;
}

.ps-leg__sep {
    color: var(--ps-border);
    flex-shrink: 0;
}

.ps-leg__route {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ps-text);
}

.ps-leg__route i {
    font-size: 0.7rem;
    color: var(--ps-muted);
}

.ps-leg__meta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.68rem;
    color: var(--ps-sub);
}

.ps-leg__nonref {
    margin-left: auto;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--ps-primary);
    background: rgba(2, 125, 226, 0.08);
    border: 1px solid rgba(2, 125, 226, 0.2);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    flex-shrink: 0;
}

.ps-seg {
    padding: 1rem 1.25rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.6rem;
}

@media (min-width: 992px) {
    .ps-seg {
        grid-template-columns: 165px 1fr 150px 1fr;
        row-gap: 0.6rem;
        column-gap: 0.75rem;
        align-items: start;
    }

    .ps-seg__airline {
        grid-column: 1;
        grid-row: 1 / 3;
    }

    .ps-seg__point {
        grid-column: 2;
        grid-row: 1;
    }

    .ps-seg__mid {
        grid-column: 3;
        grid-row: 1;
    }

    .ps-seg__point--arr {
        grid-column: 4;
        grid-row: 1;
    }

    .ps-seg__info {
        grid-column: 2 / 5;
        grid-row: 2;
    }
}

.ps-seg__airline {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    background: var(--ps-seg-tint);
    border-radius: 6px;
    padding: 0.6rem 0.65rem;
}

.ps-seg__logo {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    border: 1px solid var(--ps-border);
    background: var(--ps-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ps-seg__logo--ph {
    color: var(--ps-primary);
    font-size: 1rem;
}

.ps-seg__airline-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ps-text);
}

.ps-seg__flightno {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--ps-primary);
}

.ps-seg__aircraft,
.ps-seg__operator {
    font-size: 0.7rem;
    color: var(--ps-muted);
}

.ps-seg__code {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--ps-text);
}

.ps-seg__time {
    font-size: 0.82rem;
    margin-top: 0.3rem;
}

.ps-seg__time-val {
    font-weight: 700;
    color: var(--ps-text);
}

.ps-seg__time-sep {
    color: var(--ps-border);
    margin: 0 0.15rem;
}

.ps-seg__time-date {
    color: var(--ps-sub);
}

.ps-seg__terminal {
    font-size: 0.74rem;
    color: var(--ps-muted);
    margin-top: 0.15rem;
}

.ps-seg__mid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0;
}

.ps-seg__dur-text {
    font-size: 0.76rem;
    font-weight: 600;
    color: var(--ps-sub);
    white-space: nowrap;
}

.ps-seg__track {
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 110px;
}

.ps-seg__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--ps-primary);
    flex-shrink: 0;
}

.ps-seg__dot--arr {
    background: #94a3b8;
}

.ps-seg__line {
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, var(--ps-primary), #94a3b8);
    position: relative;
    margin: 0 2px;
}

.ps-seg__plane-ico {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.6rem;
    color: var(--ps-primary);
    background: var(--ps-surface);
    padding: 0 3px;
}

.ps-seg__stoptype {
    font-size: 0.7rem;
    color: var(--ps-muted);
    white-space: nowrap;
}

.ps-seg__info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem;
    background: var(--ps-soft);
    border-radius: 4px;
    font-size: 0.7rem;
}

.ps-seg__info-cabin {
    color: var(--ps-teal);
    font-weight: 600;
}

.ps-seg__info-fare {
    color: var(--ps-purple);
    font-weight: 600;
}

.ps-seg__info-bag {
    color: #6059d8;
    font-weight: 600;
}

.ps-seg__info-sep {
    color: var(--ps-muted);
}

/* ── Passenger table ────────────────────────────────────────────── */
.ps-pax-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
}

.ps-pax-table thead th {
    background: var(--ps-soft);
    color: var(--ps-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid var(--ps-border);
    text-align: left;
}

.ps-pax-table tbody td {
    padding: 0.8rem 1rem;
    border-bottom: 1px solid var(--ps-border);
    vertical-align: middle;
    color: var(--ps-text);
}

.ps-pax-table tbody tr:last-child td {
    border-bottom: none;
}

.ps-pax__info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.ps-pax__gender {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    color: var(--ps-muted);
}

.ps-pax__type-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.1rem 0.5rem;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
}

.ps-pax__type-badge--adt {
    background: rgba(2, 125, 226, 0.12);
    color: var(--ps-primary);
}

.ps-pax__type-badge--chd {
    background: rgba(114, 57, 234, 0.12);
    color: var(--ps-purple);
}

.ps-pax__type-badge--inf {
    background: rgba(15, 179, 166, 0.12);
    color: var(--ps-teal);
}

.ps-pax__contact {
    font-size: 0.78rem;
    white-space: pre-line;
}

/* ── Fare details ───────────────────────────────────────────────── */
.ps-fd {
    display: flex;
    flex-wrap: wrap;
}

.ps-fd__table-wrap {
    flex: 1 1 380px;
}

.ps-fd-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
}

.ps-fd-table thead th {
    background: var(--ps-soft);
    color: var(--ps-muted);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid var(--ps-border);
    text-align: left;
}

.ps-fd-table tbody td,
.ps-fd-table tfoot td {
    padding: 0.7rem 1.25rem;
    border-bottom: 1px solid var(--ps-border);
    color: var(--ps-text);
}

.ps-fd-table tfoot td {
    font-weight: 700;
    background: var(--ps-soft);
    border-bottom: none;
}

.ps-fd__gross {
    flex: 0 0 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    text-align: center;
    padding: 1rem 1.25rem;
    border-left: 1px solid var(--ps-border);
    background: var(--ps-seg-tint);
}

.ps-fd__gross-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ps-muted);
}

.ps-fd__gross-val {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--ps-primary);
}

@media (max-width: 767px) {
    .ps-fd__gross {
        border-left: none;
        border-top: 1px solid var(--ps-border);
        flex: 1 1 100%;
    }
}

/* ── Fare rules ─────────────────────────────────────────────────── */
.ps-rules-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 0.85rem;
    margin-bottom: 1rem;
    background: #fff8e6;
    border: 1px solid rgba(240, 180, 27, 0.35);
    border-radius: 8px;
    font-size: 0.8rem;
    color: #7a5a12;
}

.ps-rules-note i {
    color: #f0b41b;
}

.ps-rule-card {
    border: 1px solid var(--ps-border);
    border-radius: 10px;
    margin-bottom: 0.85rem;
    overflow: hidden;
}

.ps-rule-card:last-child {
    margin-bottom: 0;
}

.ps-rule-card__head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.9rem;
    background: var(--ps-soft);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ps-text);
}

.ps-rule-card__head i {
    color: var(--ps-primary);
}

.ps-rule-dir {
    margin-left: auto;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--ps-muted);
    text-transform: uppercase;
}

.ps-rule-tables {
    display: grid;
    grid-template-columns: 1fr;
}

@media (min-width: 768px) {
    .ps-rule-tables {
        grid-template-columns: 1fr 1fr;
    }

    .ps-rule-block:first-child {
        border-right: 1px solid var(--ps-border);
    }
}

.ps-rule-block {
    padding: 0.75rem 0.9rem;
}

.ps-rule-block__title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: var(--ps-sub);
    margin-bottom: 0.5rem;
}

.ps-rule-empty {
    font-size: 0.78rem;
    color: var(--ps-muted);
}

.ps-rule-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}

.ps-rule-table th {
    text-align: left;
    color: var(--ps-muted);
    font-weight: 600;
    padding: 0.3rem 0.4rem;
    border-bottom: 1px solid var(--ps-border);
}

.ps-rule-table td {
    padding: 0.35rem 0.4rem;
    border-bottom: 1px solid var(--ps-border);
    color: var(--ps-text);
}

.ps-rule-chip {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
}

.ps-rule-chip--ok {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.ps-rule-chip--no {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

/* ── Fare summary sidebar ───────────────────────────────────────── */
.ps-summary {
    position: sticky;
    top: 1rem;
    background: var(--ps-surface, #fff);
    border: 1px solid var(--ps-border, #e2e8f0);
    border-radius: 12px;
    box-shadow: var(--ps-shadow, 0 1px 3px rgba(15, 23, 42, 0.06));
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ps-summary__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.85rem;
    color: var(--ps-sub, #475569);
}

.ps-summary__row i {
    color: var(--ps-muted, #64748b);
    margin-right: 0.35rem;
}

.ps-summary__row strong {
    color: var(--ps-text, #0f172a);
    font-weight: 700;
}

.ps-summary__divider {
    height: 1px;
    background: var(--ps-border, #e2e8f0);
    margin: 0.15rem 0;
}

.ps-summary__row--payable {
    background: var(--ps-seg-tint, #f7f9fe);
    margin: 0 -1.25rem -1.1rem;
    padding: 0.9rem 1.25rem;
    border-radius: 0 0 12px 12px;
    font-size: 0.95rem;
}

.ps-summary__row--payable strong {
    color: var(--ps-primary, #027de2);
    font-size: 1.15rem;
}

/* Tablet Responsive (768px - 1024px) */
@media screen and (max-width: 1024px) {
    .pnr-card {
        padding: 18px 14px 22px;
    }

    .pnr-input {
        min-width: 160px;
        font-size: 12px;
        padding: 10px 14px;
    }

    .btn-check-pnr {
        padding: 10px 24px;
        font-size: 13px;
        min-width: 90px;
    }
}

/* Mobile Responsive (up to 768px) */
@media screen and (max-width: 768px) {
    .pnr-card {
        padding: 16px 12px 20px;
        border-radius: 10px;
    }

    .section-heading {
        margin-bottom: 16px;
    }

    .section-heading-left {
        font-size: 14px;
    }

    .bar-blue {
        height: 18px;
        width: 4px;
    }

    .form-label-custom {
        font-size: 12px;
        margin-bottom: 4px;
    }

    .pnr-input-group {
        gap: 8px;
        flex-direction: column;
        align-items: stretch;
    }

    .pnr-input {
        min-width: unset;
        width: 100%;
        font-size: 13px;
        padding: 10px 14px;
        height: 42px;
    }

    .btn-check-pnr {
        width: 100%;
        padding: 10px 16px;
        font-size: 14px;
        height: 42px;
        min-width: unset;
    }
}

/* Small Mobile (up to 480px) */
@media screen and (max-width: 480px) {
    .container-fluid {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    .pnr-card {
        padding: 14px 10px 18px;
        border-radius: 8px;
        border-width: 1px;
    }

    .section-heading-left {
        font-size: 13px;
        gap: 8px;
    }

    .bar-blue {
        height: 16px;
        width: 3.5px;
    }

    .pnr-input {
        font-size: 12px;
        padding: 9px 12px;
        height: 38px;
    }

    .btn-check-pnr {
        font-size: 13px;
        padding: 9px 14px;
        height: 38px;
        border-radius: 6px;
    }

    .form-label-custom {
        font-size: 11px;
        margin-bottom: 3px;
    }
}

/* Touch-friendly improvements */
@media (hover: none) {
    .btn-check-pnr:hover {
        background: #3b79f2;
    }

    .btn-check-pnr:active {
        background: #2c6bfa;
    }

    .pnr-input,
    .btn-check-pnr {
        min-height: 44px;
    }
}

/* Accessibility - Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .btn-check-pnr {
        transition: none;
    }

    .btn-check-pnr:active {
        transform: none;
    }
}

/* Accessibility - High Contrast */
@media (prefers-contrast: high) {
    .pnr-card {
        border-color: #000;
    }

    .pnr-input {
        border-color: #000;
    }

    .btn-check-pnr {
        background: #0055cc;
    }
}

/* Print Styles */
@media print {
    .pnr-card {
        border: 1px solid #000;
        box-shadow: none;
    }

    .btn-check-pnr {
        background: #000;
        color: #fff;
    }
}
</style>

<style>
/* Print only the Flight PNR result (PNR meta, Flight/Passenger/Fare Details, Fare Rules) —
   hides the sidebar, topbar, breadcrumbs and everything else on the page. Unscoped because
   it must reach elements outside this component. */
@media print {
    body * {
        visibility: hidden !important;
    }

    .pnr-print-area,
    .pnr-print-area * {
        visibility: visible !important;
    }

    .pnr-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }

    .pnr-print-hide,
    .pnr-print-hide * {
        display: none !important;
    }
}
</style>
