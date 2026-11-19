<template>
    <div class="estimate-container">
        <!-- ПРЕЛОАДЕР -->
        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="text-muted small mt-2">Загрузка данных сметы...</div>
        </div>

        <template v-else>
            <!-- ВНУТРЕННЯЯ НАВИГАЦИЯ ПО РАЗДЕЛАМ СМЕТЫ -->
            <div class="estimate-nav-pills mb-3 d-flex flex-wrap gap-1 border-bottom pb-2">
                <button class="btn btn-sm"
                    :class="activeSection === 'parts' ? 'btn-primary fw-bold' : 'btn-outline-secondary'"
                    @click="activeSection = 'parts'">
                    <i class="bi bi-gear-fill me-1"></i>
                    Запасные части
                    <span class="badge bg-white text-dark ms-1">{{ spareParts.length }}</span>
                </button>

                <button class="btn btn-sm"
                    :class="activeSection === 'calc_works' ? 'btn-primary fw-bold' : 'btn-outline-secondary'"
                    @click="activeSection = 'calc_works'">
                    <i class="bi bi-tools me-1"></i>
                    Калькуляция (Ремонт и Окраска)
                    <span class="badge bg-white text-dark ms-1">{{ repairWorks.length + paintWorks.length }}</span>
                </button>

                <button class="btn btn-sm"
                    :class="activeSection === 'plan_works' ? 'btn-primary fw-bold' : 'btn-outline-secondary'"
                    @click="activeSection = 'plan_works'">
                    <i class="bi bi-clipboard-check me-1"></i>
                    План МРК (Ведомость)
                    <span class="badge bg-white text-dark ms-1">{{ planWorks.length }}</span>
                </button>

                <button class="btn btn-sm"
                    :class="activeSection === 'fact_works' ? 'btn-primary fw-bold' : 'btn-outline-secondary'"
                    @click="activeSection = 'fact_works'">
                    <i class="bi bi-receipt me-1"></i>
                    Факт СТО и ЗП
                    <span class="badge bg-white text-dark ms-1">{{ factWorks.length }}</span>
                </button>
            </div>

            <!-- 1. РАЗДЕЛ: ЗАПАСНЫЕ ЧАСТИ -->
            <section v-show="activeSection === 'parts'" class="estimate-section mb-4">
                <div
                    class="section-header-dark d-flex justify-content-between align-items-center mb-2 px-2 py-1 bg-dark text-white rounded">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6">ЗАПАСНЫЕ ЧАСТИ</span>
                        <span class="badge bg-secondary">{{ spareParts.length }} поз.</span>
                    </div>
                    <button class="btn btn-xs btn-outline-light" @click="addEmptyPartRow">
                        <i class="bi bi-plus-lg me-1"></i>Добавить деталь
                    </button>
                </div>

                <div class="table-responsive bg-white rounded border shadow-sm">
                    <table class="table table-bordered table-mandarin table-sm mb-0 align-middle">
                        <thead>
                            <tr class="header-blue text-center">
                                <th style="width: 32px;">#</th>
                                <th style="min-width: 220px;">Наименование</th>
                                <th style="width: 140px;">Номер детали</th>
                                <th style="width: 50px;">Кол</th>
                                <template v-if="userRole !== 'service'">
                                    <th style="width: 95px;">Цена СК</th>
                                    <th style="width: 95px;">Цена ПЗН</th>
                                    <th style="width: 85px;">СТО</th>
                                    <th style="width: 28px;" title="Выбрать СТО"></th>
                                    <th style="width: 85px;">Emex</th>
                                    <th style="width: 28px;" title="Выбрать Emex"></th>
                                    <th style="width: 85px;">AP</th>
                                    <th style="width: 28px;" title="Выбрать AP"></th>
                                    <th style="width: 85px;">EA</th>
                                    <th style="width: 28px;" title="Выбрать EA"></th>
                                    <th style="width: 100px;">Выбрано</th>
                                    <th style="width: 95px;">Разница</th>
                                </template>
                                <th style="width: 110px;">Статус</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, idx) in spareParts" :key="p.id || idx" class="data-row">
                                <td class="text-center fw-bold">{{ idx + 1 }}</td>
                                <td>
                                    <input v-model="p.name" class="table-input" @change="savePart(p)"
                                        placeholder="Название детали" />
                                </td>
                                <td>
                                    <input v-model="p.part_number" class="table-input font-monospace text-uppercase"
                                        @change="savePart(p)" placeholder="OEM код" />
                                </td>
                                <td class="text-center">
                                    <input v-model.number="p.count" type="number" class="table-input text-center"
                                        @change="savePart(p)" />
                                </td>

                                <template v-if="userRole !== 'service'">
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_ic" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_pzn" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_sto" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="p-0 text-center">
                                        <button class="btn btn-xs btn-outline-primary p-0 w-100"
                                            @click="selectSupplier(p, 'sto', p.price_sto)">→</button>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_emex" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="p-0 text-center">
                                        <button class="btn btn-xs btn-outline-primary p-0 w-100"
                                            @click="selectSupplier(p, 'emex', p.price_emex)">→</button>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_ap" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="p-0 text-center">
                                        <button class="btn btn-xs btn-outline-primary p-0 w-100"
                                            @click="selectSupplier(p, 'ap', p.price_ap)">→</button>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <input v-model.number="p.price_ea" type="number" step="0.01"
                                            class="table-input text-end" @change="savePart(p)" />
                                    </td>
                                    <td class="p-0 text-center">
                                        <button class="btn btn-xs btn-outline-primary p-0 w-100"
                                            @click="selectSupplier(p, 'ea', p.price_ea)">→</button>
                                    </td>
                                    <td class="text-end font-monospace fw-bold bg-light">
                                        {{ formatMoney(p.price_selected) }}
                                    </td>
                                    <td class="text-end font-monospace fw-bold"
                                        :class="(p.price_ic - p.price_selected) >= 0 ? 'text-success' : 'text-danger'">
                                        {{ formatMoney((p.price_ic || 0) - (p.price_selected || 0)) }}
                                    </td>
                                </template>

                                <td>
                                    <select v-model="p.status" class="table-input" @change="savePart(p)">
                                        <option value="draft">В поиске</option>
                                        <option value="ordered">Заказана</option>
                                        <option value="stock">На складе</option>
                                        <option value="issued">Выдана</option>
                                        <option value="canceled">Отказ</option>
                                    </select>
                                </td>

                                <td class="text-center p-0">
                                    <button class="btn btn-link btn-xs text-danger p-0" @click="deletePart(p, idx)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 2. РАЗДЕЛ: КАЛЬКУЛЯЦИЯ СК (РЕМОНТНЫЕ И ОКРАСОЧНЫЕ РАБОТЫ) -->
            <section v-show="activeSection === 'calc_works'" class="estimate-section mb-4">
                <!-- Ремонтные работы -->
                <div class="mb-3">
                    <div
                        class="section-header-dark d-flex justify-content-between align-items-center mb-2 px-2 py-1 bg-dark text-white rounded">
                        <span class="fw-bold fs-6">РЕМОНТНЫЕ РАБОТЫ ПО КАЛЬКУЛЯЦИИ</span>
                        <button class="btn btn-xs btn-outline-light" @click="addWorkRow('repair')">
                            <i class="bi bi-plus-lg me-1"></i>Добавить работу
                        </button>
                    </div>

                    <div class="table-responsive bg-white rounded border shadow-sm">
                        <table class="table table-bordered table-mandarin table-sm mb-0 align-middle">
                            <thead>
                                <tr class="header-blue text-center">
                                    <th style="width: 32px;">#</th>
                                    <th>Наименование работы</th>
                                    <th style="width: 90px;">Часы СК</th>
                                    <th style="width: 90px;">Часы ПЗН</th>
                                    <th style="width: 100px;">Ставка н/ч</th>
                                    <th style="width: 110px;">Сумма СК</th>
                                    <th style="width: 110px;">Сумма ПЗН</th>
                                    <th style="width: 36px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(w, idx) in repairWorks" :key="w.id || idx" class="data-row">
                                    <td class="text-center fw-bold">{{ idx + 1 }}</td>
                                    <td><input v-model="w.name" class="table-input" @change="saveWork(w, 'calc')" />
                                    </td>
                                    <td><input v-model.number="w.norm_hours" type="number" step="0.1"
                                            class="table-input text-center"
                                            @change="recalcWork(w); saveWork(w, 'calc')" /></td>
                                    <td><input v-model.number="w.pzn_hours" type="number" step="0.1"
                                            class="table-input text-center"
                                            @change="recalcWork(w); saveWork(w, 'calc')" /></td>
                                    <td><input v-model.number="w.price_per_hour" type="number"
                                            class="table-input text-end" @change="recalcWork(w); saveWork(w, 'calc')" />
                                    </td>
                                    <td class="text-end font-monospace fw-bold">{{ formatMoney(w.sum_ic) }}</td>
                                    <td class="text-end font-monospace fw-bold">{{ formatMoney(w.sum_pzn) }}</td>
                                    <td class="text-center p-0">
                                        <button class="btn btn-link btn-xs text-danger p-0"
                                            @click="deleteWork(w, idx, 'repair')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Окрасочные работы -->
                <div>
                    <div
                        class="section-header-dark d-flex justify-content-between align-items-center mb-2 px-2 py-1 bg-dark text-white rounded">
                        <span class="fw-bold fs-6">ОКРАСОЧНЫЕ РАБОТЫ И МАТЕРИАЛЫ</span>
                        <button class="btn btn-xs btn-outline-light" @click="addWorkRow('paint')">
                            <i class="bi bi-plus-lg me-1"></i>Добавить окраску
                        </button>
                    </div>

                    <div class="table-responsive bg-white rounded border shadow-sm">
                        <table class="table table-bordered table-mandarin table-sm mb-0 align-middle">
                            <thead>
                                <tr class="header-blue text-center">
                                    <th style="width: 32px;">#</th>
                                    <th>Деталь / Элемент окраски</th>
                                    <th style="width: 90px;">Часы СК</th>
                                    <th style="width: 100px;">Ставка н/ч</th>
                                    <th style="width: 110px;">Работа СК</th>
                                    <th style="width: 110px;">Материалы</th>
                                    <th style="width: 110px;">Итого СК</th>
                                    <th style="width: 36px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(w, idx) in paintWorks" :key="w.id || idx" class="data-row">
                                    <td class="text-center fw-bold">{{ idx + 1 }}</td>
                                    <td><input v-model="w.name" class="table-input" @change="saveWork(w, 'calc')" />
                                    </td>
                                    <td><input v-model.number="w.norm_hours" type="number" step="0.1"
                                            class="table-input text-center"
                                            @change="recalcPaint(w); saveWork(w, 'calc')" /></td>
                                    <td><input v-model.number="w.price_per_hour" type="number"
                                            class="table-input text-end"
                                            @change="recalcPaint(w); saveWork(w, 'calc')" /></td>
                                    <td class="text-end font-monospace">{{ formatMoney((w.norm_hours || 0) *
                                        (w.price_per_hour ||
                                            0)) }}</td>
                                    <td><input v-model.number="w.sum_materials" type="number" step="0.01"
                                            class="table-input text-end font-monospace"
                                            @change="recalcPaint(w); saveWork(w, 'calc')" /></td>
                                    <td class="text-end font-monospace fw-bold">{{ formatMoney(w.sum_ic) }}</td>
                                    <td class="text-center p-0">
                                        <button class="btn btn-link btn-xs text-danger p-0"
                                            @click="deleteWork(w, idx, 'paint')"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 3. РАЗДЕЛ: ПЛАНОВЫЕ РАБОТЫ МРК -->
            <section v-show="activeSection === 'plan_works'" class="estimate-section mb-4">
                <div
                    class="section-header-dark d-flex justify-content-between align-items-center mb-2 px-2 py-1 bg-dark text-white rounded">
                    <span class="fw-bold fs-6">ПЛАНОВЫЕ РАБОТЫ МРК (РЕМОНТНАЯ ВЕДОМОСТЬ)</span>
                    <button class="btn btn-xs btn-outline-light" @click="addPlanRow">
                        <i class="bi bi-plus-lg me-1"></i>Добавить позицию плана
                    </button>
                </div>

                <div class="table-responsive bg-white rounded border shadow-sm">
                    <table class="table table-bordered table-mandarin table-sm mb-0 align-middle">
                        <thead>
                            <tr class="header-blue text-center">
                                <th style="width: 32px;">#</th>
                                <th style="width: 120px;">Категория</th>
                                <th>Наименование работы</th>
                                <th style="width: 90px;">Н/Ч</th>
                                <th style="width: 120px;">План. сумма</th>
                                <th>Примечание</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(pw, idx) in planWorks" :key="pw.id || idx" class="data-row">
                                <td class="text-center fw-bold">{{ idx + 1 }}</td>
                                <td>
                                    <select v-model="pw.work_category" class="table-input"
                                        @change="saveWork(pw, 'plan')">
                                        <option value="repair">Ремонт</option>
                                        <option value="paint">Окраска</option>
                                    </select>
                                </td>
                                <td><input v-model="pw.name" class="table-input" @change="saveWork(pw, 'plan')" /></td>
                                <td><input v-model.number="pw.norm_hours" type="number" step="0.1"
                                        class="table-input text-center" @change="saveWork(pw, 'plan')" /></td>
                                <td><input v-model.number="pw.price_plan" type="number" step="0.01"
                                        class="table-input text-end font-monospace" @change="saveWork(pw, 'plan')" />
                                </td>
                                <td><input v-model="pw.comment" class="table-input" @change="saveWork(pw, 'plan')" />
                                </td>
                                <td class="text-center p-0">
                                    <button class="btn btn-link btn-xs text-danger p-0"
                                        @click="deletePlanWork(pw, idx)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 4. РАЗДЕЛ: ФАКТИЧЕСКИЕ РАБОТЫ СТО -->
            <section v-show="activeSection === 'fact_works'" class="estimate-section mb-4">
                <div
                    class="section-header-dark d-flex justify-content-between align-items-center mb-2 px-2 py-1 bg-dark text-white rounded">
                    <span class="fw-bold fs-6">ФАКТИЧЕСКИЕ РАБОТЫ СТО И СДЕЛЬНАЯ ЗП</span>
                    <button class="btn btn-xs btn-outline-light" @click="addFactRow">
                        <i class="bi bi-plus-lg me-1"></i>Добавить факт
                    </button>
                </div>

                <div class="table-responsive bg-white rounded border shadow-sm">
                    <table class="table table-bordered table-mandarin table-sm mb-0 align-middle">
                        <thead>
                            <tr class="header-blue text-center">
                                <th style="width: 32px;">#</th>
                                <th>Наименование работы</th>
                                <th style="width: 80px;">Факт н/ч</th>
                                <th style="width: 120px;">Сумма СТО</th>
                                <th style="width: 120px;">ЗП мастера</th>
                                <th style="width: 120px;">Дата</th>
                                <th style="width: 36px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(fw, idx) in factWorks" :key="fw.id || idx" class="data-row">
                                <td class="text-center fw-bold">{{ idx + 1 }}</td>
                                <td><input v-model="fw.name" class="table-input" @change="saveWork(fw, 'fact')" /></td>
                                <td><input v-model.number="fw.fact_hours" type="number" step="0.1"
                                        class="table-input text-center" @change="saveWork(fw, 'fact')" /></td>
                                <td><input v-model.number="fw.price_sto" type="number" step="0.01"
                                        class="table-input text-end font-monospace" @change="saveWork(fw, 'fact')" />
                                </td>
                                <td><input v-model.number="fw.salary_sum" type="number" step="0.01"
                                        class="table-input text-end font-monospace" @change="saveWork(fw, 'fact')" />
                                </td>
                                <td><input v-model="fw.done_date" type="date" class="table-input"
                                        @change="saveWork(fw, 'fact')" />
                                </td>
                                <td class="text-center p-0">
                                    <button class="btn btn-link btn-xs text-danger p-0"
                                        @click="deleteFactWork(fw, idx)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['kpi-updated']);

