<template>
    <div class="estimate-tab-wrapper p-3">
        <!-- ПРЕЛОАДЕР -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="text-muted small mt-2">Загрузка сметы...</div>
        </div>

        <template v-else>
            <!-- 1. ВЕРХНИЙ KPI-БЛОК -->
            <div class="row g-2 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-primary">
                        <div class="text-muted small text-uppercase fw-semibold">Калькуляция СК</div>
                        <div class="fs-4 fw-bold font-monospace text-primary">{{ formatMoney(totalSkSum) }} ₽</div>
                        <div class="small text-muted">Согласовано со страховой</div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-secondary">
                        <div class="text-muted small text-uppercase fw-semibold">Себестоимость затрат</div>
                        <div class="fs-4 fw-bold font-monospace text-dark">{{ formatMoney(totalActualCost) }} ₽</div>
                        <div class="small text-muted">Запчасти ({{ formatMoney(totalPartsCost) }}) + Работы ({{
                            formatMoney(totalWorksCost) }})</div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-4"
                        :class="totalRemains >= 0 ? 'border-success' : 'border-danger'">
                        <div class="text-muted small text-uppercase fw-semibold">Маржинальный доход</div>
                        <div class="fs-4 fw-bold font-monospace"
                            :class="totalRemains >= 0 ? 'text-success' : 'text-danger'">
                            {{ formatMoney(totalRemains) }} ₽
                        </div>
                        <div class="small text-muted">Остаток после закупки и ремонта</div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 bg-white border-start border-4 border-warning">
                        <div class="text-muted small text-uppercase fw-semibold">Рентабельность</div>
                        <div class="fs-4 fw-bold font-monospace text-warning">{{ marginPercent }}%</div>
                        <div class="small text-muted">Процент прибыли от суммы СК</div>
                    </div>
                </div>
            </div>

            <!-- 2. СЕКЦИЯ: ЗАПЧАСТИ -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="bi bi-gear-wide-connected me-2 text-warning"></i>
                        Запчасти и материалы ({{ spareParts.length }})
                    </span>
                    <button class="btn btn-warning btn-sm fw-semibold" @click="showAddPartModal = true">
                        <i class="bi bi-plus-lg me-1"></i> Добавить деталь
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 font-sans"
                        style="font-size: 0.82rem;">
                        <thead class="table-light text-center align-middle" style="font-size: 0.75rem;">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="min-width: 200px;">Наименование детали</th>
                                <th style="width: 120px;">Каталожный №</th>
                                <th style="width: 60px;">Кол-во</th>
                                <th style="width: 100px;">Цена СК</th>
                                <th style="width: 110px;">Сумма СК</th>
                                <th style="min-width: 220px;" class="bg-light">Варианты закупки (СТО / Ориг / Аналог /
                                    Б/У)</th>
                                <th style="width: 110px;" class="table-primary">Цена закупки</th>
                                <th style="width: 90px;">Выгода/Убыток</th>
                                <th style="width: 120px;">Статус</th>
                                <th style="width: 110px;">Срок</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(part, idx) in spareParts" :key="part.id">
                                <td class="text-center text-muted font-monospace">{{ idx + 1 }}</td>
                                <td>
                                    <input v-model="part.name" @change="savePart(part)"
                                        class="form-control form-control-sm border-0 bg-transparent fw-semibold" />
                                </td>
                                <td>
                                    <input v-model="part.part_number" @change="savePart(part)"
                                        class="form-control form-control-sm border-0 bg-transparent font-monospace text-uppercase"
                                        placeholder="Номер..." />
                                </td>
                                <td>
                                    <input v-model.number="part.count" @change="savePart(part)" type="number" min="1"
                                        class="form-control form-control-sm text-center font-monospace" />
                                </td>
                                <td>
                                    <input v-model.number="part.price_sk" @change="savePart(part)" type="number"
                                        step="0.01" class="form-control form-control-sm text-end font-monospace" />
                                </td>
                                <td class="text-end fw-bold font-monospace text-dark">
                                    {{ formatMoney((part.price_sk || 0) * (part.count || 1)) }} ₽
                                </td>

                                <!-- Кнопки выбора закупки в 1 клик -->
                                <td class="p-1">
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <button v-if="part.sto_price" @click="selectBuyPrice(part, part.sto_price)"
                                            class="btn btn-xs btn-outline-secondary py-0 px-1 font-monospace"
                                            title="Выбрать цену СТО">
                                            СТО: {{ formatMoney(part.sto_price) }} →
                                        </button>
                                        <button v-if="part.orig_price" @click="selectBuyPrice(part, part.orig_price)"
                                            class="btn btn-xs btn-outline-primary py-0 px-1 font-monospace"
                                            title="Выбрать оригинал">
                                            Ориг: {{ formatMoney(part.orig_price) }} →
                                        </button>
                                        <button v-if="part.anal_price" @click="selectBuyPrice(part, part.anal_price)"
                                            class="btn btn-xs btn-outline-info py-0 px-1 font-monospace"
                                            title="Выбрать аналог">
                                            Аналог: {{ formatMoney(part.anal_price) }} →
                                        </button>
                                        <button v-if="part.bu_price" @click="selectBuyPrice(part, part.bu_price)"
                                            class="btn btn-xs btn-outline-dark py-0 px-1 font-monospace"
                                            title="Выбрать Б/У">
                                            Б/У: {{ formatMoney(part.bu_price) }} →
                                        </button>
                                        <span
                                            v-if="!part.sto_price && !part.orig_price && !part.anal_price && !part.bu_price"
                                            class="text-muted small fst-italic">
                                            нет цен
                                        </span>
                                    </div>
                                </td>

                                <!-- Фактическая закупка -->
                                <td class="table-primary p-1">
                                    <input v-model.number="part.buy_price" @change="savePart(part)" type="number"
                                        step="0.01" class="form-control form-control-sm text-end font-monospace fw-bold"
                                        placeholder="0.00" />
                                </td>

                                <!-- Маржа по детали -->
                                <td class="text-end font-monospace fw-bold" :class="getPartDiffClass(part)">
                                    {{ formatMoney(getPartDiff(part)) }} ₽
                                </td>

                                <td>
                                    <select v-model="part.status" @change="savePart(part)"
                                        class="form-select form-select-sm py-0">
                                        <option value="Поиск">Поиск</option>
                                        <option value="Заказана">Заказана</option>
                                        <option value="В пути">В пути</option>
                                        <option value="На складе">На складе</option>
                                        <option value="Выдана">Выдана</option>
                                        <option value="Отказ">Отказ</option>
                                    </select>
                                </td>
                                <td>
                                    <input v-model="part.term" @change="savePart(part)"
                                        class="form-control form-control-sm py-0" placeholder="Срок..." />
                                </td>
                                <td class="text-center p-1">
                                    <button @click="deletePart(part.id)" class="btn btn-xs btn-outline-danger border-0"
                                        title="Удалить деталь">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="spareParts.length === 0">
                                <td colspan="12" class="text-center py-4 text-muted">
                                    В смету еще не добавлены запчасти. Нажмите «Добавить деталь».
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">Итого по запчастям:</td>
                                <td class="text-end font-monospace">—</td>
                                <td class="text-end font-monospace text-primary">{{ formatMoney(totalPartsSk) }} ₽</td>
                                <td></td>
                                <td class="text-end font-monospace text-dark">{{ formatMoney(totalPartsCost) }} ₽</td>
                                <td class="text-end font-monospace"
                                    :class="totalPartsSk - totalPartsCost >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(totalPartsSk - totalPartsCost) }} ₽
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- 3. СЕКЦИЯ: РЕМОНТНЫЕ И СЛЕСАРНЫЕ РАБОТЫ -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="bi bi-wrench-adjustable me-2 text-info"></i>
                        Ремонтные и слесарные работы ({{ repairWorks.length }})
                    </span>
                    <button class="btn btn-info btn-sm fw-semibold text-white" @click="showAddRepairModal = true">
                        <i class="bi bi-plus-lg me-1"></i> Добавить работу
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light text-center" style="font-size: 0.75rem;">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Наименование операции</th>
                                <th style="width: 100px;">Нормо-часы</th>
                                <th style="width: 120px;">Ставка за н/ч</th>
                                <th style="width: 130px;">Сумма СК</th>
                                <th style="width: 110px;">Н/ч СТО</th>
                                <th style="width: 120px;">Ставка СТО</th>
                                <th style="width: 130px;" class="table-primary">Расход СТО</th>
                                <th style="width: 110px;">Остаток</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(work, idx) in repairWorks" :key="work.id">
                                <td class="text-center text-muted font-monospace">{{ idx + 1 }}</td>
                                <td>
                                    <input v-model="work.name" @change="saveWork(work)"
                                        class="form-control form-control-sm border-0 bg-transparent fw-semibold" />
                                </td>
                                <td>
                                    <input v-model.number="work.norm_hour" @change="saveWork(work)" type="number"
                                        step="0.1" class="form-control form-control-sm text-center font-monospace" />
                                </td>
                                <td>
                                    <input v-model.number="work.cost_hour" @change="saveWork(work)" type="number"
                                        step="10" class="form-control form-control-sm text-end font-monospace" />
                                </td>
                                <td class="text-end fw-bold font-monospace text-primary">
                                    {{ formatMoney((work.norm_hour || 0) * (work.cost_hour || 0)) }} ₽
                                </td>
                                <td>
                                    <input v-model.number="work.sto_hour" @change="saveWork(work)" type="number"
                                        step="0.1" class="form-control form-control-sm text-center font-monospace" />
                                </td>
                                <td>
                                    <input v-model.number="work.sto_cost" @change="saveWork(work)" type="number"
                                        step="10" class="form-control form-control-sm text-end font-monospace" />
                                </td>
                                <td class="table-primary text-end fw-bold font-monospace">
                                    {{ formatMoney((work.sto_hour || 0) * (work.sto_cost || 0)) }} ₽
                                </td>
                                <td class="text-end font-monospace fw-bold"
                                    :class="getWorkDiff(work) >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(getWorkDiff(work)) }} ₽
                                </td>
                                <td class="text-center p-1">
                                    <button @click="deleteWork(work.id)" class="btn btn-xs btn-outline-danger border-0">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="repairWorks.length === 0">
                                <td colspan="10" class="text-center py-3 text-muted">Слесарные работы не внесены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. СЕКЦИЯ: ОКРАСОЧНЫЕ РАБОТЫ И МАТЕРИАЛЫ -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="bi bi-paint-bucket me-2 text-danger"></i>
                        Окрасочные работы и расходные материалы ({{ paintWorks.length }})
                    </span>
                    <button class="btn btn-danger btn-sm fw-semibold" @click="showAddPaintModal = true">
                        <i class="bi bi-plus-lg me-1"></i> Добавить окраску
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light text-center" style="font-size: 0.75rem;">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Наименование детали / элемента</th>
                                <th style="width: 100px;">Нормо-часы</th>
                                <th style="width: 120px;">Ставка за н/ч</th>
                                <th style="width: 130px;">Материалы (ЛКМ)</th>
                                <th style="width: 130px;">Итого СК</th>
                                <th style="width: 130px;" class="table-primary">Расход СТО</th>
                                <th style="width: 110px;">Остаток</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(work, idx) in paintWorks" :key="work.id">
                                <td class="text-center text-muted font-monospace">{{ idx + 1 }}</td>
                                <td>
                                    <input v-model="work.name" @change="saveWork(work)"
                                        class="form-control form-control-sm border-0 bg-transparent fw-semibold" />
                                </td>
                                <td>
                                    <input v-model.number="work.norm_hour" @change="saveWork(work)" type="number"
                                        step="0.1" class="form-control form-control-sm text-center font-monospace" />
                                </td>
                                <td>
                                    <input v-model.number="work.cost_hour" @change="saveWork(work)" type="number"
                                        step="10" class="form-control form-control-sm text-end font-monospace" />
                                </td>
                                <td>
                                    <input v-model.number="work.paint_cost" @change="saveWork(work)" type="number"
                                        step="10" class="form-control form-control-sm text-end font-monospace" />
                                </td>
                                <td class="text-end fw-bold font-monospace text-primary">
                                    {{ formatMoney(((work.norm_hour || 0) * (work.cost_hour || 0)) + (work.paint_cost ||
                                        0)) }} ₽
                                </td>
                                <td class="table-primary text-end fw-bold font-monospace">
                                    <input v-model.number="work.sto_cost" @change="saveWork(work)" type="number"
                                        step="10" class="form-control form-control-sm text-end font-monospace"
                                        placeholder="0.00" />
                                </td>
                                <td class="text-end font-monospace fw-bold"
                                    :class="getPaintDiff(work) >= 0 ? 'text-success' : 'text-danger'">
                                    {{ formatMoney(getPaintDiff(work)) }} ₽
                                </td>
                                <td class="text-center p-1">
                                    <button @click="deleteWork(work.id)" class="btn btn-xs btn-outline-danger border-0">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="paintWorks.length === 0">
                                <td colspan="9" class="text-center py-3 text-muted">Окрасочные работы не внесены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['updated']);

