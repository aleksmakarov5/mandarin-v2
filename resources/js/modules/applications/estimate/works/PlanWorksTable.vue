<template>
    <div class="plan-works-module mb-4">
        <!-- ЗАГОЛОВОК АККОРДЕОНА -->
        <div
            class="section-header-row d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 cursor-pointer select-none accordion-toggle-btn"
                @click="isCollapsed = !isCollapsed">
                <div class="section-icon-box bg-primary text-white p-1 rounded">
                    <i class="bi bi-clipboard-check-fill"></i>
                </div>
                <h6 class="section-title m-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Плановые работы</span>
                    <span class="text-muted fw-normal fs-7">({{ works.length }})</span>
                    <span v-if="fullyApproved"
                        class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                        <i class="bi bi-check-all me-1"></i>Согласовано
                    </span>
                    <span v-else-if="serviceApproved"
                        class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace">
                        На согласовании у МРК
                    </span>
                    <i class="bi bi-chevron-down accordion-arrow ms-1 text-secondary"
                        :class="{ 'accordion-arrow-rotated': isCollapsed }"></i>
                </h6>
            </div>

            <!-- БАДЖ ИТОГО + КНОПКИ + ИСТОРИЯ ИЗМЕНЕНИЙ -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="badge-black-pill font-monospace" title="Итоговая сумма плановых работ">
                    <span class="pill-label">Итого:</span>
                    <span class="pill-val">{{ formatMoney(totalSum) }} ₽</span>
                </div>

                <!-- Кнопка Сервиса: "Согласовать" -->
                <button v-if="isServiceUser && !serviceApproved" type="button"
                    class="btn btn-sm btn-warning fw-bold d-flex align-items-center shadow-xs"
                    @click="submitServiceApproval">
                    <i class="bi bi-send-check me-1.5"></i> Согласовать с МРК
                </button>

                <!-- Кнопка МРК / Наставника: "Согласование завершено" -->
                <button v-if="!isServiceUser && canCompleteApproval && !fullyApproved" type="button"
                    class="btn btn-sm btn-success fw-bold d-flex align-items-center shadow-xs"
                    @click="completeApproval">
                    <i class="bi bi-check-circle-fill me-1.5"></i> Согласование завершено
                </button>
                <!-- Кнопка быстрой сверки/пересчета цен под текущий автосервис -->
                <button v-if="currentEffectiveServiceId" type="button"
                    class="btn btn-sm btn-outline-mandarin fw-semibold d-flex align-items-center shadow-xs"
                    title="Пересчитать цены позиций из каталога по прайс-листу выбранного СТО"
                    @click="syncWithService(currentEffectiveServiceId)">
                    <i class="bi bi-arrow-repeat me-1.5"></i> Обновить цены СТО
                </button>
                <!-- Кнопка "История изменений" 1:1 как в Запчастях -->
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
                            <th class="text-start ps-3">Наименование работы</th>
                            <th style="width: 140px;">Цена (₽)</th>
                            <th style="width: 140px;">Статус / Согласование</th>
                            <th style="width: 44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(work, idx) in works" :key="work.id" class="work-row"
                            :class="[getAuthorBgClass(work), { 'row-rejected': work.approval_status === 'rejected' }]">
                            <td class="text-center font-monospace cell-index fw-bold text-muted p-0">
                                {{ idx + 1 }}
                            </td>

                            <!-- Наименование (contenteditable) -->
                            <td class="editable-cell text-start ps-3 fw-semibold" contenteditable="true"
                                @blur="saveField(work, 'name', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ work.name }}
                            </td>

                            <!-- Цена (contenteditable) -->
                            <td class="editable-cell text-end pe-3 font-monospace fw-bold" contenteditable="true"
                                @blur="saveField(work, 'price', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(work.price) }}
                            </td>

                            <!-- Статус / Согласование -->
                            <td class="text-center p-1">
                                <div v-if="canReviewRow(work)"
                                    class="d-flex align-items-center justify-content-center gap-1">
                                    <button type="button" class="btn btn-xs"
                                        :class="work.approval_status === 'approved' ? 'btn-success text-white' : 'btn-outline-success'"
                                        title="Принять позицию" @click="setRowApproval(work, 'approved')">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs"
                                        :class="work.approval_status === 'rejected' ? 'btn-danger text-white' : 'btn-outline-danger'"
                                        title="Отклонить позицию" @click="setRowApproval(work, 'rejected')">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <span v-if="work.approval_status === 'pending'"
                                        class="badge bg-warning text-dark font-monospace ms-1"
                                        style="font-size: 0.65rem;">
                                        Новая
                                    </span>
                                </div>
                                <div v-else>
                                    <span class="badge font-monospace"
                                        :class="getApprovalBadgeClass(work.approval_status)">
                                        {{ translateApproval(work.approval_status) }}
                                    </span>
                                </div>
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
                            <td colspan="5" class="text-center py-4 text-muted">
                                Плановые работы не добавлены. Добавьте вручную или выберите из справочника.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- КНОПКИ ДЕЙСТВИЙ -->
            <div class="mt-2.5 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-mandarin-add-sm fw-bold d-flex align-items-center"
                        @click="addManualRow">
                        <i class="bi bi-plus-circle me-1.5"></i> Добавить вручную
                    </button>

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

        <!-- МОДАЛКА ВЫБОРА ИЗ СПРАВОЧНИКА -->
        <Teleport to="body">
            <div v-if="showDirectoryModal" class="modal-backdrop-custom" @click="showDirectoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 650px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-book-half me-2 text-primary"></i>
                            <span v-if="effectiveServiceName">
                                Прайс СТО: {{ effectiveServiceName }}
                            </span>
                            <span v-else-if="currentEffectiveServiceId">
                                Прайс СТО: #{{ currentEffectiveServiceId }}
                            </span>
                            <span v-else>Общий справочник типовых работ (базовые цены)</span>
                        </h6>
                        <button type="button" class="btn-close" @click="showDirectoryModal = false"></button>
                    </div>
                    <div class="modal-body p-3" style="max-height: 420px; overflow-y: auto;">
                        <div v-if="!currentEffectiveServiceId" class="alert alert-info py-1.5 px-2.5 small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Автосервис в деле не выбран. Отображаются все типовые работы с базовыми ценами.
                        </div>

                        <div v-else class="alert alert-success py-1.5 px-2.5 small mb-2">
                            <i class="bi bi-check-circle me-1"></i>
                            Применяются индивидуальные тарифы сервиса: <strong>{{ effectiveServiceName }}</strong>
                        </div>

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
                                    <td class="text-end fw-bold text-primary">{{ formatMoney(item.final_price ||
                                        item.service_price || item.base_price || 0) }} ₽</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5"
                                            @click="selectFromDirectory(item)">
                                            Выбрать
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredDirectory.length === 0">
                                    <td colspan="3" class="text-center text-muted py-3">
                                        {{ currentEffectiveServiceId ? 'Для выбранного СТО нет настроенных расценок' :
                                            'Работы не найдены' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ ИЗМЕНЕНИЙ (1:1 как в Запчастях) -->
        <Teleport to="body">
            <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 820px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-primary fs-5"></i>
                            История изменений плановых работ дела #{{ applicationId }}
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
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    applicationId: { type: [Number, String], required: true },
    serviceId: { type: [Number, String], default: null },
    application: { type: Object, default: () => ({}) },
    userRole: { type: String, default: 'mrk' },
    currentUser: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['updated']);

const isCollapsed = ref(false);
const works = ref([]);
const totalSum = ref(0);
const serviceApproved = ref(false);
const fullyApproved = ref(false);
const canCompleteApproval = ref(false);
const serverServiceId = ref(null);
const serverServiceName = ref('');
const serverIsServiceUser = ref(false);

// История изменений
const showHistoryModal = ref(false);
const historyLoading = ref(false);
const historyList = ref([]);

const isServiceUser = computed(() => {
    return serverIsServiceUser.value === true;
});

// Эффективный ID сервиса
const currentEffectiveServiceId = computed(() => {
    const sid = Number(
        props.serviceId ||
        props.application?.id_service ||
        props.application?.service_id ||
        serverServiceId.value ||
        0
    );
    return sid > 0 ? sid : null;
});

// Точное название автосервиса
const effectiveServiceName = computed(() => {
    return (
        serverServiceName.value ||
        props.application?.service?.name ||
        props.application?.service_name ||
        ''
    );
});

// Автопересчёт цен при смене СТО
watch(
    [
        () => props.serviceId,
        () => props.application?.id_service,
        () => props.application?.service_id
    ],
    async ([newPropSid, newAppIdServ, newAppServId], [oldPropSid, oldAppIdServ, oldAppServId]) => {
        const newSid = Number(newPropSid || newAppIdServ || newAppServId || 0);
        const oldSid = Number(oldPropSid || oldAppIdServ || oldAppServId || 0);

        if (newSid > 0 && newSid !== oldSid) {
            await syncWithService(newSid);
        }
    },
    { immediate: false }
);

const syncWithService = async (sid) => {
    const targetServiceId = sid || currentEffectiveServiceId.value;
    if (!targetServiceId) return;

    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/plan-works/sync-service-prices`, {
            service_id: targetServiceId,
        });
        if (res.data?.data) {
            works.value = res.data.data;
            totalSum.value = res.data.total_sum || 0;
            emit('updated');
        }
    } catch (err) {
        console.error('Ошибка обновления цен под сервис:', err);
    }
};

// Модалка справочника
const showDirectoryModal = ref(false);
const directoryItems = ref([]);
const dirSearch = ref('');

const filteredDirectory = computed(() => {
    if (!dirSearch.value.trim()) return directoryItems.value;
    const q = dirSearch.value.toLowerCase();
    return directoryItems.value.filter(i => i.name.toLowerCase().includes(q));
});

const fetchPlanWorks = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/plan-works`);
        works.value = res.data.data || [];
        totalSum.value = res.data.total_sum || 0;
        serviceApproved.value = Boolean(res.data.service_approved);
        fullyApproved.value = Boolean(res.data.fully_approved);
        canCompleteApproval.value = Boolean(res.data.can_complete_approval);
        serverServiceId.value = res.data.service_id;
        serverServiceName.value = res.data.service_name || '';

        if (typeof res.data.is_service_user !== 'undefined') {
            serverIsServiceUser.value = Boolean(res.data.is_service_user);
        }
    } catch (e) {
        console.error('Ошибка загрузки плановых работ:', e);
    }
};

const addManualRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/plan-works`, {
            name: 'Новая работа',
            price: 0,
        });
        if (res.data?.data) {
            works.value.push(res.data.data);
            recalcTotal();
            emit('updated');
        }
    } catch (e) {
        alert(e.response?.data?.message || 'Ошибка добавления строки вручную');
    }
};

const openDirectoryModal = async () => {
    showDirectoryModal.value = true;
    try {
        const sid = currentEffectiveServiceId.value || 0;
        const res = await axios.get('/api/standard-works/application-options', {
            params: { service_id: sid }
        });
        directoryItems.value = res.data.data || [];
    } catch (e) {
        console.error('Ошибка загрузки справочника:', e);
        directoryItems.value = [];
    }
};

const selectFromDirectory = async (item) => {
    try {
        const price = item.final_price ?? item.service_price ?? item.base_price ?? 0;
        const res = await axios.post(`/api/applications/${props.applicationId}/plan-works`, {
            standard_work_id: item.id, // <-- КЛЮЧЕВОЙ ПАРАМЕТР
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
        alert(e.response?.data?.message || 'Ошибка добавления работы из справочника');
    }
};

const saveField = async (work, field, rawValue) => {
    let cleanVal = String(rawValue || '').replace(/\u00a0/g, ' ').trim();
    if (field === 'price') {
        cleanVal = parseFloat(cleanVal.replace(/\s+/g, '').replace(',', '.')) || 0;
    }
    if (cleanVal === work[field]) return;
    work[field] = cleanVal;

    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/plan-works/${work.id}/field`, {
            field,
            value: cleanVal,
        });
        if (res.data?.data) {
            Object.assign(work, res.data.data);
        }
        recalcTotal();
        emit('updated');
    } catch (e) {
        console.error('Ошибка сохранения ячейки:', e);
    }
};

