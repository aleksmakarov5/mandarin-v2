<template>
    <div class="estimate-tab-wrapper p-2 p-md-3">
        <!-- ПРЕЛОАДЕР -->
        <div v-if="loading && !effectiveAppId" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="text-muted small mt-2">Загрузка сметы...</div>
        </div>

        <template v-else-if="effectiveAppId">

            <!-- 2. СЕКЦИЯ: ЗАПЧАСТИ (на всю ширину; видят ВСЕ роли) -->
            <div class="mb-3">
                <SparePartsTable :application-id="effectiveAppId"
                    :status-id="application?.id_status || application?.status_id" :application="application || {}"
                    :current-tab="currentTab" :is-sto="isSto" @updated="onEstimateUpdated" />
            </div>

            <!-- ГРИД ДЛЯ РАБОТ И ДОП. ЗАТРАТ -->
            <div class="row g-3">
                <!-- 1. КАЛЬКУЛЯЦИЯ СК (РЕМОНТ + ОКРАСКА) — СТРОГО СКРЫТА ДЛЯ СТО И СПМ -->
                <div v-if="!isSto && !isSpm" class="col-12">
                    <CalculationWorksSection :application-id="effectiveAppId" @updated="onEstimateUpdated" />
                </div>

                <!-- 2. ПЛАНОВЫЕ РАБОТЫ — ВИДЯТ СТО, МРК, НАСТАВНИКИ (СКРЫТО ДЛЯ СПМ) -->
                <div v-if="!isSpm" class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100 p-2 bg-white">
                        <PlanWorksTable :application-id="effectiveAppId" :service-id="effectiveServiceId"
                            :application="application || {}" :user-role="userRoleString" :current-user="currentUser"
                            @updated="onEstimateUpdated" />
                    </div>
                </div>

                <!-- 3. ФАКТИЧЕСКИЕ РАБОТЫ СТО — ВИДЯТ СТО, МРК, НАСТАВНИКИ (СКРЫТО ДЛЯ СПМ) -->
                <div v-if="!isSpm" class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100 p-2 bg-white">
                        <FactWorksTable :application-id="effectiveAppId" :service-id="effectiveServiceId"
                            :application="application || {}" :user-role="userRoleString" :current-user="currentUser"
                            @updated="onEstimateUpdated" />
                    </div>
                </div>

                <!-- 4. ДОП. ЗАТРАТЫ — СТРОГО СКРЫТЫ ДЛЯ СТО И СПМ -->
                <div v-if="!isSto && !isSpm" class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100 p-2 bg-white">
                        <ExtraExpensesTable :application-id="effectiveAppId" @updated="onEstimateUpdated" />
                    </div>
                </div>

                <!-- 5. ИТОГОВЫЕ ДАННЫЕ ИЗ КАЛЬКУЛЯЦИИ (АККОРДЕОН) — СТРОГО СКРЫТЫ ДЛЯ СТО И СПМ -->
                <div v-if="!isSto && !isSpm" class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm bg-white estimate-collapsible-card h-100">
                        <div class="card-header bg-white border-0 py-2.5 px-3 d-flex justify-content-between align-items-center cursor-pointer user-select-none"
                            @click="isSummaryOpen = !isSummaryOpen">
                            <div class="d-flex align-items-center gap-2">
                                <div
                                    class="header-icon-box bg-dark text-white rounded d-flex align-items-center justify-content-center">
                                    <i class="bi bi-calculator-fill" style="font-size: 0.85rem;"></i>
                                </div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <span class="fw-bold text-dark section-title">Итоговые данные из калькуляции</span>
                                    <span class="text-secondary count-bracket">(7)</span>
                                    <i class="bi bi-chevron-right chevron-toggle text-secondary ms-0.5"
                                        :class="{ 'rotated': isSummaryOpen }"></i>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <div
                                    class="header-badge-black d-flex flex-column align-items-end justify-content-center px-2.5 py-1">
                                    <span class="badge-label-small">Сумма СК:</span>
                                    <span class="badge-val-big font-monospace">{{ formatMoney(totalSkSum) }} ₽</span>
                                </div>

                                <div class="header-badge-outlined d-flex flex-column align-items-end justify-content-center px-2.5 py-1"
                                    :class="totalRemains >= 0 ? 'badge-profit-border' : 'badge-loss-border'">
                                    <span class="badge-label-small">Маржа ({{ marginPercent }}%):</span>
                                    <span class="badge-val-big font-monospace"
                                        :class="totalRemains >= 0 ? 'text-success' : 'text-danger'">
                                        {{ formatMoney(totalRemains) }} ₽
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-show="isSummaryOpen" class="card-body p-0 border-top">
                            <div class="table-responsive">
                                <table
                                    class="table table-sm table-bordered mb-0 align-middle font-monospace summary-detail-table">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted fw-semibold ps-3">Калькуляция запчастей (СК)</td>
                                            <td class="text-end fw-bold text-primary pe-3">{{ formatMoney(totalPartsSk)
                                                }} ₽</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-semibold ps-3">Калькуляция работ (Ремонт + Окраска
                                                СК)</td>
                                            <td class="text-end fw-bold text-primary pe-3">{{
                                                formatMoney(totalWorksSkSum) }} ₽</td>
                                        </tr>
                                        <tr class="table-subtotal-row">
                                            <td class="fw-bold text-dark ps-3">ИТОГО СОГЛАСОВАНО СК:</td>
                                            <td class="text-end fw-bold text-primary pe-3 fs-6">{{
                                                formatMoney(totalSkSum) }} ₽</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-semibold ps-3">Закупка запчастей (выбрано/факт)
                                            </td>
                                            <td class="text-end fw-bold text-danger pe-3">-{{
                                                formatMoney(totalPartsCost) }} ₽</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-semibold ps-3">Затраты на ремонт и окраску (СТО)
                                            </td>
                                            <td class="text-end fw-bold text-danger pe-3">-{{
                                                formatMoney(totalWorksCost) }} ₽</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-semibold ps-3">Дополнительные расходы</td>
                                            <td class="text-end fw-bold text-danger pe-3">-{{
                                                formatMoney(totalExpensesCost) }} ₽</td>
                                        </tr>
                                        <tr class="table-subtotal-row">
                                            <td class="fw-bold text-dark ps-3">ИТОГО ЗАТРАТЫ:</td>
                                            <td class="text-end fw-bold text-dark pe-3 fs-6">{{
                                                formatMoney(totalActualCostWithExpenses) }} ₽</td>
                                        </tr>
                                        <tr :class="totalRemains >= 0 ? 'table-profit-row' : 'table-loss-row'">
                                            <td class="fw-bold ps-3">ОСТАТОК / ПРИБЫЛЬ:</td>
                                            <td class="text-end fw-bold pe-3 fs-6"
                                                :class="totalRemains >= 0 ? 'text-success' : 'text-danger'">
                                                {{ totalRemains >= 0 ? '+' : '' }}{{ formatMoney(totalRemains) }} ₽
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

