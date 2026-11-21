<template>
    <div class="extra-expenses-module mb-4">
        <!-- ШАПКА БЛОКА: АККОРДЕОН + ИТОГОВЫЙ БАДЖ + КНОПКА ИСТОРИИ -->
        <div class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="isCollapsed = !isCollapsed" role="button" title="Свернуть / развернуть">
                <div class="section-icon-box bg-secondary text-white p-1 rounded">
                    <i class="bi bi-wallet2"></i>
                </div>
                <h6 class="section-title m-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Дополнительные затраты</span>
                    <span class="text-muted fw-normal fs-7">({{ items.length }})</span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- БАДЖ ИТОГО -->
                <div
                    class="summary-pill d-flex align-items-center gap-1.5 px-2.5 py-1 bg-dark text-white rounded font-monospace">
                    <span class="text-white-50 small">Итого:</span>
                    <span class="fw-bold text-warning">{{ formatMoney(totalSum) }} ₽</span>
                </div>

                <!-- КНОПКА ИСТОРИЯ -->
                <button type="button"
                    class="btn btn-sm btn-outline-secondary btn-history-sm fw-semibold d-flex align-items-center"
                    @click="openHistoryModal">
                    <i class="bi bi-clock-history me-1 text-primary"></i> История
                </button>
            </div>
        </div>

        <!-- ТЕЛО ТАБЛИЦЫ -->
        <div v-show="!isCollapsed" class="accordion-body-wrapper">
            <div class="table-desktop-fit-wrapper bg-white border rounded-1 shadow-xs">
                <table class="table table-bordered table-mandarin mb-0 align-middle">
                    <thead>
                        <tr class="header-blue text-center">
                            <th style="width: 38px;">#</th>
                            <th style="width: 140px;">Категория</th>
                            <th class="text-start ps-3">Статья расходов</th>
                            <th style="width: 130px;" class="text-end pe-3">Сумма (₽)</th>
                            <th style="min-width: 160px;" class="text-start ps-3">Комментарий</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id" class="expense-row"
                            :class="getAuthorBgClass(item.author_type)">
                            <!-- 1. Номер -->
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- 2. Категория (select) -->
                            <td class="p-1">
                                <select class="form-select form-select-xs category-select font-monospace"
                                    :value="item.expense_type"
                                    @change="saveField(item, 'expense_type', $event.target.value)">
                                    <option value="эвакуатор">Доставка</option>
                                    <option value="эвакуатор">Эвакуатор</option>
                                    <option value="мойка">Мойка</option>
                                    <option value="такси">Такси</option>
                                    <option value="сертификат">Сертификат</option>
                                    <option value="стоянка">Ремонт дисков</option>
                                    <option value="экспертиза">Оклейка</option>
                                    <option value="экспертиза">Деффектовка</option>
                                    <option value="прочее">Прочее</option>
                                </select>
                            </td>

                            <!-- 3. Название статьи (contenteditable) -->
                            <td class="editable-cell text-start ps-3 fw-semibold text-truncate" contenteditable="true"
                                @blur="saveField(item, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ item.name }}
                            </td>

                            <!-- 4. Сумма (contenteditable) -->
                            <td class="editable-cell text-end pe-3 font-monospace fw-bold text-danger"
                                contenteditable="true" @blur="saveField(item, 'sum', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(item.sum) }}
                            </td>

                            <!-- 5. Комментарий (contenteditable) -->
                            <td class="editable-cell text-start ps-3 text-muted small text-truncate"
                                contenteditable="true" @blur="saveField(item, 'comment', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()" :title="item.comment">
                                {{ item.comment || '' }}
                            </td>

                            <!-- 6. Удаление -->
                            <td class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-secondary hover-danger p-0"
                                    @click="deleteExpense(item)" title="Удалить строку">
                                    <i class="bi bi-trash3 fs-6"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="items.length === 0">
                            <td colspan="6" class="text-center py-4 text-muted">
                                Дополнительные расходы не зафиксированы. Нажмите «Добавить расход».
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- КНОПКА ДОБАВЛЕНИЯ -->
            <div class="mt-2 d-flex align-items-center justify-content-between">
                <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center shadow-xs"
                    @click="addNewRow">
                    <i class="bi bi-plus-circle me-1.5"></i> Добавить расход
                </button>

                <div class="text-muted small font-monospace">
                    * Расходы учитываются в калькуляции затрат дела для расчета чистой прибыли
                </div>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ АУДИТА -->
        <Teleport to="body">
            <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 700px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-primary"></i>
                            История изменений доп. затрат
                        </h6>
                        <button type="button" class="btn-close" @click="showHistoryModal = false"></button>
                    </div>
                    <div class="modal-body p-3" style="max-height: 440px; overflow-y: auto;">
                        <div v-if="historyList.length === 0" class="text-center py-4 text-muted">
                            История изменений пока пуста.
                        </div>
                        <table v-else class="table table-sm table-striped history-table mb-0 font-monospace">
                            <thead>
                                <tr class="small text-muted border-bottom">
                                    <th>Дата</th>
                                    <th>Сотрудник</th>
                                    <th>Действие</th>
                                    <th>Поле</th>
                                    <th>Было</th>
                                    <th>Стало</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="h in historyList" :key="h.id" class="small">
                                    <td class="text-nowrap">{{ formatDateTime(h.created_at) }}</td>
                                    <td class="fw-semibold">{{ h.user ? h.user.name : 'Система' }}</td>
                                    <td>
                                        <span class="badge"
                                            :class="h.action_type === 'created' ? 'bg-success' : (h.action_type === 'deleted' ? 'bg-danger' : 'bg-primary')">
                                            {{ h.action_type }}
                                        </span>
                                    </td>
                                    <td>{{ h.field_name || '—' }}</td>
                                    <td class="text-danger text-truncate" style="max-width: 120px;">{{ h.old_value ||
                                        '—' }}</td>
                                    <td class="text-success fw-bold text-truncate" style="max-width: 120px;">{{
                                        h.new_value || '—' }}</td>
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
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits(['updated']);

