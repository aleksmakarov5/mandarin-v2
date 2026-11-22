<template>
    <div class="calculation-works-module mb-3">
        <!-- ======================================================== -->
        <!-- 1. КАРТОЧКА: РЕМОНТНЫЕ РАБОТЫ ПО КАЛЬКУЛЯЦИИ              -->
        <!-- ======================================================== -->
        <div class="estimate-block-card bg-white border rounded mb-3 shadow-xs overflow-hidden">
            <!-- ШАПКА В ЕДИНОМ СТИЛЕ С ПЛАНОМ И ФАКТОМ -->
            <div class="card-accordion-header px-3 py-2 d-flex justify-content-between align-items-center cursor-pointer select-none"
                @click="isRepairCollapsed = !isRepairCollapsed">
                <!-- Левая часть: квадратная иконка + Заголовок + счетчик (N) + стрелка -->
                <div class="d-flex align-items-center gap-2">
                    <div class="section-icon-box icon-repair d-flex align-items-center justify-content-center">
                        <i class="bi bi-wrench-adjustable-circle-fill"></i>
                    </div>
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-1.5 section-title-text">
                        <span>Ремонтные работы по калькуляции</span>
                        <span class="text-secondary fw-normal count-text">({{ repairWorks.length }})</span>
                        <i class="bi bi-chevron-right accordion-chevron ms-1 text-secondary"
                            :class="{ 'accordion-chevron-rotated': !isRepairCollapsed }"></i>
                    </h6>
                </div>

                <!-- Правая часть: бейджи СК и ПЗН + кнопка Загрузить + История -->
                <div class="d-flex align-items-center gap-2" @click.stop>
                    <!-- Бейдж Сумма СК -->
                    <div class="badge-black-pill font-monospace" title="Сумма страховой компании">
                        <span class="pill-label">СК:</span>
                        <span class="pill-val">{{ formatMoney(repairTotals.sumSk) }} ₽</span>
                    </div>

                    <!-- Бейдж Сумма ПЗН -->
                    <div class="badge-black-pill font-monospace"
                        :class="{ 'badge-pzn-alert': repairTotals.sumPzn !== repairTotals.sumSk }" title="Сумма ПЗН">
                        <span class="pill-label text-info">ПЗН:</span>
                        <span class="pill-val"
                            :class="repairTotals.sumPzn !== repairTotals.sumSk ? 'text-warning' : 'text-info'">
                            {{ formatMoney(repairTotals.sumPzn) }} ₽
                        </span>
                    </div>

                    <!-- Кнопка "Загрузить" в общем оранжевом контурном стиле -->
                    <button type="button"
                        class="btn btn-sm btn-outline-mandarin fw-semibold d-flex align-items-center shadow-xs"
                        @click="onUploadClick('repair')">
                        <i class="bi bi-upload me-1.5"></i> Загрузить
                    </button>

                    <!-- Кнопка "История" в общем стиле -->
                    <button type="button"
                        class="btn btn-sm btn-outline-secondary btn-history fw-semibold d-flex align-items-center shadow-xs"
                        @click="openHistoryModal('repair')">
                        <i class="bi bi-clock-history me-1 text-primary"></i> История
                    </button>
                </div>
            </div>

            <!-- ТЕЛО ТАБЛИЦЫ РЕМОНТНЫХ РАБОТ -->
            <div v-show="!isRepairCollapsed" class="card-accordion-body border-top">
                <div class="table-responsive">
                    <table class="mandarin-calc-table mb-0 align-middle">
                        <thead>
                            <tr class="header-blue text-center">
                                <th style="width: 34px;">#</th>
                                <th class="text-start ps-2">Наименование</th>
                                <th style="width: 100px;">Цена</th>
                                <th style="width: 85px;">Нормочасы</th>
                                <th style="width: 85px;" class="col-pzn-header">Часы ПЗН</th>
                                <th style="width: 120px;">Сумма страховой</th>
                                <th style="width: 120px;" class="col-pzn-header">Сумма ПЗН</th>
                                <th style="width: 32px;"></th>
                                <th style="width: 110px;" title="Перенос позиции в малярку">Перенос</th>
                            </tr>
                            <tr class="header-totals">
                                <th></th>
                                <th class="text-start ps-2 text-white fw-bold">Итого</th>
                                <th colspan="3"></th>
                                <th class="text-end font-monospace text-white fw-bold pe-2">{{
                                    formatMoney(repairTotals.sumSk) }} р.</th>
                                <th class="text-end font-monospace text-white fw-bold pe-2">{{
                                    formatMoney(repairTotals.sumPzn) }} р.</th>
                                <th colspan="2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(work, idx) in repairWorks" :key="work.id" class="calc-row">
                                <td class="text-center font-monospace text-muted fw-bold">{{ idx + 1 }}</td>

                                <!-- Наименование -->
                                <td class="text-start ps-2">
                                    <input v-model="work.name" class="table-cell-input fw-semibold"
                                        placeholder="Наименование операции..."
                                        @change="saveField(work, 'name', work.name)" />
                                </td>

                                <!-- Ставка н/ч -->
                                <td class="text-end pe-2 font-monospace">
                                    <input v-model.number="work.price" type="number" step="50"
                                        class="table-cell-input text-end font-monospace"
                                        @change="onPriceOrHoursChange(work)" />
                                </td>

                                <!-- Нормочасы СК -->
                                <td class="text-center font-monospace">
                                    <input v-model.number="work.count_hours" type="number" step="0.05"
                                        class="table-cell-input text-center font-monospace"
                                        @change="onPriceOrHoursChange(work)" />
                                </td>

                                <!-- Часы ПЗН -->
                                <td class="text-center font-monospace col-pzn-cell"
                                    :class="{ 'col-pzn-diff': isPznDiff(work) }">
                                    <input v-model.number="work.hours_pzn" type="number" step="0.05"
                                        class="table-cell-input text-center font-monospace fw-bold"
                                        @change="onHoursPznChange(work)" />
                                </td>

                                <!-- Сумма страховой -->
                                <td class="text-end pe-2 font-monospace fw-bold text-dark">
                                    {{ formatMoney(work.sum_sk) }} р.
                                </td>

                                <!-- Сумма ПЗН -->
                                <td class="text-end pe-2 font-monospace fw-bold col-pzn-cell"
                                    :class="{ 'col-pzn-diff': isPznDiff(work) }">
                                    {{ formatMoney(work.sum_pzn) }} р.
                                </td>

                                <!-- Удаление -->
                                <td class="text-center p-0">
                                    <button type="button" class="btn btn-link text-secondary p-0 hover-danger"
                                        @click="deleteWork(work, idx, 'repair')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>

                                <!-- Кнопка переноса в малярку -->
                                <td class="text-center p-1">
                                    <button type="button" class="btn-transfer-pill to-paint"
                                        title="Перенести позицию в окрасочные работы"
                                        @click="transferWork(work, 'paint')">
                                        <i class="bi bi-paint-bucket me-1"></i> В малярку &rarr;
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="repairWorks.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted small">
                                    В калькуляции нет ремонтных работ. Добавьте вручную кнопкой ниже.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-2 bg-light border-top d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                        @click="addManualRow(false)">
                        <i class="bi bi-plus-circle me-1.5"></i> Добавить ремонтную работу
                    </button>
                </div>
            </div>
        </div>


        <!-- ======================================================== -->
        <!-- 2. КАРТОЧКА: ОКРАСОЧНЫЕ РАБОТЫ ПО КАЛЬКУЛЯЦИИ             -->
        <!-- ======================================================== -->
        <div class="estimate-block-card bg-white border rounded mb-3 shadow-xs overflow-hidden">
            <!-- ШАПКА В ЕДИНОМ СТИЛЕ С ПЛАНОМ И ФАКТОМ -->
            <div class="card-accordion-header px-3 py-2 d-flex justify-content-between align-items-center cursor-pointer select-none"
                @click="isPaintCollapsed = !isPaintCollapsed">
                <!-- Левая часть: квадратная иконка + Заголовок + счетчик (N) + стрелка -->
                <div class="d-flex align-items-center gap-2">
                    <div class="section-icon-box icon-paint d-flex align-items-center justify-content-center">
                        <i class="bi bi-paint-bucket"></i>
                    </div>
                    <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-1.5 section-title-text">
                        <span>Окрасочные работы по калькуляции</span>
                        <span class="text-secondary fw-normal count-text">({{ paintWorks.length }})</span>
                        <i class="bi bi-chevron-right accordion-chevron ms-1 text-secondary"
                            :class="{ 'accordion-chevron-rotated': !isPaintCollapsed }"></i>
                    </h6>
                </div>

                <!-- Правая часть: бейджи сумм + ЛКМ + Загрузить + История -->
                <div class="d-flex align-items-center gap-2" @click.stop>
                    <!-- Бейдж СК -->
                    <div class="badge-black-pill font-monospace" title="Сумма страховой компании">
                        <span class="pill-label">СК:</span>
                        <span class="pill-val">{{ formatMoney(paintTotals.sumSk) }} ₽</span>
                    </div>

                    <!-- Бейдж ПЗН -->
                    <div class="badge-black-pill font-monospace"
                        :class="{ 'badge-pzn-alert': paintTotals.sumPzn !== paintTotals.sumSk }" title="Сумма ПЗН">
                        <span class="pill-label text-info">ПЗН:</span>
                        <span class="pill-val"
                            :class="paintTotals.sumPzn !== paintTotals.sumSk ? 'text-warning' : 'text-info'">
                            {{ formatMoney(paintTotals.sumPzn) }} ₽
                        </span>
                    </div>

                    <!-- Бейдж ЛКМ (Одобренная / Плановая) -->
                    <div class="badge-black-pill badge-lkm-pill font-monospace"
                        title="Стоимость расходных материалов ЛКМ (Одобренная / Плановая)">
                        <span class="pill-label text-warning">ЛКМ:</span>
                        <span class="pill-val text-warning">{{ formatMoney(materials.approved) }} / {{
                            formatMoney(materials.plan) }} ₽</span>
                    </div>

                    <!-- Кнопка "Загрузить" -->
                    <button type="button"
                        class="btn btn-sm btn-outline-mandarin fw-semibold d-flex align-items-center shadow-xs"
                        @click="onUploadClick('paint')">
                        <i class="bi bi-upload me-1.5"></i> Загрузить
                    </button>

                    <!-- Кнопка "История" -->
                    <button type="button"
                        class="btn btn-sm btn-outline-secondary btn-history fw-semibold d-flex align-items-center shadow-xs"
                        @click="openHistoryModal('paint')">
                        <i class="bi bi-clock-history me-1 text-primary"></i> История
                    </button>
                </div>
            </div>

            <!-- ТЕЛО ТАБЛИЦЫ ОКРАСОЧНЫХ РАБОТ -->
            <div v-show="!isPaintCollapsed" class="card-accordion-body border-top">
                <div class="table-responsive">
                    <table class="mandarin-calc-table mb-0 align-middle">
                        <thead>
                            <tr class="header-blue text-center">
                                <th style="width: 34px;">#</th>
                                <th class="text-start ps-2">Наименование</th>
                                <th style="width: 100px;">Цена</th>
                                <th style="width: 85px;">Нормочасы</th>
                                <th style="width: 85px;" class="col-pzn-header">Часы ПЗН</th>
                                <th style="width: 120px;">Сумма страховой</th>
                                <th style="width: 120px;" class="col-pzn-header">Сумма ПЗН</th>
                                <th style="width: 32px;"></th>
                                <th style="width: 110px;" title="Перенос позиции в слесарку">Перенос</th>
                            </tr>
                            <tr class="header-totals">
                                <th></th>
                                <th class="text-start ps-2 text-white fw-bold">Итого</th>
                                <th colspan="3"></th>
                                <th class="text-end font-monospace text-white fw-bold pe-2">{{
                                    formatMoney(paintTotals.sumSk) }} р.</th>
                                <th class="text-end font-monospace text-white fw-bold pe-2">{{
                                    formatMoney(paintTotals.sumPzn) }} р.</th>
                                <th colspan="2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(work, idx) in paintWorks" :key="work.id" class="calc-row">
                                <td class="text-center font-monospace text-muted fw-bold">{{ idx + 1 }}</td>

                                <td>
                                    <input v-model="work.name" class="table-cell-input fw-semibold"
                                        placeholder="Окрасочная операция..."
                                        @change="saveField(work, 'name', work.name)" />
                                </td>

                                <td class="text-end pe-2 font-monospace">
                                    <input v-model.number="work.price" type="number" step="50"
                                        class="table-cell-input text-end font-monospace"
                                        @change="onPriceOrHoursChange(work)" />
                                </td>

                                <td class="text-center font-monospace">
                                    <input v-model.number="work.count_hours" type="number" step="0.05"
                                        class="table-cell-input text-center font-monospace"
                                        @change="onPriceOrHoursChange(work)" />
                                </td>

                                <td class="text-center font-monospace col-pzn-cell"
                                    :class="{ 'col-pzn-diff': isPznDiff(work) }">
                                    <input v-model.number="work.hours_pzn" type="number" step="0.05"
                                        class="table-cell-input text-center font-monospace fw-bold"
                                        @change="onHoursPznChange(work)" />
                                </td>

                                <td class="text-end pe-2 font-monospace fw-bold text-dark">
                                    {{ formatMoney(work.sum_sk) }} р.
                                </td>

                                <td class="text-end pe-2 font-monospace fw-bold col-pzn-cell"
                                    :class="{ 'col-pzn-diff': isPznDiff(work) }">
                                    {{ formatMoney(work.sum_pzn) }} р.
                                </td>

                                <td class="text-center p-0">
                                    <button type="button" class="btn btn-link text-secondary p-0 hover-danger"
                                        @click="deleteWork(work, idx, 'paint')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>

                                <!-- Кнопка переноса в слесарку -->
                                <td class="text-center p-1">
                                    <button type="button" class="btn-transfer-pill to-repair"
                                        title="Перенести позицию в ремонтные (слесарные) работы"
                                        @click="transferWork(work, 'repair')">
                                        <i class="bi bi-tools me-1"></i> &larr; В слесарку
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="paintWorks.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted small">
                                    В калькуляции нет окрасочных работ. Добавьте вручную кнопкой ниже.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-2 bg-light border-top d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                        @click="addManualRow(true)">
                        <i class="bi bi-plus-circle me-1.5"></i> Добавить окрасочную работу
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- РАСХОДНЫЕ МАТЕРИАЛЫ (ЛКМ)                                -->
                <!-- ======================================================== -->
                <div class="p-3 bg-white border-top">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-droplet-half text-primary fs-6"></i>
                        <span class="fw-bold small text-dark">Стоимость расходных материалов (ЛКМ):</span>
                    </div>

                    <div class="row g-2" style="max-width: 580px;">
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Одобренная страховой (₽):</label>
                            <div class="input-group input-group-sm">
                                <input v-model.number="materials.approved" type="number" step="0.01"
                                    class="form-control font-monospace fw-bold" placeholder="0.00"
                                    @change="saveMaterials" />
                                <span class="input-group-text bg-light text-muted font-monospace">₽</span>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Плановая закупка / СТО (₽):</label>
                            <div class="input-group input-group-sm">
                                <input v-model.number="materials.plan" type="number" step="0.01"
                                    class="form-control font-monospace fw-bold" placeholder="0.00"
                                    @change="saveMaterials" />
                                <span class="input-group-text bg-light text-muted font-monospace">₽</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ -->
        <Teleport to="body">
            <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 820px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-primary fs-5"></i>
                            История изменений (Ремонтные и Окрасочные работы)
                        </h6>
                        <button type="button" class="btn-close" @click="showHistoryModal = false"></button>
                    </div>
                    <div class="p-3" style="max-height: 480px; overflow-y: auto;">
                        <div v-if="historyList.length === 0" class="text-center py-4 text-muted small">
                            История пока пуста.
                        </div>
                        <table v-else class="table table-sm table-striped font-monospace small align-middle mb-0">
                            <thead>
                                <tr class="text-muted border-bottom">
                                    <th style="width: 130px;">Дата / Время</th>
                                    <th style="width: 150px;">Сотрудник</th>
                                    <th style="width: 140px;">Действие / Поле</th>
                                    <th>Было</th>
                                    <th>Стало</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="h in historyList" :key="h.id">
                                    <td>{{ formatDateTime(h.created_at) }}</td>
                                    <td class="fw-semibold">{{ h.user ? (h.user.last_name ? (h.user.last_name + ' ' +
                                        (h.user.name || '')) : h.user.name) : 'Система' }}</td>
                                    <td>
                                        <span class="badge"
                                            :class="h.action_type && h.action_type.includes('transfer') ? 'bg-primary' : 'bg-light text-dark border'">
                                            {{ translateAction(h.action_type, h.field_name) }}
                                        </span>
                                    </td>
                                    <td class="text-danger text-truncate" style="max-width: 180px;"
                                        :title="h.old_value">{{ h.old_value || '—' }}</td>
                                    <td class="text-success fw-bold text-truncate" style="max-width: 180px;"
                                        :title="h.new_value">{{ h.new_value || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: { type: [Number, String], required: true },
});