// ИМПОРТЫ МОДУЛЕЙ СМЕТЫ
import SparePartsTable from '../../modules/applications/estimate/parts/SparePartsTable.vue';
import PlanWorksTable from '../../modules/applications/estimate/works/PlanWorksTable.vue';
import FactWorksTable from '../../modules/applications/estimate/works/FactWorksTable.vue';
import ExtraExpensesTable from '../../modules/applications/estimate/works/ExtraExpensesTable.vue';
import CalculationWorksSection from '../../modules/applications/estimate/works/CalculationWorksSection.vue';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        default: null,
    },
    application: {
        type: Object,
        default: () => ({}),
    },
    currentTab: {
        type: String,
        default: 'requests',
    },
});

const emit = defineEmits(['updated']);
const route = useRoute();

const isSummaryOpen = ref(false);
const loading = ref(false);

// Ответ бэкенда с ролями
const estimateMeta = ref({
    is_sto: false,
    is_spm: false,
    is_mrk: true,
    role: 'mrk',
    current_user: null,
    service: null,
});

const spareParts = ref([]);
const repairWorks = ref([]);
const paintWorks = ref([]);
const extraExpenses = ref([]);
const summary = ref({ sum_sk: 0, sum_zap: 0, remont_sum: 0 });

const effectiveAppId = computed(() => {
    return Number(
        props.applicationId ||
        props.application?.id ||
        route.params.id ||
        0
    );
});

const effectiveServiceId = computed(() => {
    return props.application?.id_service || props.application?.service_id || estimateMeta.value?.service?.id || null;
});

// РОЛИ: Читаем напрямую из ответа сервера
const isSto = computed(() => Boolean(estimateMeta.value.is_sto));
const isSpm = computed(() => Boolean(estimateMeta.value.is_spm));
const isMrk = computed(() => Boolean(estimateMeta.value.is_mrk));
const userRoleString = computed(() => String(estimateMeta.value.role || 'mrk'));
const currentUser = computed(() => estimateMeta.value.current_user || null);

// Итоги для расчетов
const totalPartsSk = computed(() => {
    return spareParts.value.reduce((acc, p) => acc + ((p.price_sk || p.price_ic || 0) * (p.count || 1)), 0);
});

const totalPartsCost = computed(() => {
    return spareParts.value.reduce((acc, p) => acc + ((p.buy_price || p.price_selected || 0) * (p.count || 1)), 0);
});