const canReviewRow = (work) => {
    if (isServiceUser.value) return false;
    return Number(work.author_type) === 4 || work.approval_status === 'pending';
};

const setRowApproval = async (work, status) => {
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/plan-works/${work.id}/row-approval`, {
            status,
        });
        if (res.data?.data) {
            work.approval_status = res.data.data.approval_status;
        } else {
            work.approval_status = status;
        }
        fetchPlanWorks();
        emit('updated');
    } catch (e) {
        console.error('Ошибка смены статуса строки:', e);
    }
};

const submitServiceApproval = async () => {
    if (!confirm('Отправить все плановые работы на согласование МРК?')) return;
    try {
        await axios.post(`/api/applications/${props.applicationId}/plan-works/service-submit`);
        serviceApproved.value = true;
        fetchPlanWorks();
        emit('updated');
    } catch (e) {
        console.error('Ошибка отправки на согласование:', e);
    }
};

const completeApproval = async () => {
    if (!confirm('Завершить согласование плановых работ по делу?')) return;
    try {
        await axios.post(`/api/applications/${props.applicationId}/plan-works/complete-approval`);
        fullyApproved.value = true;
        emit('updated');
    } catch (e) {
        console.error('Ошибка завершения согласования:', e);
    }
};

const deleteWork = async (work) => {
    if (!confirm(`Удалить работу "${work.name}"?`)) return;
    try {
        await axios.delete(`/api/applications/${props.applicationId}/plan-works/${work.id}`);
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

// Загрузка истории изменений
const openHistoryModal = async () => {
    showHistoryModal.value = true;
    historyLoading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/plan-works/history`);
        historyList.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка загрузки истории плановых работ:', err);
    } finally {
        historyLoading.value = false;
    }
};