const loading = ref(true);
const spareParts = ref([]);
const repairWorks = ref([]);
const paintWorks = ref([]);
const summary = ref({ sum_sk: 0, sum_zap: 0, remont_sum: 0 });

const showAddPartModal = ref(false);
const showAddRepairModal = ref(false);
const showAddPaintModal = ref(false);

// Итоги
const totalPartsSk = computed(() => {
    return spareParts.value.reduce((acc, p) => acc + ((p.price_sk || 0) * (p.count || 1)), 0);
});

const totalPartsCost = computed(() => {
    return spareParts.value.reduce((acc, p) => acc + ((p.buy_price || 0) * (p.count || 1)), 0);
});

const totalWorksCost = computed(() => {
    const repair = repairWorks.value.reduce((acc, w) => acc + ((w.sto_hour || 0) * (w.sto_cost || 0)), 0);
    const paint = paintWorks.value.reduce((acc, w) => acc + (w.sto_cost || 0), 0);
    return repair + paint;
});

const totalSkSum = computed(() => {
    const worksSk = repairWorks.value.reduce((acc, w) => acc + ((w.norm_hour || 0) * (w.cost_hour || 0)), 0) +
        paintWorks.value.reduce((acc, w) => acc + ((w.norm_hour || 0) * (w.cost_hour || 0)) + (w.paint_cost || 0), 0);
    return totalPartsSk.value + worksSk;
});