const loading = ref(true);
const activeSection = ref('parts');
const userRole = ref('mrk');

const spareParts = ref([]);
const repairWorks = ref([]);
const paintWorks = ref([]);
const planWorks = ref([]);
const factWorks = ref([]);

const fetchEstimateData = async () => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/estimate`);
        userRole.value = res.data.role || 'mrk';
        spareParts.value = res.data.spare_parts || [];
        repairWorks.value = res.data.repair_works || [];
        paintWorks.value = res.data.paint_works || [];
        planWorks.value = res.data.plan_works || [];
        factWorks.value = res.data.fact_works || [];

        emit('kpi-updated', res.data.kpi);
    } catch (err) {
        console.error('Ошибка загрузки сметы:', err);
    } finally {
        loading.value = false;
    }
};

// Запчасти
const addEmptyPartRow = () => {
    spareParts.value.push({
        id: null,
        name: '',
        part_number: '',
        count: 1,
        price_ic: 0,
        price_pzn: 0,
        price_sto: 0,
        price_emex: 0,
        price_ap: 0,
        price_ea: 0,
        selected_supplier: null,
        price_selected: 0,
        status: 'draft',
    });
};

const selectSupplier = (part, supplierKey, price) => {
    part.selected_supplier = supplierKey;
    part.price_selected = price || 0;
    savePart(part);
};

const savePart = async (part) => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/estimate/parts`, part);
        if (!part.id && res.data.data?.id) {
            part.id = res.data.data.id;
        }
    } catch (e) {
        console.error('Ошибка сохранения детали:', e);
    }
};

