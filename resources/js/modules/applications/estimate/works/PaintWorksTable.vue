<template>
    <div class="paint-works-module mb-4">
        <!-- ШАПКА РАЗДЕЛА С АККОРДЕОНОМ -->
        <div class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="isCollapsed = !isCollapsed">
                <div class="section-icon-box bg-dark text-white p-1 rounded">
                    <i class="bi bi-paint-bucket"></i>
                </div>
                <h6 class="section-title m-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Окрасочные работы по калькуляции</span>
                    <span class="text-muted fw-normal fs-7">({{ works.length }})</span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div
                    class="summary-pill d-flex align-items-center gap-2 px-2.5 py-1 bg-dark text-white rounded font-monospace">
                    <span class="text-white-50 small">Сумма СК:</span>
                    <span class="fw-bold">{{ formatMoney(totals.paint_ic) }} ₽</span>
                </div>
                <div
                    class="summary-pill d-flex align-items-center gap-2 px-2.5 py-1 bg-light text-dark border rounded font-monospace">
                    <span class="text-muted small">ПЗН:</span>
                    <span class="fw-bold">{{ formatMoney(totals.paint_pzn) }} ₽</span>
                </div>
            </div>
        </div>

        <!-- ТАБЛИЦА -->
        <div v-show="!isCollapsed">
            <div class="table-responsive bg-white border rounded-1 shadow-xs">
                <table class="table table-bordered table-mandarin mb-0 align-middle">
                    <thead>
                        <tr class="header-blue text-center">
                            <th style="width: 38px;">#</th>
                            <th class="text-start ps-3">Деталь под окраску</th>
                            <th style="width: 90px;">Ставка н/ч</th>
                            <th style="width: 75px;">Н/Ч (СК)</th>
                            <th style="width: 75px;">Н/Ч (ПЗН)</th>
                            <th style="width: 95px;">ЛКМ (Материалы)</th>
                            <th style="width: 110px;">Сумма СК</th>
                            <th style="width: 110px;">Сумма ПЗН</th>
                            <th style="width: 36px;" title="Вернуть в слесарные работы">В слесарку</th>
                            <th style="width: 36px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(work, idx) in works" :key="work.id" class="work-row"
                            :class="getAuthorBgClass(work.author_type)">
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- Деталь под окраску -->
                            <td class="editable-cell text-start ps-3 fw-semibold" contenteditable="true"
                                @blur="saveField(work, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.name }}
                            </td>

                            <!-- Ставка н/ч -->
                            <td class="editable-cell text-end pe-2 font-monospace" contenteditable="true"
                                @blur="saveField(work, 'price_per_hour', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(work.price_per_hour) }}
                            </td>

                            <!-- Часы СК -->
                            <td class="editable-cell text-center font-monospace" contenteditable="true"
                                @blur="saveField(work, 'norm_hours', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.norm_hours }}
                            </td>

                            <!-- Часы ПЗН -->
                            <td class="editable-cell text-center font-monospace" contenteditable="true"
                                @blur="saveField(work, 'pzn_hours', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.pzn_hours }}
                            </td>

                            <!-- ЛКМ Материалы -->
                            <td class="editable-cell text-end pe-2 font-monospace" contenteditable="true"
                                @blur="saveField(work, 'sum_materials', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(work.sum_materials) }}
                            </td>

                            <!-- Сумма СК (темная ячейка) -->
                            <td class="cell-dark text-end pe-2 font-monospace fw-bold text-white">
                                {{ formatMoney(work.sum_ic) }}
                            </td>

                            <!-- Сумма ПЗН -->
                            <td class="text-end pe-2 font-monospace fw-bold bg-light">
                                {{ formatMoney(work.sum_pzn) }}
                            </td>

                            <!-- Кнопка возврата в слесарные -->
                            <td class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-primary p-0"
                                    title="Вернуть в слесарные работы" @click="convertToRepair(work)">
                                    <i class="bi bi-wrench fs-6"></i>
                                </button>
                            </td>

                            <!-- Удаление -->
                            <td class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-secondary hover-danger p-0"
                                    @click="deleteWork(work)" title="Удалить строку">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="works.length === 0">
                            <td colspan="10" class="text-center py-3 text-muted">
                                Окрасочные работы по калькуляции не внесены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                    @click="addManualRow">
                    <i class="bi bi-plus-circle me-1.5"></i> Добавить окрасочную позицию
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: { type: Number, required: true },
});