const emit = defineEmits(['updated']);

// Обе таблицы свернуты по умолчанию
const isRepairCollapsed = ref(true);
const isPaintCollapsed = ref(true);

const repairWorks = ref([]);
const paintWorks = ref([]);
const materials = ref({ approved: 0, plan: 0 });

const showHistoryModal = ref(false);
const historyList = ref([]);

const fetchWorks = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/calculation-works`);
        repairWorks.value = res.data.repair_works || [];
        paintWorks.value = res.data.paint_works || [];
        materials.value = res.data.materials || { approved: 0, plan: 0 };
    } catch (err) {
        console.error('Ошибка загрузки калькуляции работ:', err);
    }
};

const repairTotals = computed(() => {
    let sk = 0, pzn = 0;
    repairWorks.value.forEach(w => {
        sk += Number(w.sum_sk || 0);
        pzn += Number(w.sum_pzn || 0);
    });
    return { sumSk: sk, sumPzn: pzn };
});

const paintTotals = computed(() => {
    let sk = 0, pzn = 0;
    paintWorks.value.forEach(w => {
        sk += Number(w.sum_sk || 0);
        pzn += Number(w.sum_pzn || 0);
    });
    return { sumSk: sk, sumPzn: pzn };
});

const isPznDiff = (work) => {
    const norm = parseFloat(work.count_hours) || 0;
    const pzn = parseFloat(work.hours_pzn) || 0;
    return Math.abs(norm - pzn) > 0.001;
};

const onHoursPznChange = (work) => {
    let pzn = parseFloat(work.hours_pzn) || 0;
    if (pzn <= 0) {
        pzn = parseFloat(work.count_hours) || 0;
        work.hours_pzn = pzn;
    }
    work.sum_pzn = Math.round(Number(work.price || 0) * pzn * 100) / 100;
    saveField(work, 'hours_pzn', pzn);
};

const onPriceOrHoursChange = (work) => {
    const price = parseFloat(work.price) || 0;
    const hours = parseFloat(work.count_hours) || 0;

    work.sum_sk = Math.round(price * hours * 100) / 100;

    if (!work.hours_pzn || parseFloat(work.hours_pzn) === 0) {
        work.hours_pzn = hours;
    }
    work.sum_pzn = Math.round(price * parseFloat(work.hours_pzn) * 100) / 100;

    saveField(work, 'count_hours', hours);
    saveField(work, 'price', price);
};

const saveField = async (work, field, value) => {
    try {
        await axios.put(`/api/applications/${props.applicationId}/calculation-works/${work.id}/field`, {
            field,
            value,
        });
        emit('updated');
    } catch (err) {
        console.error(`Ошибка сохранения ${field}:`, err);
    }
};

const transferWork = async (work, target) => {
    try {
        await axios.post(`/api/applications/${props.applicationId}/calculation-works/${work.id}/transfer`);
        if (target === 'paint') {
            repairWorks.value = repairWorks.value.filter(w => w.id !== work.id);
            work.is_paint = true;
            paintWorks.value.push(work);
        } else {
            paintWorks.value = paintWorks.value.filter(w => w.id !== work.id);
            work.is_paint = false;
            repairWorks.value.push(work);
        }
        emit('updated');
    } catch (err) {
        console.error('Ошибка переноса:', err);
    }
};

const addManualRow = async (isPaint) => {
    const appId = Number(props.applicationId);
    if (!appId || isNaN(appId)) {
        alert('Не удалось определить ID дела');
        return;
    }

    try {
        const res = await axios.post(`/api/applications/${appId}/calculation-works`, {
            id_application: appId,
            name: isPaint ? 'ОКРАСКА ДЕТАЛИ' : 'РЕМОНТНАЯ ОПЕРАЦИЯ',
            price: 1000.00,
            count_hours: 0.5,
            hours_pzn: 0.5,
            is_paint: Boolean(isPaint),
        });

        if (res.data?.data) {
            if (isPaint) {
                paintWorks.value.push(res.data.data);
            } else {
                repairWorks.value.push(res.data.data);
            }
            emit('updated');
        }
    } catch (err) {
        console.error('Ошибка добавления строки:', err);
        alert(err.response?.data?.message || 'Ошибка добавления строки в калькуляцию');
    }
};

const deleteWork = async (work, idx, type) => {
    if (!confirm(`Удалить операцию "${work.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/calculation-works/${work.id}`);
        if (type === 'repair') repairWorks.value.splice(idx, 1);
        else paintWorks.value.splice(idx, 1);
        emit('updated');
    } catch (err) {
        console.error('Ошибка удаления:', err);
    }
};

