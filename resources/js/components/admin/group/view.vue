<script setup>
import AppBreadcrumbs from '../../common/AppBreadcrumbs.vue';
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from 'vue-router';
import axiosInstance from "../../../axiosInstance";
import { useAuthStore } from '../../../stores/authStore';
import moment from "moment";

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();
const loading = ref(true);
const data = ref(null);

onMounted(async () => {
    if (route.params.id) {
        await getAllDataOfGroup(route.params.id);
    }
});

async function getAllDataOfGroup(id) {
    try {
        loading.value = true;
        const response = await axiosInstance.post('edit-group-request/data', { id: id });
        const raw = response.data.data ?? response.data[0] ?? response.data;
        data.value = raw;
    } catch (error) {
        console.error('Failed to fetch group data:', error);
        Notification.showToast('error', error.response?.data?.message || 'Failed to load group data');
        // router.push({ name: 'groupList' });
    } finally {
        loading.value = false;
    }
}

const tripTypeLabel = computed(() => {
    if (!data.value) return '';
    const map = { oneway: 'One Way', roundway: 'Round Way', multicity: 'Multi City' };
    return map[data.value.trip_type] || map[data.value.tripType] || data.value.trip_type || data.value.tripType || '';
});

const totalPassengers = computed(() => {
    if (!data.value) return 0;
    const a = Number(data.value.adult) || 0;
    const c = Number(data.value.children) || 0;
    const inf = Number(data.value.infants) || 0;
    return a + c + inf;
});

const segments = computed(() => {
    if (!data.value) return [];
    return data.value.segments || data.value.flights || [];
});

function formatDate(val) {
    if (!val) return '—';
    return moment(val).format('DD MMM YYYY, hh:mm A');
}

function formatCurrency(val, currency) {
    if (!val && val !== 0) return '—';
    const curr = currency || data.value?.currency || 'BDT';
    return `${Number(val).toLocaleString()} ${curr}`;
}

function goBack() {
    router.push({ name: 'groupList' });
}
</script>