const emit = defineEmits(['updated']);

const isCollapsed = ref(false);
const works = ref([]);
const totals = ref({ paint_ic: 0, paint_pzn: 0 });

const fetchWorks = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/calc-works`);
        works.value = res.data.paint || [];
        totals.value.paint_ic = res.data.totals.paint_ic;
        totals.value.paint_pzn = res.data.totals.paint_pzn;
    } catch (e) {
        console.error('Ошибка загрузки окрасочных работ:', e);
    }
};

const addManualRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/calc-works`, {
            work_category: 'paint',
            name: 'Окраска элемента',
            norm_hours: 1,
            pzn_hours: 1,
            price_per_hour: 1500,
            sum_materials: 3500,
        });
        works.value.push(res.data.data);
        emit('updated');
    } catch (e) {
        console.error('Ошибка добавления строки:', e);
    }
};

const saveField = async (work, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (['norm_hours', 'pzn_hours', 'price_per_hour', 'sum_materials'].includes(field)) {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === work[field]) return;

    work[field] = cleanVal;
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/calc-works/${work.id}/field`, {
            field,
            value: cleanVal,
        });
        Object.assign(work, res.data.data);
        fetchWorks();
        emit('updated');
    } catch (e) {
        console.error('Ошибка сохранения поля:', e);
    }
};

const convertToRepair = async (work) => {
    if (!confirm(`Вернуть работу "${work.name}" в слесарно-кузовные?`)) return;
    try {
        await axios.post(`/api/applications/${props.applicationId}/calc-works/${work.id}/toggle-category`);
        fetchWorks();
        emit('updated');
    } catch (e) {
        console.error('Ошибка переноса в слесарку:', e);
    }
};

const deleteWork = async (work) => {
    if (!confirm(`Удалить операцию "${work.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/calc-works/${work.id}`);
        works.value = works.value.filter(w => w.id !== work.id);
        fetchWorks();
        emit('updated');
    } catch (e) {
        console.error('Ошибка удаления:', e);
    }
};

const getAuthorBgClass = (type) => {
    switch (Number(type)) {
        case 1: return 'row-mrk';
        case 2: return 'row-mentor';
        case 4: return 'row-service';
        case 5: return 'row-other';
        default: return 'row-auto';
    }
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(fetchWorks);
defineExpose({ fetchWorks });
</script>

<style scoped>
.accordion-toggle-btn {
    cursor: pointer;
    user-select: none;
}

.accordion-arrow {
    font-size: 0.82rem;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.accordion-arrow-rotated {
    transform: rotate(-90deg);
}

.table-mandarin {
    table-layout: fixed !important;
    width: 100% !important;
    font-size: 0.72rem !important;
    border-collapse: collapse;
}

.header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-weight: 700;
    padding: 3px 2px !important;
    border: 1px solid #1f8abf;
}

.table-mandarin td {
    padding: 2px 4px !important;
    height: 24px;
    border: 1px solid #e2e8f0;
}

.cell-dark {
    background-color: #1e293b !important;
}

.row-auto td {
    background-color: #ffffff;
}

.row-mrk td {
    background-color: #eff6ff !important;
}

.row-mentor td {
    background-color: #f5f3ff !important;
}

.row-service td {
    background-color: #fefce8 !important;
}

.row-other td {
    background-color: #fdf4f0 !important;
}

.editable-cell {
    outline: none;
    cursor: text;
}

.editable-cell:focus {
    background-color: #e0f2fe !important;
    box-shadow: inset 0 0 0 1px #0284c7;
}

.btn-mandarin-add-sm {
    background-color: #299bd1;
    color: #ffffff;
    font-size: 0.72rem;
    padding: 2px 8px;
    border-radius: 3px;
    border: none;
}

.btn-mandarin-add-sm:hover {
    background-color: #0284c7;
}

.btn-xs {
    font-size: 0.65rem;
    padding: 1px 4px;
}

.hover-danger:hover {
    color: #dc2626 !important;
}
</style>