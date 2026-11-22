<template>
    <div class="fact-works-module mb-4">
        <!-- ЗАГОЛОВОК АККОРДЕОНА (В ЕДИНОМ СТИЛЕ С ПЛАНОМ И ЗАПЧАСТЯМИ) -->
        <div
            class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2" @click="isCollapsed = !isCollapsed">
                <div class="section-icon-box bg-success text-white p-1 rounded">
                    <i class="bi bi-tools"></i>
                </div>
                <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-1.5 section-title-text">
                    <span>Фактические работы (Акт СТО)</span>
                    <span class=" text-muted fw-normal fs-7">({{ works.length }})</span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <!-- БАДЖ ИТОГО + КНОПКА ИМПОРТА ИЗ ПЛАНА + ИСТОРИЯ -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="badge-black-pill font-monospace" title="Итоговая сумма плановых работ">
                    <span class="pill-label">Итого:</span>
                    <span class="pill-val">{{ formatMoney(totalSum) }} ₽</span>
                </div>


                <!-- КНОПКА "ИМПОРТ ИЗ ПЛАНА" -->
                <button type="button"
                    class="btn btn-sm btn-outline-mandarin fw-semibold d-flex align-items-center shadow-xs"
                    :disabled="isImporting" @click="importFromPlan"
                    title="Скопировать согласованные работы из плана в факт">
                    <span v-if="isImporting" class="spinner-border spinner-border-xs me-1.5"></span>
                    <i v-else class="bi bi-box-arrow-in-down me-1.5"></i>
                    Импорт из плана
                </button>

                <!-- КНОПКА "ИСТОРИЯ ИЗМЕНЕНИЙ" -->
                <button type="button"
                    class="btn btn-sm btn-outline-secondary btn-history fw-semibold d-flex align-items-center shadow-xs"
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
                            <th class="text-start ps-3">Наименование выполненной работы</th>
                            <th style="width: 150px;">Цена сервиса (₽)</th>
                            <th style="width: 44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(work, idx) in works" :key="work.id" class="work-row"
                            :class="getAuthorBgClass(work)">
                            <!-- 1. # Номер -->
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- 2. Наименование (contenteditable) -->
                            <td class="editable-cell text-start ps-3 fw-semibold" contenteditable="true"
                                @blur="saveField(work, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.name }}
                            </td>

                            <!-- 3. Цена (contenteditable) -->
                            <td class="editable-cell text-end pe-3 font-monospace fw-bold" contenteditable="true"
                                @blur="saveField(work, 'price', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(work.price) }}
                            </td>

                            <!-- 4. Удаление -->
                            <td class="text-center p-0">
                                <button type="button" class="btn btn-link btn-xs text-secondary hover-danger p-0"
                                    @click="deleteWork(work)" title="Удалить строку">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="works.length === 0">
                            <td colspan="4" class="text-center py-4 text-muted">
                                Фактические работы еще не внесены. Нажмите «Импорт из плана» или добавьте строки вручную
                                / из справочника.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- КНОПКИ ДЕЙСТВИЙ ВНИЗУ -->
            <div class="mt-2.5 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <!-- Добавить вручную -->
                    <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                        @click="addManualRow">
                        <i class="bi bi-plus-circle me-1.5"></i> Добавить вручную
                    </button>

                    <!-- Выбрать из справочника СТО -->
                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold d-flex align-items-center"
                        @click="openDirectoryModal">
                        <i class="bi bi-book-half me-1.5"></i> Подтянуть из справочника
                    </button>
                </div>

                <div class="text-muted small font-monospace">
                    * Цвета строк: Голубой — МРК, Желтый — Сервис, Сиреневый — Наставник
                </div>
            </div>
        </div>

        <!-- МОДАЛКА ВЫБОРА ИЗ СПРАВОЧНИКА ТИПОВЫХ РАБОТ ДЛЯ ДАННОГО СТО -->
        <Teleport to="body">
            <div v-if="showDirectoryModal" class="modal-backdrop-custom" @click="showDirectoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 650px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-book-half me-2 text-primary"></i>
                            <span v-if="effectiveServiceName">Прайс СТО: {{ effectiveServiceName }}</span>
                            <span v-else>Общий справочник типовых работ (базовые цены)</span>
                        </h6>
                        <button type="button" class="btn-close" @click="showDirectoryModal = false"></button>
                    </div>
                    <div class="modal-body p-3" style="max-height: 420px; overflow-y: auto;">
                        <input v-model="dirSearch" type="text" class="form-control form-control-sm mb-3"
                            placeholder="Поиск типовой работы по названию..." />
                        <table class="table table-sm table-hover align-middle mb-0 font-monospace">
                            <thead>
                                <tr class="small text-muted border-bottom">
                                    <th>Наименование операции</th>
                                    <th style="width: 120px;" class="text-end">Цена</th>
                                    <th style="width: 90px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in filteredDirectory" :key="item.id">
                                    <td class="fw-semibold">{{ item.name }}</td>
                                    <td class="text-end fw-bold text-primary">
                                        {{ formatMoney(item.final_price || item.service_price || item.base_price || 0)
                                        }} ₽
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5"
                                            @click="selectFromDirectory(item)">
                                            Выбрать
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredDirectory.length === 0">
                                    <td colspan="3" class="text-center text-muted py-3">Работы не найдены</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ ИЗМЕНЕНИЙ (1:1 С ПЛАНОМ И ЗАПЧАСТЯМИ) -->
        <Teleport to="body">
            <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 820px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-primary fs-5"></i>
                            История изменений фактических работ дела #{{ applicationId }}
                        </h6>
                        <button type="button" class="btn-close" @click="showHistoryModal = false"></button>
                    </div>
                    <div class="modal-body p-3" style="max-height: 480px; overflow-y: auto;">
                        <div v-if="historyLoading" class="text-center py-4 text-muted">
                            <span class="spinner-border spinner-border-sm text-primary me-2"></span>
                            Загрузка истории...
                        </div>
                        <div v-else-if="historyList.length === 0" class="text-center py-4 text-muted">
                            История изменений пока пуста.
                        </div>
                        <table v-else class="table table-sm table-striped history-table mb-0 align-middle">
                            <thead>
                                <tr class="small text-muted border-bottom">
                                    <th style="width: 130px;">Дата / Время</th>
                                    <th style="width: 140px;">Сотрудник</th>
                                    <th style="width: 140px;">Поле / Действие</th>
                                    <th>Было</th>
                                    <th>Стало</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="h in historyList" :key="h.id" class="small">
                                    <td class="font-monospace text-nowrap">{{ formatDateTime(h.created_at) }}</td>
                                    <td class="fw-semibold">
                                        {{ h.user ? (h.user.last_name ? (h.user.last_name + ' ' + (h.user.name || '')) :
                                            h.user.name) : 'Система' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ translateHistoryField(h.field_name || h.action_type) }}
                                        </span>
                                    </td>
                                    <td class="text-danger text-truncate font-monospace" style="max-width: 180px;"
                                        :title="h.old_value">
                                        {{ h.old_value || '—' }}
                                    </td>
                                    <td class="text-success text-truncate font-monospace fw-semibold"
                                        style="max-width: 180px;" :title="h.new_value">
                                        {{ h.new_value || '—' }}
                                    </td>
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
    serviceId: { type: [Number, String], default: null },
    application: { type: Object, default: () => ({}) },
    userRole: { type: String, default: 'mrk' },
});