<template>
    <div class="group-page">
        <AppBreadcrumbs title="View Group Request" :back-to="{ name: 'groupList' }" :breadcrumbs="[
            { label: 'Dashboard', to: { name: 'Home' } },
            { label: 'Group Management', to: { name: 'groupList' } },
            { label: 'View Group Request' }]">
        </AppBreadcrumbs>

        <!-- Loading State -->
        <div v-if="loading" class="view-loading">
            <div class="spinner-box">
                <div class="spinner-ring"></div>
                <span>Loading group details...</span>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="data" class="row g-4">
            <!-- Main View Card -->
            <div class="col-12 col-lg-8">
                <div class="rule-card">

                    <!-- Header Banner -->
                    <div class="view-header-banner">
                        <div class="banner-left">
                            <div class="banner-icon-wrap">
                                <i class="fa fa-plane-departure"></i>
                            </div>
                            <div class="banner-text">
                                <h4 class="banner-title">Group Request #{{ data.group_code }}</h4>
                                <p class="banner-sub">Created {{ formatDate(data.created_at) }}</p>
                            </div>
                        </div>
                        <div class="banner-right">
                            <span class="trip-type-badge" :class="'badge-success'">
                                {{ data.request_type }}
                            </span>

                        </div>
                    </div>

                    <!-- Content Area -->
                    <div class="tab-content-area">

                        <!-- Group Information -->
                        <div class="view-section">
                            <div class="section-heading blue">
                                <span class="section-bar"></span>
                                <h5><i class="fa fa-users me-2 section-icon"></i>Group Information</h5>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Group Type</span>
                                    <span class="info-value group-type-value">{{ data.group_type || data.groupType || '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Flight Details / Segments -->
                        <template v-if="(data.request_type || data.request_type) !== 'multicity'">
                            <!-- Outbound Flight -->
                            <div class="view-section">
                                <div class="section-heading purple">
                                    <span class="section-bar"></span>
                                    <h5><i class="fa fa-plane me-2 section-icon"></i>{{ (data.request_type || data.request_type) === 'roundway' ? 'Outbound Flight' : 'Flight Details' }}</h5>
                                </div>
                                <div class="flight-route-visual">
                                    <div class="route-node">
                                        <div class="route-dot dot-from"></div>
                                        <span class="route-code">{{ data.origin || '—' }}</span>
                                        <span class="route-label">From</span>
                                    </div>
                                    <div class="route-line-wrap">
                                        <div class="route-line"></div>
                                        <i class="fa fa-plane route-plane-icon"></i>
                                    </div>
                                    <div class="route-node">
                                        <div class="route-dot dot-to"></div>
                                        <span class="route-code">{{ data.destination || '—' }}</span>
                                        <span class="route-label">To</span>
                                    </div>
                                </div>
                                <div class="info-grid mt-3">
                                    <div class="info-item">
                                        <span class="info-label">Departure Date</span>
                                        <span class="info-value"><i class="fa-regular fa-calendar me-1"></i>{{ formatDate(data.departure_date || data.departureDate) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Return Flight -->
                            <template v-if="(data.request_type || data.request_type) === 'roundway'">
                                <div class="section-divider"></div>
                                <div class="view-section">
                                    <div class="section-heading teal">
                                        <span class="section-bar"></span>
                                        <h5><i class="fa fa-plane-arrival me-2 section-icon"></i>Return Flight</h5>
                                    </div>
                                    <div class="flight-route-visual return-route">
                                        <div class="route-node">
                                            <div class="route-dot dot-from"></div>
                                            <span class="route-code">{{ data.return_origin || data.return_origin || '—' }}</span>
                                            <span class="route-label">From</span>
                                        </div>
                                        <div class="route-line-wrap">
                                            <div class="route-line"></div>
                                            <i class="fa fa-plane route-plane-icon"></i>
                                        </div>
                                        <div class="route-node">
                                            <div class="route-dot dot-to"></div>
                                            <span class="route-code">{{ data.return_destination || data.return_destination || '—' }}</span>
                                            <span class="route-label">To</span>
                                        </div>
                                    </div>
                                    <div class="info-grid mt-3">
                                        <div class="info-item">
                                            <span class="info-label">Return Date</span>
                                            <span class="info-value"><i class="fa-regular fa-calendar me-1"></i>{{ formatDate(data.return_date || data.returnDate) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- Multi City Segments -->
                        <template v-else>
                            <div class="view-section">
                                <div class="section-heading purple">
                                    <span class="section-bar"></span>
                                    <h5><i class="fa fa-route me-2 section-icon"></i>Flight Segments</h5>
                                </div>
                                <div class="segments-timeline">
                                    <div v-for="(seg, index) in segments" :key="index" class="segment-card-view">
                                        <div class="segment-left">
                                            <div class="segment-step">
                                                <div class="step-circle">{{ index + 1 }}</div>
                                                <div v-if="index < segments.length - 1" class="step-connector"></div>
                                            </div>
                                        </div>
                                        <div class="segment-content">
                                            <div class="segment-route-mini">
                                                <span class="mini-code">{{ seg.origin || '—' }}</span>
                                                <i class="fa fa-arrow-right mini-arrow"></i>
                                                <span class="mini-code">{{ seg.destination || '—' }}</span>
                                            </div>
                                            <div class="segment-meta">
                                                <span><i class="fa-regular fa-calendar me-1"></i>{{ formatDate(seg.departure_date || seg.departureDate) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="section-divider"></div>

                        <!-- Flight Preferences -->
                        <div class="view-section">
                            <div class="section-heading blue">
                                <span class="section-bar"></span>
                                <h5><i class="fa fa-sliders me-2 section-icon"></i>Flight Preferences</h5>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Preferred Airlines</span>
                                    <span class="info-value">{{ data.preferred_flight || data.preferred_flight || '—' }}</span>
                                </div>
                                <template v-if="(data.request_type || data.request_type) !== 'multicity'">
                                    <div class="info-item">
                                        <span class="info-label">Flight No.</span>
                                        <span class="info-value mono">{{ data.flight_no || data.flight_no || '—' }}</span>
                                    </div>
                                </template>
                                <div class="info-item">
                                    <span class="info-label">Preferred Class</span>
                                    <span class="info-value">
                                        <span v-if="data.class_type || data.class_type" class="class-badge">{{ data.class_type || data.class_type }}</span>
                                        <span v-else>—</span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Code (RBD)</span>
                                    <span class="info-value mono rbd-badge">{{ data.class_code || '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Passengers -->
                        <div class="view-section">
                            <div class="section-heading teal">
                                <span class="section-bar"></span>
                                <h5><i class="fa fa-user-group me-2 section-icon"></i>Passengers</h5>
                            </div>
                            <div class="passenger-stats">
                                <div class="pax-stat-card">
                                    <div class="pax-stat-icon adult-icon"><i class="fa fa-user"></i></div>
                                    <div class="pax-stat-info">
                                        <span class="pax-stat-number">{{ data.adult_traveler || 0 }}</span>
                                        <span class="pax-stat-label">Adults</span>
                                    </div>
                                </div>
                                <div class="pax-stat-card">
                                    <div class="pax-stat-icon child-icon"><i class="fa fa-child"></i></div>
                                    <div class="pax-stat-info">
                                        <span class="pax-stat-number">{{ data.child_traveler || 0 }}</span>
                                        <span class="pax-stat-label">Children</span>
                                    </div>
                                </div>
                                <div class="pax-stat-card">
                                    <div class="pax-stat-icon infant-icon"><i class="fa fa-baby"></i></div>
                                    <div class="pax-stat-info">
                                        <span class="pax-stat-number">{{ data.infant_traveler || 0 }}</span>
                                        <span class="pax-stat-label">Infants</span>
                                    </div>
                                </div>
                                <div class="pax-stat-card total-card">
                                    <div class="pax-stat-icon total-icon"><i class="fa fa-users"></i></div>
                                    <div class="pax-stat-info">
                                        <span class="pax-stat-number">{{ data.total_traveler }}</span>
                                        <span class="pax-stat-label">Total</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Fare Details -->
                        <div class="view-section">
                            <div class="section-heading green">
                                <span class="section-bar"></span>
                                <h5><i class="fa fa-money-bill-wave me-2 section-icon"></i>Fare Details</h5>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Currency</span>
                                    <span class="info-value">
                                        <span class="currency-badge">{{ data.currency || 'BDT' }}</span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Per Person Fare</span>
                                    <span class="info-value fare-value">{{ formatCurrency(data.per_person_fare || data.per_person_fare) }}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Total Estimated Fare</span>
                                    <span class="info-value fare-total">{{ formatCurrency((Number(data.per_person_fare || data.per_person_fare) || 0) * data.total_traveler) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <!-- Requirements -->
                        <div class="view-section">
                            <div class="section-heading purple">
                                <span class="section-bar"></span>
                                <h5><i class="fa fa-clipboard-list me-2 section-icon"></i>Requirements</h5>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Special Requirements</span>
                                    <span class="info-value">
                                        <span v-if="data.special_requirements || data.specialRequirements" class="req-tag">{{ data.special_requirements || data.specialRequirements }}</span>
                                        <span v-else class="text-muted">None</span>
                                    </span>
                                </div>
                                <div class="info-item full-width">
                                    <span class="info-label">Details Requirements</span>
                                    <span class="info-value details-text">{{ data.details_requirements || data.detailsRequirements || 'No details provided.' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Back Button -->
                        <div class="nav-actions">
                            <button @click="goBack" class="btn-action btn-back">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <div class="nav-right"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Policy Cards -->
            <div class="col-12 col-lg-4">
                <div class="sidebar-sticky">
                    <div class="policy-card" v-for="(policy, idx) in sidebarPolicies" :key="idx">
                        <div class="policy-header" :class="policy.colorClass">
                            <span class="policy-icon">
                                <i :class="policy.icon"></i>
                            </span>
                            <h6 class="policy-title">{{ policy.title }}</h6>
                        </div>
                        <ul class="policy-list">
                            <li v-for="(item, iIdx) in policy.items" :key="iIdx" v-html="item"></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Data State -->
        <div v-else class="empty-state">
            <div class="empty-icon"><i class="fa fa-inbox"></i></div>
            <h5>No Data Found</h5>
            <p>The group request you're looking for doesn't exist or has been removed.</p>
            <button @click="goBack" class="btn-action btn-back"><i class="bi bi-arrow-left me-1"></i>Go Back</button>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            sidebarPolicies: [
                {
                    title: 'Deposit Policy',
                    icon: 'fa fa-money-bill-transfer',
                    colorClass: 'policy-blue',
                    items: [
                        'A non-refundable deposit (usually <strong>10–30%</strong> of total fare) must be paid within <strong>24–72 hours</strong> of receiving the quotation to hold the seats.',
                        'Failure to pay deposit within the deadline will result in <strong>automatic cancellation</strong> of the reserved seats.'
                    ]
                },
                {
                    title: 'Fare Rules',
                    icon: 'fa fa-ruler',
                    colorClass: 'policy-purple',
                    items: [
                        'Group fares are negotiated and fixed once agreed but are <strong>not available publicly</strong> online.',
                        'Fares are usually higher than promotional fares but offer flexibility (name changes, payment terms).',
                        'Quoted fares include taxes and surcharges, but exclude optional services (meals, baggage, etc.).'
                    ]
                },
                {
                    title: 'Cancellation & Refund',
                    icon: 'fa fa-circle-minus',
                    colorClass: 'policy-red',
                    items: [
                        '<strong>Before ticket issuance:</strong> Deposit is usually non-refundable.',
                        '<strong>After ticket issuance:</strong> Cancellation penalties apply per ticket. Group tickets are often non-refundable or only partially refundable.',
                        'No-show passengers may be charged <strong>100% penalty</strong>.'
                    ]
                }
            ]
        };
    }
};
</script>

<style scoped>
/* ─── Page ─────────────────────────────────────── */
.group-page {
    padding: 1.5rem;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* ─── Card ──────────────────────────────────────── */
.rule-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

/* ─── View Header Banner ────────────────────────── */
.view-header-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1.75rem;
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    color: #fff;
    flex-wrap: wrap;
    gap: 1rem;
}

.banner-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.banner-icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.banner-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.01em;
    color: #fff;
}

.banner-sub {
    font-size: 0.8rem;
    margin: 0.15rem 0 0;
    opacity: 0.85;
    font-weight: 400;
}

.trip-type-badge {
    padding: 0.4rem 1.1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(4px);
    border: 1.5px solid rgba(255, 255, 255, 0.35);
}

.trip-type-badge.badge-roundway {
    background: rgba(255, 255, 255, 0.25);
}

.trip-type-badge.badge-multicity {
    background: rgba(255, 255, 255, 0.25);
}

/* ─── Tab Content ───────────────────────────────── */
.tab-content-area {
    padding: 2rem 1.75rem;
}

/* ─── View Sections ─────────────────────────────── */
.view-section {
    margin-bottom: 0.5rem;
}

.section-divider {
    border: none;
    border-top: 1px dashed #e5e7eb;
    margin: 1.5rem 0;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
}

.section-heading h5 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.section-icon {
    font-size: 0.85rem;
    opacity: 0.7;
}

.section-bar {
    display: inline-block;
    width: 4px;
    height: 20px;
    border-radius: 4px;
}

.section-heading.blue .section-bar { background: #3b82f6; }
.section-heading.blue h5 { color: #1d4ed8; }

.section-heading.purple .section-bar { background: #8b5cf6; }
.section-heading.purple h5 { color: #7c3aed; }

.section-heading.teal .section-bar { background: #14b8a6; }
.section-heading.teal h5 { color: #0d9488; }

.section-heading.green .section-bar { background: #10b981; }
.section-heading.green h5 { color: #059669; }

/* ─── Info Grid ─────────────────────────────────── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.25rem;
}

.info-item {
    padding: 0.85rem 1rem;
    background: #f9fafb;
    border-radius: 12px;
    border: 1px solid #f3f4f6;
    transition: border-color 0.2s ease;
}

.info-item:hover {
    border-color: #e5e7eb;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.35rem;
}

.info-value {
    display: block;
    font-size: 0.92rem;
    font-weight: 600;
    color: #111827;
    word-break: break-word;
}

.info-value.mono {
    font-family: 'Courier New', monospace;
    font-weight: 700;
    letter-spacing: 0.03em;
}

.group-type-value {
    text-transform: capitalize;
    font-size: 1rem;
    color: #1d4ed8;
}

.details-text {
    font-weight: 400;
    color: #4b5563;
    line-height: 1.6;
    white-space: pre-wrap;
}

.text-muted {
    color: #9ca3af;
    font-style: italic;
}

/* ─── Flight Route Visual ───────────────────────── */
.flight-route-visual {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 1.5rem 1rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #eff6ff 100%);
    border-radius: 14px;
    border: 1.5px solid #dbeafe;
    margin-bottom: 0.5rem;
}

.flight-route-visual.return-route {
    background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
    border-color: #99f6e4;
}

.route-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    min-width: 80px;
}

.route-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    position: relative;
}

.dot-from {
    background: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

.return-route .dot-from {
    background: #14b8a6;
    box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.2);
}

.dot-to {
    background: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
}

.route-code {
    font-size: 1.35rem;
    font-weight: 800;
    color: #111827;
    letter-spacing: 0.02em;
}

.route-label {
    font-size: 0.72rem;
    font-weight: 500;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.route-line-wrap {
    flex: 1;
    max-width: 200px;
    position: relative;
    display: flex;
    align-items: center;
}

.route-line {
    width: 100%;
    height: 2.5px;
    background: linear-gradient(90deg, #3b82f6, #93c5fd);
    border-radius: 2px;
}

.return-route .route-line {
    background: linear-gradient(90deg, #14b8a6, #5eead4);
}

.route-plane-icon {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-size: 1rem;
    color: #6b7280;
    background: #f0f9ff;
    padding: 0 0.5rem;
}

.return-route .route-plane-icon {
    background: #f0fdfa;
}

/* ─── Multi-City Segments Timeline ──────────────── */
.segments-timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.segment-card-view {
    display: flex;
    gap: 1rem;
    align-items: stretch;
}

.segment-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 36px;
    flex-shrink: 0;
}

.step-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #8b5cf6);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    font-weight: 700;
    flex-shrink: 0;
}

.step-connector {
    width: 2.5px;
    flex: 1;
    min-height: 20px;
    background: linear-gradient(180deg, #c4b5fd, #e9e5f5);
    border-radius: 2px;
}

.segment-content {
    flex: 1;
    padding: 0.85rem 1rem;
    background: #f9fafb;
    border: 1.5px solid #f3f4f6;
    border-radius: 12px;
    margin-bottom: 0.65rem;
}

.segment-route-mini {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.4rem;
}

.mini-code {
    font-size: 1.1rem;
    font-weight: 800;
    color: #111827;
    letter-spacing: 0.02em;
}

.mini-arrow {
    color: #8b5cf6;
    font-size: 0.8rem;
}

.segment-meta {
    font-size: 0.82rem;
    color: #6b7280;
}

/* ─── Passenger Stats Cards ─────────────────────── */
.passenger-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.85rem;
}

.pax-stat-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 14px;
    border: 1.5px solid #f3f4f6;
    transition: all 0.25s ease;
}

.pax-stat-card:hover {
    border-color: #e5e7eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.pax-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.adult-icon { background: #eff6ff; color: #3b82f6; }
.child-icon { background: #fef3c7; color: #d97706; }
.infant-icon { background: #fce7f3; color: #db2777; }
.total-icon { background: linear-gradient(135deg, #1e40af, #3b82f6); color: #fff; }

.pax-stat-info {
    display: flex;
    flex-direction: column;
}

.pax-stat-number {
    font-size: 1.35rem;
    font-weight: 800;
    color: #111827;
    line-height: 1;
}

.total-card .pax-stat-number {
    color: #1d4ed8;
}

.pax-stat-label {
    font-size: 0.72rem;
    font-weight: 500;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 0.2rem;
}

/* ─── Badges ────────────────────────────────────── */
.class-badge {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.rbd-badge {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    border-radius: 8px;
    font-size: 1rem;
    background: #f3f4f6;
    color: #374151;
    border: 1.5px solid #e5e7eb;
}

.currency-badge {
    display: inline-block;
    padding: 0.3rem 0.85rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 700;
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    color: #059669;
    border: 1px solid #a7f3d0;
}

.req-tag {
    display: inline-block;
    padding: 0.35rem 0.9rem;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 600;
    background: linear-gradient(135deg, #f5f3ff, #ede9fe);
    color: #7c3aed;
    border: 1px solid #ddd6fe;
}

.fare-value {
    font-size: 1.1rem !important;
    color: #059669 !important;
}

.fare-total {
    font-size: 1.2rem !important;
    color: #1d4ed8 !important;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    padding: 0.5rem 1rem;
    border-radius: 12px;
    text-align: center;
}

/* ─── Navigation Actions ────────────────────────── */
.nav-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f3f4f6;
}

.nav-right {
    display: flex;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.6rem 1.5rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    font-family: inherit;
}

.btn-back {
    background: #f3f4f6;
    color: #374151;
}

.btn-back:hover {
    background: #e5e7eb;
    color: #111827;
}

/* ─── Loading State ─────────────────────────────── */
.view-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

.spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.spinner-ring {
    width: 44px;
    height: 44px;
    border: 3.5px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ─── Empty State ───────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    text-align: center;
    gap: 0.75rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
}

.empty-state h5 {
    font-size: 1.15rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}

.empty-state p {
    font-size: 0.875rem;
    color: #9ca3af;
    margin: 0 0 1rem;
    max-width: 360px;
}

/* ─── Sidebar ───────────────────────────────────── */
.sidebar-sticky {
    position: sticky;
    top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.policy-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.policy-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.policy-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.9rem 1.15rem;
}

.policy-blue {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-bottom: 2px solid #bfdbfe;
}

.policy-purple {
    background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
    border-bottom: 2px solid #ddd6fe;
}

.policy-red {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-bottom: 2px solid #fecaca;
}

.policy-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.policy-blue .policy-icon { background: #3b82f6; color: #fff; }
.policy-purple .policy-icon { background: #8b5cf6; color: #fff; }
.policy-red .policy-icon { background: #ef4444; color: #fff; }

.policy-title {
    font-size: 0.88rem;
    font-weight: 600;
    margin: 0;
}

.policy-blue .policy-title { color: #1d4ed8; }
.policy-purple .policy-title { color: #7c3aed; }
.policy-red .policy-title { color: #dc2626; }

.policy-list {
    list-style: none;
    padding: 0.85rem 1.15rem 1rem;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.policy-list li {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.55;
    padding-left: 1rem;
    position: relative;
}

.policy-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5em;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #d1d5db;
}

.policy-blue .policy-list li::before { background: #93c5fd; }
.policy-purple .policy-list li::before { background: #c4b5fd; }
.policy-red .policy-list li::before { background: #fca5a5; }

.policy-list li strong { color: #374151; }

.policy-list li :deep(ul) {
    list-style: none;
    padding: 0.35rem 0 0 0.5rem;
    margin: 0;
}

.policy-list li :deep(ul li)::before {
    width: 4px;
    height: 4px;
    top: 0.55em;
}

/* ─── Responsive ────────────────────────────────── */
@media (max-width: 991.98px) {
    .sidebar-sticky {
        position: static;
        flex-direction: row;
        flex-wrap: wrap;
    }

    .policy-card {
        flex: 1 1 calc(50% - 0.5rem);
        min-width: 260px;
    }
}

@media (max-width: 767.98px) {
    .group-page {
        padding: 1rem;
    }

    .tab-content-area {
        padding: 1.25rem 1rem;
    }

    .view-header-banner {
        padding: 1.25rem 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .passenger-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .flight-route-visual {
        padding: 1rem 0.75rem;
        gap: 0.75rem;
    }

    .route-code {
        font-size: 1.05rem;
    }

    .policy-card {
        flex: 1 1 100%;
    }

    .nav-actions {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }

    .nav-right {
        width: 100%;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
    }
}
</style>