const getAuthorBgClass = (work) => {
    if (!work) return 'row-auto';
    const type = Number(work.author_type ?? 0);
    const role = String(work.author_role ?? '').toLowerCase();

    if (type === 1 || role === 'mrk') return 'row-mrk';
    if (type === 2 || role === 'mentor') return 'row-mentor';
    if (type === 3 || role === 'spm') return 'row-spm';
    if (type === 4 || role === 'service') return 'row-service';
    if (type === 5 || role === 'other') return 'row-other';

    return 'row-auto';
};

const getApprovalBadgeClass = (st) => {
    switch (st) {
        case 'approved': return 'bg-success text-white';
        case 'rejected': return 'bg-danger text-white';
        default: return 'bg-warning text-dark';
    }
};

const translateApproval = (st) => {
    switch (st) {
        case 'approved': return 'Принято';
        case 'rejected': return 'Отклонено';
        default: return 'На проверке';
    }
};

const translateHistoryField = (field) => {
    const map = {
        name: 'Наименование',
        price: 'Цена',
        approval_status: 'Статус согласования',
        согласование: 'Согласование',
        позиция: 'Позиция',
        created: 'Добавление',
        deleted: 'Удаление',
        updated: 'Изменение',
        status_change: 'Решение МРК',
        service_submitted: 'Отправка СТО',
        approval_completed: 'Завершение',
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

onMounted(fetchPlanWorks);
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

:deep(.table-mandarin tbody tr.row-spm td),
.table-mandarin tbody tr.row-spm td {
    background-color: #dcfce7 !important;
    color: #14532d !important;
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

:deep(.table-mandarin tbody tr.row-rejected td),
.table-mandarin tbody tr.row-rejected td {
    background-color: #fee2e2 !important;
    text-decoration: line-through;
    opacity: 0.75;
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