const emit = defineEmits(['updated']);

const isCollapsed = ref(false);
const works = ref([]);
const totalSum = ref(0);
const isImporting = ref(false);
const serverServiceId = ref(null);
const serverServiceName = ref('');

// История изменений
const showHistoryModal = ref(false);
const historyLoading = ref(false);
const historyList = ref([]);

// Справочник
const showDirectoryModal = ref(false);
const directoryItems = ref([]);
const dirSearch = ref('');

const currentEffectiveServiceId = computed(() => {
    return Number(
        props.serviceId ||
        props.application?.id_service ||
        props.application?.service_id ||
        serverServiceId.value ||
        0
    );
});

const effectiveServiceName = computed(() => {
    return (
        serverServiceName.value ||
        props.application?.service?.name ||
        props.application?.service_name ||
        ''
    );
});

const filteredDirectory = computed(() => {
    if (!dirSearch.value.trim()) return directoryItems.value;
    const q = dirSearch.value.toLowerCase();
    return directoryItems.value.filter(i => i.name.toLowerCase().includes(q));
});

// Загрузка фактических работ
const fetchFactWorks = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/fact-works`);
        works.value = res.data.data || [];
        totalSum.value = res.data.total_sum || 0;
        serverServiceId.value = res.data.service_id;
        serverServiceName.value = res.data.service_name || '';
    } catch (err) {
        console.error('Ошибка загрузки фактических работ:', err);
    }
};

// Импорт из согласованного плана
const importFromPlan = async () => {
    if (!confirm('Скопировать все плановые работы дела в фактические?')) return;

    isImporting.value = true;
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/fact-works/import-from-plan`);
        if (res.data?.data) {
            works.value = res.data.data;
            totalSum.value = res.data.total_sum || 0;
            emit('updated');
            alert(res.data.message || 'Импорт успешно завершен');
        }
    } catch (err) {
        alert(err.response?.data?.message || 'Ошибка импорта из плана');
    } finally {
        isImporting.value = false;
    }
};

