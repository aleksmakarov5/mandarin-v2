<template>
    <div class="fact-works-module mb-4">
        <!-- ШАПКА РАЗДЕЛА С АККОРДЕОНОМ -->
        <div class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="isCollapsed = !isCollapsed">
                <div class="section-icon-box bg-success text-white p-1 rounded">
                    <i class="bi bi-file-earmark-check-fill"></i>
                </div>
                <h6 class="section-title m-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Фактические работы (Акт СТО)</span>
                    <span class="text-muted fw-normal fs-7">({{ works.length }})</span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div
                    class="summary-pill d-flex align-items-center gap-2 px-2.5 py-1 bg-dark text-white rounded font-monospace">
                    <span class="text-white-50 small">Факт затрат:</span>
                    <span class="fw-bold">{{ formatMoney(totalSum) }} ₽</span>
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
                            <th class="text-start ps-3">Наименование выполненной работы</th>
                            <th style="width: 130px;">Сумма СТО (₽)</th>
                            <th style="width: 120px;">Дата выполнения</th>
                            <th style="width: 44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(work, idx) in works" :key="work.id" class="work-row"
                            :class="getAuthorBgClass(work.author_type)">
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- Наименование -->
                            <td class="editable-cell text-start ps-3 fw-semibold" contenteditable="true"
                                @blur="saveField(work, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.name }}
                            </td>

                            <!-- Сумма сервиса -->
                            <td class="editable-cell text-end pe-3 font-monospace fw-bold" contenteditable="true"
                                @blur="saveField(work, 'price_service', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(work.price_service) }}
                            </td>

                            <!-- Дата выполнения -->
                            <td class="text-center p-0">
                                <input type="date" class="date-input-micro font-monospace text-center"
                                    :value="formatDateForInput(work.execution_date)"
                                    @change="saveField(work, 'execution_date', $event.target.value)" />
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
                            <td colspan="5" class="text-center py-3 text-muted">
                                Фактические работы СТО еще не внесены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                    @click="addManualRow">
                    <i class="bi bi-plus-circle me-1.5"></i> Добавить выполненную работу
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
const totalSum = ref(0);

const fetchFactWorks = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/fact-works`);
        works.value = res.data.data || [];
        totalSum.value = res.data.total_sum || 0;
    } catch (e) {
        console.error('Ошибка загрузки фактических работ:', e);
    }
};

const addManualRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/fact-works`, {
            name: 'Выполненная работа СТО',
            price_service: 0,
            execution_date: new Date().toISOString().substring(0, 10),
        });
        works.value.push(res.data.data);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка добавления строки:', e);
    }
};

const saveField = async (work, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (field === 'price_service') {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === work[field]) return;

    work[field] = cleanVal;
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/fact-works/${work.id}/field`, {
            field,
            value: cleanVal,
        });
        Object.assign(work, res.data.data);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка сохранения поля:', e);
    }
};

const deleteWork = async (work) => {
    if (!confirm(`Удалить работу "${work.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/fact-works/${work.id}`);
        works.value = works.value.filter(w => w.id !== work.id);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка удаления:', e);
    }
};

const recalcTotal = () => {
    totalSum.value = works.value.reduce((acc, w) => acc + (parseFloat(w.price_service) || 0), 0);
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

const formatDateForInput = (dateStr) => {
    if (!dateStr) return '';
    return String(dateStr).substring(0, 10);
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(fetchFactWorks);
defineExpose({ fetchFactWorks });
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

.date-input-micro {
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    font-size: 0.67rem;
    padding: 0 1px;
    height: 20px;
    width: 95%;
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