const totalActualCost = computed(() => totalPartsCost.value + totalWorksCost.value);
const totalRemains = computed(() => totalSkSum.value - totalActualCost.value);
const marginPercent = computed(() => {
    if (totalSkSum.value <= 0) return 0;
    return Math.round((totalRemains.value / totalSkSum.value) * 100);
});

// Загрузка
const fetchEstimate = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/estimate`);
        spareParts.value = res.data.spare_parts || [];
        repairWorks.value = res.data.repair_works || [];
        paintWorks.value = res.data.paint_works || [];
        summary.value = res.data.summary || {};
    } catch (err) {
        console.error('Ошибка загрузки сметы:', err);
    } finally {
        loading.value = false;
    }
};

// Выбор цены закупки в 1 клик
const selectBuyPrice = async (part, price) => {
    part.buy_price = price;
    await savePart(part);
};

// Сохранение запчасти
const savePart = async (part) => {
    try {
        await axios.put(`/api/applications/${props.applicationId}/estimate/spare-parts/${part.id}`, part);
        emit('updated');
    } catch (err) {
        alert('Не удалось сохранить изменения детали');
    }
};

// Удаление запчасти
const deletePart = async (id) => {
    if (!confirm('Удалить эту запчасть из сметы?')) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/estimate/spare-parts/${id}`);
        spareParts.value = spareParts.value.filter(p => p.id !== id);
        emit('updated');
    } catch (err) {
        alert('Ошибка при удалении');
    }
};