// Добавление вручную
const addManualRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/fact-works`, {
            name: 'Новая фактическая работа',
            price: 0,
        });
        if (res.data?.data) {
            works.value.push(res.data.data);
            recalcTotal();
            emit('updated');
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Ошибка создания строки');
    }
};

// Открытие модалки справочника
const openDirectoryModal = async () => {
    showDirectoryModal.value = true;
    try {
        const sid = currentEffectiveServiceId.value || 0;
        const res = await axios.get('/api/standard-works/application-options', {
            params: { service_id: sid }
        });
        directoryItems.value = res.data.data || [];
    } catch (e) {
        console.error('Ошибка справочника:', e);
        directoryItems.value = [];
    }
};

// Выбор из справочника
const selectFromDirectory = async (item) => {
    try {
        const price = item.final_price ?? item.service_price ?? item.base_price ?? 0;
        const res = await axios.post(`/api/applications/${props.applicationId}/fact-works`, {
            standard_work_id: item.id,
            name: item.name,
            price: price,
        });
        if (res.data?.data) {
            works.value.push(res.data.data);
            showDirectoryModal.value = false;
            recalcTotal();
            emit('updated');
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Ошибка добавления из справочника');
    }
};

// Сохранение ячеек инлайн
const saveField = async (work, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (field === 'price') {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === work[field]) return;
    work[field] = cleanVal;

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/fact-works/${work.id}/field`, {
            field,
            value: cleanVal,
        });
        if (res.data?.data) {
            Object.assign(work, res.data.data);
        }
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка сохранения:', e);
    }
};

// Удаление строки
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
    totalSum.value = works.value.reduce((acc, w) => acc + (parseFloat(w.price) || 0), 0);
};

// История изменений
const openHistoryModal = async () => {
    showHistoryModal.value = true;
    historyLoading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/fact-works/history`);
        historyList.value = res.data.data || [];
    } catch (e) {
        console.error('Ошибка истории:', e);
    } finally {
        historyLoading.value = false;
    }
};

// Раскраска строк
const getAuthorBgClass = (work) => {
    if (!work) return 'row-auto';
    const type = Number(work.author_type ?? 0);
    const role = String(work.author_role ?? '').toLowerCase();

    if (type === 1 || role === 'mrk') return 'row-mrk';
    if (type === 2 || role === 'mentor') return 'row-mentor';
    if (type === 4 || role === 'service') return 'row-service';
    if (type === 5 || role === 'other') return 'row-other';

    return 'row-auto';
};

const translateHistoryField = (field) => {
    const map = {
        name: 'Наименование',
        price: 'Цена сервиса',
        позиция: 'Позиция',
        created: 'Добавление',
        imported: 'Импорт из плана',
        deleted: 'Удаление',
        updated: 'Изменение',
    };
    return map[field] || field;
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(fetchFactWorks);
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

.editable-cell {
    outline: none;
    cursor: text;
    background-color: transparent !important;
}

.editable-cell:focus {
    background-color: #ffffff !important;
    box-shadow: inset 0 0 0 2px #0284c7;
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

/* ЦВЕТА СТРОК АВТОРОВ */
:deep(.table-mandarin tbody tr.row-auto td),
.table-mandarin tbody tr.row-auto td {
    background-color: #ffffff !important;
    color: #1e293b;
}

:deep(.table-mandarin tbody tr.row-mrk td),
.table-mandarin tbody tr.row-mrk td {
    background-color: #e0f2fe !important;
    color: #0c4a6e !important;
}

:deep(.table-mandarin tbody tr.row-mrk:hover td),
.table-mandarin tbody tr.row-mrk:hover td {
    background-color: #bae6fd !important;
}

:deep(.table-mandarin tbody tr.row-mentor td),
.table-mandarin tbody tr.row-mentor td {
    background-color: #f3e8ff !important;
    color: #581c87 !important;
}

:deep(.table-mandarin tbody tr.row-mentor:hover td),
.table-mandarin tbody tr.row-mentor:hover td {
    background-color: #e9d5ff !important;
}

:deep(.table-mandarin tbody tr.row-service td),
.table-mandarin tbody tr.row-service td {
    background-color: #fef9c3 !important;
    color: #713f12 !important;
}

:deep(.table-mandarin tbody tr.row-service:hover td),
.table-mandarin tbody tr.row-service:hover td {
    background-color: #fef08a !important;
}

:deep(.table-mandarin tbody tr.row-other td),
.table-mandarin tbody tr.row-other td {
    background-color: #fed7aa !important;
    color: #7c2d12 !important;
}

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
</style>