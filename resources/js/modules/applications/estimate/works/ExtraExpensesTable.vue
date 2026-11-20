<template>
    <div class="extra-expenses-module mb-4">
        <!-- ШАПКА РАЗДЕЛА С АККОРДЕОНОМ -->
        <div class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="isCollapsed = !isCollapsed">
                <div class="section-icon-box bg-secondary text-white p-1 rounded">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h6 class="section-title m-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Дополнительные затраты</span>
                    <span class="text-muted fw-normal fs-7">({{ expenses.length }})</span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div
                    class="summary-pill d-flex align-items-center gap-2 px-2.5 py-1 bg-dark text-white rounded font-monospace">
                    <span class="text-white-50 small">Итого доп. расходы:</span>
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
                            <th style="width: 160px;">Тип расхода</th>
                            <th class="text-start ps-3">Наименование / Описание</th>
                            <th style="width: 120px;">Сумма (₽)</th>
                            <th>Комментарий</th>
                            <th style="width: 44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(exp, idx) in expenses" :key="exp.id" class="work-row"
                            :class="getAuthorBgClass(exp.author_type)">
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- Селект типа расхода -->
                            <td class="p-1">
                                <select class="form-select form-select-micro" :value="exp.expense_type"
                                    @change="saveField(exp, 'expense_type', $event.target.value)">
                                    <option value="эвакуатор">Эвакуатор</option>
                                    <option value="мойка">Мойка</option>
                                    <option value="такси">Такси клиенту</option>
                                    <option value="сертификат">Сертификат / Экспертиза</option>
                                    <option value="хранение">Хранение на стоянке</option>
                                    <option value="прочее">Прочее</option>
                                </select>
                            </td>

                            <!-- Описание -->
                            <td class="editable-cell text-start ps-3 fw-semibold" contenteditable="true"
                                @blur="saveField(exp, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ exp.name }}
                            </td>

                            <!-- Сумма -->
                            <td class="editable-cell text-end pe-3 font-monospace fw-bold" contenteditable="true"
                                @blur="saveField(exp, 'sum', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(exp.sum) }}
                            </td>

                            <!-- Комментарий -->
                            <td class="editable-cell text-start ps-2 text-muted" contenteditable="true"
                                @blur="saveField(exp, 'comment', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ exp.comment }}
                            </td>

                            <!-- Удаление -->
                            <td class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-secondary hover-danger p-0"
                                    @click="deleteExpense(exp)" title="Удалить строку">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="expenses.length === 0">
                            <td colspan="6" class="text-center py-3 text-muted">
                                Дополнительные затраты не внесены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                    @click="addManualRow">
                    <i class="bi bi-plus-circle me-1.5"></i> Добавить расход
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
const expenses = ref([]);
const totalSum = ref(0);

const fetchExpenses = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/extra-expenses`);
        expenses.value = res.data.data || [];
        totalSum.value = res.data.total_sum || 0;
    } catch (e) {
        console.error('Ошибка загрузки доп. затрат:', e);
    }
};

const addManualRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/extra-expenses`, {
            name: 'Услуги эвакуатора',
            expense_type: 'эвакуатор',
            sum: 0,
            comment: '',
        });
        expenses.value.push(res.data.data);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка добавления расхода:', e);
    }
};

const saveField = async (exp, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (field === 'sum') {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === exp[field]) return;

    exp[field] = cleanVal;
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/extra-expenses/${exp.id}/field`, {
            field,
            value: cleanVal,
        });
        Object.assign(exp, res.data.data);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка сохранения поля:', e);
    }
};

const deleteExpense = async (exp) => {
    if (!confirm(`Удалить расход "${exp.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/extra-expenses/${exp.id}`);
        expenses.value = expenses.value.filter(e => e.id !== exp.id);
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка удаления:', e);
    }
};

const recalcTotal = () => {
    totalSum.value = expenses.value.reduce((acc, e) => acc + (parseFloat(e.sum) || 0), 0);
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

onMounted(fetchExpenses);
defineExpose({ fetchExpenses });
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

.form-select-micro {
    height: 21px !important;
    font-size: 0.68rem !important;
    padding: 0 3px !important;
    border-radius: 2px;
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