const isCollapsed = ref(false);
const items = ref([]);
const totalSum = ref(0);
const showHistoryModal = ref(false);
const historyList = ref([]);

const fetchExpenses = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/extra-expenses`);
        items.value = res.data.data || [];
        totalSum.value = res.data.total_sum || 0;
    } catch (err) {
        console.error('Ошибка загрузки доп. затрат:', err);
    }
};

const addNewRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/extra-expenses`, {
            name: 'Новый расход',
            expense_type: 'прочее',
            sum: 0,
            comment: '',
        });
        items.value.push(res.data.data);
        recalcTotal();
        emit('updated');
    } catch (err) {
        console.error('Ошибка добавления строки:', err);
    }
};

const saveField = async (item, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (field === 'sum') {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === item[field]) return;
    item[field] = cleanVal;

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/extra-expenses/${item.id}/field`, {
            field,
            value: cleanVal,
        });
        if (res.data?.data) {
            Object.assign(item, res.data.data);
        }
        recalcTotal();
        emit('updated');
    } catch (err) {
        console.error('Ошибка сохранения ячейки:', err);
    }
};

const deleteExpense = async (item) => {
    if (!confirm(`Удалить статью расходов "${item.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/extra-expenses/${item.id}`);
        items.value = items.value.filter(i => i.id !== item.id);
        recalcTotal();
        emit('updated');
    } catch (err) {
        console.error('Ошибка удаления расхода:', err);
    }
};

const openHistoryModal = async () => {
    showHistoryModal.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/extra-expenses/history`);
        historyList.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка загрузки истории:', err);
    }
};

const recalcTotal = () => {
    totalSum.value = items.value.reduce((acc, i) => acc + (parseFloat(i.sum) || 0), 0);
};

// Раскраска строк под автора (МРК — синий, Наставник — сиреневый, другой — коричневый)
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

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

onMounted(fetchExpenses);
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

/* ЦВЕТА СТРОК АВТОРОВ */
.row-auto td {
    background-color: #ffffff;
}

.row-mrk td {
    background-color: #eff6ff !important;
}

/* синий */
.row-mentor td {
    background-color: #f5f3ff !important;
}

/* сиреневый */
.row-service td {
    background-color: #fefce8 !important;
}

/* желтый */
.row-other td {
    background-color: #fdf4f0 !important;
}

/* коричневый */

.editable-cell {
    outline: none;
    cursor: text;
}

.editable-cell:focus {
    background-color: #e0f2fe !important;
    box-shadow: inset 0 0 0 1px #0284c7;
}

.category-select {
    font-size: 0.70rem !important;
    height: 22px;
    padding: 0 4px;
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

.btn-history-sm {
    font-size: 0.72rem;
    padding: 2px 8px;
}

.btn-xs {
    font-size: 0.65rem;
    padding: 1px 4px;
}

.hover-danger:hover {
    color: #dc2626 !important;
}

.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1090;
}

.modal-dialog-custom {
    background: #ffffff;
    border-radius: 8px;
    width: 90%;
}
</style>