const saveMaterials = async () => {
    try {
        await axios.post(`/api/applications/${props.applicationId}/calculation-works/materials`, {
            paint_materials_approved: materials.value.approved,
            paint_materials_plan: materials.value.plan,
        });
        emit('updated');
    } catch (err) {
        console.error('Ошибка сохранения ЛКМ:', err);
    }
};

const openHistoryModal = (type) => {
    showHistoryModal.value = true;
    axios.get(`/api/applications/${props.applicationId}/calculation-works/history`)
        .then(res => {
            historyList.value = res.data.data || [];
        })
        .catch(err => {
            console.error('Ошибка загрузки истории:', err);
        });
};

const onUploadClick = (tableType) => {
    alert(`Загрузка данных из калькуляции для ${tableType === 'paint' ? 'окрасочных' : 'ремонтных'} работ`);
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const translateAction = (type, field) => {
    if (type === 'transferred_to_paint') return 'В малярку';
    if (type === 'transferred_to_repair') return 'В слесарку';
    if (type === 'created') return 'Добавление';
    if (type === 'deleted') return 'Удаление';
    if (field === 'hours_pzn') return 'Часы ПЗН';
    if (field === 'count_hours') return 'Нормочасы';
    if (field === 'price') return 'Ставка н/ч';
    if (field === 'name') return 'Наименование';
    return field || type;
};

onMounted(fetchWorks);
</script>

<style scoped>
/* 1. ОБЩИЙ СТИЛЬ КАРТОЧЕК КАК У ПЛАНА / ФАКТА */
.estimate-block-card {
    border-color: #e2e8f0 !important;
    transition: box-shadow 0.15s ease;
}

.estimate-block-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card-accordion-header {
    background-color: #ffffff;
    min-height: 48px;
    transition: background-color 0.12s ease;
}

.card-accordion-header:hover {
    background-color: #f8fafc;
}

/* Квадратные иконки в стиле Плана/Факта */
.section-icon-box {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.icon-repair {
    background-color: #0284c7;
    color: #ffffff;
}

.icon-paint {
    background-color: #06b6d4;
    color: #ffffff;
}

.section-title-text {
    font-size: 0.88rem;
    letter-spacing: -0.01em;
}

.count-text {
    font-size: 0.85rem;
}

.accordion-chevron {
    font-size: 0.75rem;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}

.accordion-chevron-rotated {
    transform: rotate(90deg);
}

/* 2. ЧЕРНЫЕ КОМПАКТНЫЕ БЕЙДЖИ СУММ */
.badge-black-pill {
    background-color: #1e293b;
    color: #ffffff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.76rem;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.pill-label {
    font-size: 0.68rem;
    opacity: 0.85;
}

.pill-val {
    font-weight: 700;
}

.badge-pzn-alert {
    box-shadow: inset 0 0 0 1px #eab308;
}

.badge-lkm-pill {
    border: 1px solid rgba(245, 158, 11, 0.4);
}

/* 3. КНОПКА "ЗАГРУЗИТЬ" В ОБЩЕМ ОРАНЖЕВОМ СТИЛЕ */
.btn-outline-mandarin {
    border: 1px solid #f97316;
    color: #ea580c;
    background-color: #ffffff;
    padding: 3px 10px;
    font-size: 0.74rem;
    border-radius: 5px;
    transition: all 0.15s ease;
}

.btn-outline-mandarin:hover {
    background-color: #f97316;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(249, 115, 22, 0.25);
}

/* 4. КНОПКА "ИСТОРИЯ" В ОБЩЕМ СТИЛЕ */
.btn-history {
    font-size: 0.74rem;
    padding: 3px 10px;
    border-radius: 5px;
    background-color: #ffffff;
    border-color: #cbd5e1;
    color: #475569;
}

.btn-history:hover {
    background-color: #f1f5f9;
    color: #1e293b;
}

/* 5. КНОПКА ДОБАВЛЕНИЯ ВНИЗУ ТАБЛИЦЫ */
.btn-mandarin-add-sm {
    background-color: #299bd1;
    color: #ffffff;
    font-size: 0.74rem;
    padding: 3px 10px;
    border-radius: 4px;
    border: none;
}

.btn-mandarin-add-sm:hover {
    background-color: #0284c7;
}

/* 6. ТАБЛИЦА КАЛЬКУЛЯЦИИ */
.mandarin-calc-table {
    table-layout: fixed;
    width: 100%;
    border-collapse: collapse;
    font-size: 0.73rem;
}

.mandarin-calc-table thead .header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-weight: 700;
    padding: 4px 3px !important;
    border: 1px solid #1f8abf;
    white-space: nowrap;
}

.mandarin-calc-table thead .header-totals th {
    background-color: #38bdf8 !important;
    color: #ffffff !important;
    padding: 4px 3px !important;
    border: 1px solid #0284c7;
}

.mandarin-calc-table td {
    padding: 2px 4px !important;
    height: 25px;
    border: 1px solid #e2e8f0;
}

/* ПЗН колонки */
.col-pzn-header {
    background-color: #0284c7 !important;
}

.col-pzn-cell {
    background-color: #f0f9ff !important;
    color: #0369a1 !important;
}

.col-pzn-diff {
    background-color: #fef08a !important;
    color: #854d0e !important;
    font-weight: 900 !important;
}

.table-cell-input {
    width: 100%;
    border: 1px solid transparent;
    background: transparent;
    font-size: 0.73rem;
    padding: 1px 3px;
    border-radius: 2px;
}

.table-cell-input:focus {
    background-color: #ffffff;
    border-color: #0284c7;
    outline: none;
    box-shadow: inset 0 0 0 1px #0284c7;
}

/* Кнопки переноса */
.btn-transfer-pill {
    border: none;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 0.67rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    transition: all 0.15s ease;
}

.btn-transfer-pill.to-paint {
    background-color: #e0f2fe;
    color: #0369a1;
}

.btn-transfer-pill.to-paint:hover {
    background-color: #0284c7;
    color: #ffffff;
}

.btn-transfer-pill.to-repair {
    background-color: #ffedd5;
    color: #c2410c;
}

.btn-transfer-pill.to-repair:hover {
    background-color: #ea580c;
    color: #ffffff;
}

.hover-danger:hover {
    color: #dc2626 !important;
}

/* Модалка */
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1090;
    backdrop-filter: blur(2px);
}

.modal-dialog-custom {
    background: #ffffff;
    border-radius: 8px;
    width: 90%;
    overflow: hidden;
}
</style>