const totalWorksCost = computed(() => {
    const repair = repairWorks.value.reduce((acc, w) => acc + ((w.sto_hour || 0) * (w.sto_cost || 0)), 0);
    const paint = paintWorks.value.reduce((acc, w) => acc + (w.sto_cost || 0), 0);
    return repair + paint;
});

const totalWorksSkSum = computed(() => {
    const repair = repairWorks.value.reduce((acc, w) => acc + ((w.norm_hour || w.norm_hours || 0) * (w.cost_hour || w.price_per_hour || 0)), 0);
    const paint = paintWorks.value.reduce((acc, w) => acc + ((w.norm_hour || w.norm_hours || 0) * (w.cost_hour || w.price_per_hour || 0)) + (w.paint_cost || w.sum_materials || 0), 0);
    return repair + paint;
});

const totalExpensesCost = computed(() => {
    return extraExpenses.value.reduce((acc, e) => acc + (parseFloat(e.sum) || 0), 0);
});

const totalSkSum = computed(() => totalPartsSk.value + totalWorksSkSum.value);
const totalActualCost = computed(() => totalPartsCost.value + totalWorksCost.value);
const totalActualCostWithExpenses = computed(() => totalActualCost.value + totalExpensesCost.value);

const totalRemains = computed(() => totalSkSum.value - totalActualCostWithExpenses.value);

const marginPercent = computed(() => {
    if (totalSkSum.value <= 0) return 0;
    return Math.round((totalRemains.value / totalSkSum.value) * 100);
});

// Загрузка сметы и ролей с бэкенда
const fetchEstimate = async () => {
    if (!effectiveAppId.value) return;
    loading.value = true;
    try {
        const [estRes, expRes] = await Promise.all([
            axios.get(`/api/applications/${effectiveAppId.value}/estimate`).catch(() => ({ data: {} })),
            axios.get(`/api/applications/${effectiveAppId.value}/extra-expenses`).catch(() => ({ data: { data: [] } })),
        ]);

        if (estRes.data) {
            estimateMeta.value = {
                is_sto: Boolean(estRes.data.is_sto),
                is_spm: Boolean(estRes.data.is_spm),
                is_mrk: Boolean(estRes.data.is_mrk),
                role: estRes.data.role || (estRes.data.is_sto ? 'service' : (estRes.data.is_spm ? 'spm' : 'mrk')),
                current_user: estRes.data.current_user || null,
                service: estRes.data.service || null,
            };

            spareParts.value = estRes.data.spare_parts || [];
            repairWorks.value = estRes.data.repair_works || [];
            paintWorks.value = estRes.data.paint_works || [];
            summary.value = estRes.data.summary || {};
        }

        extraExpenses.value = expRes.data?.data || [];
    } catch (err) {
        console.error('Ошибка загрузки данных сметы:', err);
    } finally {
        loading.value = false;
    }
};

const onEstimateUpdated = () => {
    fetchEstimate();
    emit('updated');
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(() => {
    fetchEstimate();
});
</script>

<style scoped>
.estimate-tab-wrapper {
    width: 100%;
}

.estimate-collapsible-card {
    border-radius: 6px;
    overflow: hidden;
    transition: box-shadow 0.15s ease;
}

.cursor-pointer {
    cursor: pointer;
}

.header-icon-box {
    width: 28px;
    height: 28px;
    background-color: #1e293b !important;
    border-radius: 6px;
}

.section-title {
    font-size: 0.88rem;
    letter-spacing: -0.01em;
}

.count-bracket {
    font-size: 0.85rem;
    font-weight: 500;
}

.chevron-toggle {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
    display: inline-block;
}

.chevron-toggle.rotated {
    transform: rotate(90deg);
}

.header-badge-black {
    background-color: #1e293b;
    color: #ffffff;
    border-radius: 5px;
    min-width: 95px;
    line-height: 1.15;
}

.header-badge-outlined {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    min-width: 100px;
    line-height: 1.15;
}

.badge-profit-border {
    border-color: #86efac;
    background-color: #f0fdf4;
}

.badge-loss-border {
    border-color: #fca5a5;
    background-color: #fef2f2;
}

.badge-label-small {
    font-size: 0.60rem;
    opacity: 0.8;
    text-transform: uppercase;
}

.badge-val-big {
    font-size: 0.78rem;
    font-weight: 700;
}

.summary-detail-table td {
    padding: 6px 12px !important;
    font-size: 0.74rem;
    border-color: #f1f5f9;
}

.table-subtotal-row td {
    background-color: #f8fafc !important;
    border-top: 1.5px solid #cbd5e1 !important;
    border-bottom: 1.5px solid #cbd5e1 !important;
}

.table-profit-row td {
    background-color: #dcfce7 !important;
    border-top: 2px solid #86efac !important;
}

.table-loss-row td {
    background-color: #fee2e2 !important;
    border-top: 2px solid #fca5a5 !important;
}
</style>