const deletePart = async (part, idx) => {
    if (part.id) {
        await axios.delete(`/api/estimate/parts/${part.id}`);
    }
    spareParts.value.splice(idx, 1);
};

// Работы
const addWorkRow = (category) => {
    const target = category === 'repair' ? repairWorks : paintWorks;
    target.value.push({
        id: null,
        work_category: category,
        name: '',
        norm_hours: 1,
        pzn_hours: 1,
        price_per_hour: 1500,
        sum_ic: 1500,
        sum_pzn: 1500,
        sum_materials: category === 'paint' ? 3000 : 0,
    });
};

const recalcWork = (w) => {
    w.sum_ic = (w.norm_hours || 0) * (w.price_per_hour || 0);
    w.sum_pzn = (w.pzn_hours || 0) * (w.price_per_hour || 0);
};

const recalcPaint = (w) => {
    const labor = (w.norm_hours || 0) * (w.price_per_hour || 0);
    w.sum_ic = labor + (w.sum_materials || 0);
};

const saveWork = async (w, tableType) => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/estimate/works`, {
            ...w,
            table_type: tableType,
        });
        if (!w.id && res.data.data?.id) {
            w.id = res.data.data.id;
        }
    } catch (e) {
        console.error('Ошибка сохранения работы:', e);
    }
};

const deleteWork = async (w, idx, category) => {
    if (w.id) {
        await axios.delete(`/api/estimate/works/${w.id}`, { params: { table_type: 'calc' } });
    }
    if (category === 'repair') repairWorks.value.splice(idx, 1);
    if (category === 'paint') paintWorks.value.splice(idx, 1);
};

// План
const addPlanRow = () => {
    planWorks.value.push({
        id: null,
        work_category: 'repair',
        name: '',
        norm_hours: 1,
        price_plan: 0,
        comment: '',
    });
};

const deletePlanWork = async (pw, idx) => {
    if (pw.id) {
        await axios.delete(`/api/estimate/works/${pw.id}`, { params: { table_type: 'plan' } });
    }
    planWorks.value.splice(idx, 1);
};

// Факт
const addFactRow = () => {
    factWorks.value.push({
        id: null,
        work_category: 'repair',
        name: '',
        fact_hours: 1,
        price_sto: 0,
        salary_sum: 0,
        done_date: new Date().toISOString().slice(0, 10),
    });
};

const deleteFactWork = async (fw, idx) => {
    if (fw.id) {
        await axios.delete(`/api/estimate/works/${fw.id}`, { params: { table_type: 'fact' } });
    }
    factWorks.value.splice(idx, 1);
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(() => {
    fetchEstimateData();
});
</script>

<style scoped>
.table-input {
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    font-size: 0.72rem;
    padding: 2px 4px;
    border-radius: 2px;
}

.table-input:focus {
    border-color: #2563eb;
    background: #ffffff;
    outline: none;
}

.header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-size: 0.70rem !important;
    font-weight: 600;
    padding: 4px 2px !important;
    border: 1px solid #000000 !important;
}

.data-row td {
    padding: 2px 3px !important;
    font-size: 0.71rem !important;
    border: 1px solid #dee2e6;
}

.btn-xs {
    font-size: 0.68rem;
    padding: 1px 4px;
}
</style>