// Расчет выгоды по детали
const getPartDiff = (part) => {
    const sk = (part.price_sk || 0) * (part.count || 1);
    const buy = (part.buy_price || 0) * (part.count || 1);
    return sk - buy;
};

const getPartDiffClass = (part) => {
    if (!part.buy_price) return 'text-muted';
    return getPartDiff(part) >= 0 ? 'text-success' : 'text-danger';
};

// Сохранение и расчет по работам
const saveWork = async (work) => {
    try {
        await axios.put(`/api/applications/${props.applicationId}/estimate/works/${work.id}`, work);
        emit('updated');
    } catch (err) {
        alert('Не удалось сохранить работу');
    }
};

const deleteWork = async (id) => {
    if (!confirm('Удалить эту операцию?')) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/estimate/works/${id}`);
        repairWorks.value = repairWorks.value.filter(w => w.id !== id);
        paintWorks.value = paintWorks.value.filter(w => w.id !== id);
        emit('updated');
    } catch (err) {
        alert('Ошибка при удалении');
    }
};

const getWorkDiff = (work) => {
    const sk = (work.norm_hour || 0) * (work.cost_hour || 0);
    const sto = (work.sto_hour || 0) * (work.sto_cost || 0);
    return sk - sto;
};

const getPaintDiff = (work) => {
    const sk = ((work.norm_hour || 0) * (work.cost_hour || 0)) + (work.paint_cost || 0);
    const sto = work.sto_cost || 0;
    return sk - sto;
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(fetchEstimate);
</script>

<style scoped>
.btn-xs {
    font-size: 0.70rem;
    padding: 0.1rem 0.35rem;
}

.font-sans {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>