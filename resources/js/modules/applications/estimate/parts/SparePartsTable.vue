<template>
    <div class="spare-parts-module-container" @click="closeCommentPopover">
        <!-- ВЕРХНЯЯ ПАНЕЛЬ ДЕЙСТВИЙ -->
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <!-- Кнопка Добавить позицию (для МРК / СПМ) -->
                <button v-if="!isStoUser" type="button"
                    class="btn btn-sm btn-mandarin-add fw-bold d-flex align-items-center" @click="addNewRow">
                    <i class="bi bi-plus-circle me-1"></i> Добавить запчасть
                </button>

                <!-- Кнопка Мелкие детали (2%) -->
                <button v-if="!isStoUser" type="button"
                    class="btn btn-sm btn-small-parts fw-bold d-flex align-items-center" @click="handleSmallPartsClick"
                    title="Рассчитать 2% от суммы калькуляции">
                    <i class="bi bi-percent me-1"></i> Мелкие детали
                </button>

                <!-- Индикатор режима КАСКО / ОСАГО -->
                <span v-if="isCasco"
                    class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-2 py-1">
                    КАСКО (Оригинал)
                </span>
                <span v-else
                    class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1">
                    ОСАГО
                </span>
            </div>

            <!-- Кнопка История изменений -->
            <div>
                <button type="button"
                    class="btn btn-sm btn-outline-secondary btn-history fw-semibold d-flex align-items-center shadow-xs"
                    @click="openHistoryModal">
                    <i class="bi bi-clock-history me-1 text-primary"></i> История изменений
                </button>
            </div>
        </div>

        <!-- ТАБЛИЦА ЗАПЧАСТЕЙ -->
        <div class="table-responsive bg-white border rounded-1 shadow-sm">
            <table class="table table-bordered table-mandarin mb-0 align-middle">
                <thead>
                    <tr class="header-blue text-center">
                        <th style="width: 36px;">#</th>
                        <th style="min-width: 220px;">Наименование</th>
                        <th style="width: 140px;">Кат. номер</th>

                        <!-- Колонки цен калькуляции (только для МРК/СПМ) -->
                        <template v-if="!isStoUser">
                            <th style="width: 105px;">Цена СК</th>
                            <th style="width: 105px;">Цена ПЗН</th>
                            <th style="width: 100px;">Оригинал</th>

                            <!-- Аналог и Б/у скрыты при КАСКО -->
                            <template v-if="!isCasco">
                                <th style="width: 100px;">Аналог</th>
                                <th style="width: 100px;">Б/у</th>
                            </template>
                        </template>

                        <!-- Цена СТО (доступна и сервису, и МРК) -->
                        <th style="width: 100px;">Цена СТО</th>

                        <!-- Ссылки на проценку в агрегаторах -->
                        <th style="width: 44px;" title="Поиск на ЕвроАвто">ЕА</th>
                        <th style="width: 44px;" title="Поиск на Автопитер">АП</th>
                        <th style="width: 44px;" title="Поиск на Emex">EM</th>

                        <th style="width: 130px;">Комментарий</th>

                        <!-- Колонки логистики (во вкладке Направления или для СТО) -->
                        <template v-if="isNaprTab || isStoUser">
                            <th style="width: 135px;">Статус</th>
                            <th style="width: 110px;">Дата заказа</th>
                            <th style="width: 115px;">Ожидаемая дата</th>
                        </template>

                        <!-- Удаление строки (только для МРК) -->
                        <th v-if="!isStoUser" style="width: 36px;"></th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="(part, idx) in parts" :key="part.id" class="spare-part-row"
                        :class="{ 'row-manual': part.is_manual, 'row-auto': !part.is_manual, 'row-small-parts': part.is_small_parts }">
                        <!-- 1. Номер п/п с тултипом автора при ручном добавлении -->
                        <td class="text-center font-monospace cell-index position-relative">
                            <span class="badge-index" :class="{ 'has-creator-tooltip': part.is_manual }"
                                :data-tooltip="getCreatorTooltip(part)">
                                {{ idx + 1 }}
                            </span>
                        </td>

                        <!-- 2. Наименование (contenteditable) -->
                        <td class="editable-cell text-start fw-semibold" :contenteditable="!part.is_small_parts"
                            @blur="saveField(part, 'name', $event.target.innerText)"
                            @keydown.enter.prevent="$event.target.blur()">
                            {{ part.name }}
                        </td>

                        <!-- 3. Каталожный номер (contenteditable) -->
                        <td class="editable-cell text-center font-monospace text-uppercase"
                            :contenteditable="!part.is_small_parts"
                            @blur="saveField(part, 'part_number', $event.target.innerText.trim())"
                            @keydown.enter.prevent="$event.target.blur()">
                            {{ part.part_number }}
                        </td>

                        <!-- ЦЕНЫ КАЛЬКУЛЯЦИИ (СК и ПЗН) -->
                        <template v-if="!isStoUser">
                            <!-- Цена СК (темный графитовый фон) -->
                            <td class="cell-dark text-end font-monospace text-white editable-cell"
                                contenteditable="true" @blur="saveMoneyField(part, 'price_ic', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(part.price_ic) }}
                            </td>

                            <!-- Цена ПЗН (жирный шрифт, если отличается от цены СК) -->
                            <td class="cell-dark text-end font-monospace text-white editable-cell"
                                :class="{ 'pzn-highlight': isPznDiff(part) }" contenteditable="true"
                                @blur="saveMoneyField(part, 'price_pzn', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()">
                                {{ formatMoney(part.price_pzn) }}
                            </td>

                            <!-- Оригинал (ПКМ для выбора selected_mrk) -->
                            <td class="text-end font-monospace editable-cell selectable-price"
                                :class="{ 'selected-mrk-active': part.selected_mrk === 'original' }"
                                contenteditable="true" @contextmenu.prevent="toggleMrkSelection(part, 'original')"
                                @blur="saveMoneyField(part, 'price_original', $event.target.innerText)"
                                @keydown.enter.prevent="$event.target.blur()"
                                title="ПКМ — выбрать этот вариант закупки для МРК">
                                {{ formatMoneyOrEmpty(part.price_original) }}
                            </td>

                            <!-- Аналог и Б/у (скрываются при КАСКО) -->
                            <template v-if="!isCasco">
                                <td class="text-end font-monospace editable-cell selectable-price"
                                    :class="{ 'selected-mrk-active': part.selected_mrk === 'analogue' }"
                                    contenteditable="true" @contextmenu.prevent="toggleMrkSelection(part, 'analogue')"
                                    @blur="saveMoneyField(part, 'price_analogue', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    title="ПКМ — выбрать этот вариант закупки для МРК">
                                    {{ formatMoneyOrEmpty(part.price_analogue) }}
                                </td>

                                <td class="text-end font-monospace editable-cell selectable-price"
                                    :class="{ 'selected-mrk-active': part.selected_mrk === 'used' }"
                                    contenteditable="true" @contextmenu.prevent="toggleMrkSelection(part, 'used')"
                                    @blur="saveMoneyField(part, 'price_used', $event.target.innerText)"
                                    @keydown.enter.prevent="$event.target.blur()"
                                    title="ПКМ — выбрать этот вариант закупки для МРК">
                                    {{ formatMoneyOrEmpty(part.price_used) }}
                                </td>
                            </template>
                        </template>

                        <!-- Цена СТО (ПКМ для МРК / обычный ввод для сервиса) -->
                        <td class="text-end font-monospace editable-cell selectable-price"
                            :class="{ 'selected-mrk-active': part.selected_mrk === 'sto' }" contenteditable="true"
                            @contextmenu.prevent="!isStoUser && toggleMrkSelection(part, 'sto')"
                            @blur="saveMoneyField(part, 'price_sto', $event.target.innerText)"
                            @keydown.enter.prevent="$event.target.blur()" title="ПКМ — выбрать вариант поставки от СТО">
                            {{ formatMoneyOrEmpty(part.price_sto) }}
                        </td>

                        <!-- Ссылка на ЕвроАвто -->
                        <td class="text-center p-0">
                            <a v-if="cleanPartNumber(part.part_number)"
                                :href="`https://euroauto.ru/search/parts/${cleanPartNumber(part.part_number)}/`"
                                target="_blank" class="shop-link-btn" title="Искать деталь на ЕвроАвто">
                                -&gt;
                            </a>
                            <span v-else class="text-muted small">—</span>
                        </td>

                        <!-- Ссылка на Автопитер -->
                        <td class="text-center p-0">
                            <a v-if="cleanPartNumber(part.part_number)"
                                :href="`https://autopiter.ru/goods/${cleanPartNumber(part.part_number)}`"
                                target="_blank" class="shop-link-btn" title="Искать деталь на Автопитер">
                                -&gt;
                            </a>
                            <span v-else class="text-muted small">—</span>
                        </td>

                        <!-- Ссылка на Emex -->
                        <td class="text-center p-0">
                            <a v-if="cleanPartNumber(part.part_number)"
                                :href="`https://emex.ru/products/${cleanPartNumber(part.part_number)}`" target="_blank"
                                class="shop-link-btn" title="Искать деталь на Emex">
                                -&gt;
                            </a>
                            <span v-else class="text-muted small">—</span>
                        </td>

                        <!-- Комментарий: компактная плашка, клик открывает Popover -->
                        <td class="comment-cell text-truncate" @click.stop="openCommentEditor(part, $event)"
                            :title="part.comment || 'Нажмите, чтобы добавить комментарий'">
                            <span v-if="part.comment" class="text-dark fw-medium">{{ part.comment }}</span>
                            <span v-else class="text-muted fst-italic comment-placeholder">+ заметка</span>
                        </td>

                        <!-- ЛОГИСТИКА: СТАТУС И ДАТЫ (НАПРАВЛЕНИЯ / СТО) -->
                        <template v-if="isNaprTab || isStoUser">
                            <!-- Статус -->
                            <td class="p-1">
                                <select class="form-select form-select-xs status-select" :value="part.status"
                                    @change="onStatusSelect(part, $event.target.value)">
                                    <option value="В поиске">В поиске</option>
                                    <option value="Заказана">Заказана</option>
                                    <option value="Ожидается доставка">Ожидается доставка</option>
                                    <option value="На складе">На складе</option>
                                    <option value="Выдана на СТО">Выдана на СТО</option>
                                    <option value="Установлена">Установлена</option>
                                    <option value="Отказ">Отказ</option>
                                </select>
                            </td>

                            <!-- Дата заказа -->
                            <td class="text-center font-monospace small p-0">
                                <input type="date" class="date-input-table" :value="formatDateForInput(part.order_date)"
                                    @change="saveField(part, 'order_date', $event.target.value)" />
                            </td>

                            <!-- Дата поставки (сравнение с максимальной датой дела) -->
                            <td class="text-center font-monospace small p-0">
                                <input type="date" class="date-input-table"
                                    :value="formatDateForInput(part.delivery_date)"
                                    @change="saveField(part, 'delivery_date', $event.target.value)" />
                            </td>
                        </template>

                        <!-- Удаление (только МРК) -->
                        <td v-if="!isStoUser" class="text-center p-0">
                            <button type="button" class="btn btn-link btn-xs text-secondary hover-danger p-0"
                                title="Удалить позицию" @click="confirmDelete(part)">
                                <i class="bi bi-trash3 fs-6"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Пустое состояние таблицы -->
                    <tr v-if="parts.length === 0">
                        <td :colspan="tableColspan" class="text-center py-4 text-muted">
                            В смете нет запчастей. Нажмите кнопку «Добавить запчасть».
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ВСПЛЫВАЮЩИЙ POPOVER ДЛЯ РЕДАКТИРОВАНИЯ КОММЕНТАРИЯ -->
        <div v-if="activeCommentPart" class="comment-popover shadow-lg" :style="popoverStyle" @click.stop>
            <div class="popover-header d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                <span class="fw-bold small text-dark text-truncate" style="max-width: 220px;">
                    Заметка: {{ activeCommentPart.name }}
                </span>
                <button type="button" class="btn-close btn-close-xs" @click="closeCommentPopover"></button>
            </div>
            <textarea v-model="commentText" class="form-control form-control-sm mb-2" rows="3"
                placeholder="Введите служебную заметку по детали..."></textarea>
            <div class="d-flex justify-content-end gap-1">
                <button type="button" class="btn btn-xs btn-light border" @click="closeCommentPopover">
                    Отмена
                </button>
                <button type="button" class="btn btn-xs btn-primary fw-bold px-2" @click="saveComment">
                    Сохранить
                </button>
            </div>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО ИСТОРИИ ИЗМЕНЕНИЙ (АУДИТ-ЛОГ) -->
        <Teleport to="body">
            <div v-if="showHistoryModal" class="modal-backdrop-custom" @click="showHistoryModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop>
                    <div
                        class="modal-header-custom d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold d-flex align-items-center">
                            <i class="bi bi-clock-history me-2 text-primary fs-5"></i>
                            История изменений по запчастям дела #{{ applicationId }}
                        </h6>
                        <button type="button" class="btn-close" @click="showHistoryModal = false"></button>
                    </div>
                    <div class="modal-body-custom p-3" style="max-height: 480px; overflow-y: auto;">
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
                                        {{ h.user ? (h.user.last_name || h.user.name) : 'Система' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace">
                                            {{ translateField(h.field_name || h.action_type) }}
                                        </span>
                                    </td>
                                    <td class="text-danger text-truncate font-monospace" style="max-width: 160px;"
                                        :title="h.old_value">
                                        {{ h.old_value || '—' }}
                                    </td>
                                    <td class="text-success text-truncate font-monospace fw-semibold"
                                        style="max-width: 160px;" :title="h.new_value">
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
    applicationId: {
        type: Number,
        required: true,
    },
    currentTab: {
        type: String,
        default: 'requests', // 'requests' (запросы) или 'applications' (направления)
    },
});

const emit = defineEmits(['updated']);

const parts = ref([]);
const isCasco = ref(false);
const isStoUser = ref(false);

const showHistoryModal = ref(false);
const historyLoading = ref(false);
const historyList = ref([]);

// Popover комментария
const activeCommentPart = ref(null);
const commentText = ref('');
const popoverStyle = ref({ top: '0px', left: '0px' });

// Проверка: вкладка «Направления»
const isNaprTab = computed(() => {
    return props.currentTab === 'applications' || props.currentTab === 'napr';
});

// Динамический colspan для пустой строки таблицы
const tableColspan = computed(() => {
    let cols = 9; // базовые: #, имя, кат.номер, СТО, ЕА, АП, EM, коммент, корзина
    if (!isStoUser.value) {
        cols += 3; // Цена СК, Цена ПЗН, Оригинал
        if (!isCasco.value) cols += 2; // Аналог, Б/у
    }
    if (isNaprTab.value || isStoUser.value) {
        cols += 3; // Статус, Дата заказа, Дата поставки
    }
    return cols;
});

// Загрузка запчастей с сервера
const fetchParts = async () => {
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/spare-parts`);
        parts.value = res.data.data || [];
        isCasco.value = Boolean(res.data.is_casco);
        isStoUser.value = Boolean(res.data.is_sto);
    } catch (err) {
        console.error('Ошибка загрузки запчастей:', err);
    }
};

// Добавление новой пустой строки
const addNewRow = async () => {
    try {
        const res = await axios.post(`/api/applications/${props.applicationId}/spare-parts`, {
            name: 'Новая деталь',
            part_number: '',
            price_ic: 0,
            price_pzn: 0,
        });
        parts.value.push(res.data.data);
        emit('updated');
    } catch (err) {
        console.error('Ошибка добавления строки:', err);
    }
};

// Сохранение текстового поля по событию blur (contenteditable)
const saveField = async (part, field, value) => {
    if (part[field] == value) return;

    part[field] = value;
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/field`, {
            field,
            value,
        });
        if (res.data?.data) {
            Object.assign(part, res.data.data);
        }
        emit('updated');
    } catch (err) {
        console.error(`Ошибка сохранения поля ${field}:`, err);
    }
};

// Сохранение числовых/денежных полей
const saveMoneyField = async (part, field, rawValue) => {
    const cleanVal = parseFloat(String(rawValue).replace(/\s+/g, '').replace(',', '.')) || 0;
    saveField(part, field, cleanVal);
};

// Выбор варианта закупки МРК по правому клику мыши (ПКМ)
const toggleMrkSelection = async (part, type) => {
    try {
        const res = await axios.put(`/api/applications/${props.applicationId}/spare-parts/${part.id}/toggle-mrk`, {
            type,
        });
        part.selected_mrk = res.data.data.selected_mrk;
        emit('updated');
    } catch (err) {
        console.error('Ошибка выбора варианта МРК:', err);
    }
};

// Смена статуса позиции
const onStatusSelect = async (part, newStatus) => {
    await saveField(part, 'status', newStatus);
    if (newStatus === 'Заказана' && !part.order_date) {
        part.order_date = new Date().toISOString().substring(0, 10);
    }
};

// Работа с «Мелкими деталями» (2%)
const handleSmallPartsClick = async () => {
    try {
        const checkRes = await axios.post(`/api/applications/${props.applicationId}/spare-parts/small-parts`);
        if (checkRes.data.exists) {
            if (confirm('Позиция "Мелкие детали" уже создана. Удалить её из сметы?')) {
                await axios.delete(`/api/applications/${props.applicationId}/spare-parts/${checkRes.data.part_id}`);
                await fetchParts();
                emit('updated');
            }
        } else {
            await fetchParts();
            emit('updated');
        }
    } catch (err) {
        console.error('Ошибка обработки мелких деталей:', err);
    }
};

// Удаление запчасти
const confirmDelete = async (part) => {
    if (!confirm(`Удалить запчасть "${part.name}"?`)) return;

    try {
        await axios.delete(`/api/applications/${props.applicationId}/spare-parts/${part.id}`);
        parts.value = parts.value.filter((p) => p.id !== part.id);
        emit('updated');
    } catch (err) {
        console.error('Ошибка удаления:', err);
    }
};

// Popover для комментария
const openCommentEditor = (part, event) => {
    activeCommentPart.value = part;
    commentText.value = part.comment || '';
    const rect = event.currentTarget.getBoundingClientRect();
    popoverStyle.value = {
        top: `${rect.bottom + window.scrollY + 4}px`,
        left: `${Math.max(10, rect.left + window.scrollX - 70)}px`,
    };
};

const closeCommentPopover = () => {
    activeCommentPart.value = null;
};

const saveComment = async () => {
    if (activeCommentPart.value) {
        await saveField(activeCommentPart.value, 'comment', commentText.value);
        closeCommentPopover();
    }
};

// Модальное окно истории
const openHistoryModal = async () => {
    showHistoryModal.value = true;
    historyLoading.value = true;
    try {
        const res = await axios.get(`/api/applications/${props.applicationId}/spare-parts/history`);
        historyList.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка загрузки истории:', err);
    } finally {
        historyLoading.value = false;
    }
};

// Проверка несовпадения цены ПЗН и СК
const isPznDiff = (part) => {
    const ic = parseFloat(part.price_ic) || 0;
    const pzn = parseFloat(part.price_pzn) || 0;
    return pzn > 0 && Math.abs(ic - pzn) > 0.01;
};

// Очистка каталожного номера для внешних ссылок
const cleanPartNumber = (num) => {
    return num ? String(num).replace(/[^a-zA-Z0-9]/g, '') : '';
};

// Тултип автора для добавленных вручную строк
const getCreatorTooltip = (part) => {
    if (!part.is_manual) return '';
    const name = part.creator ? `${part.creator.last_name || ''} ${part.creator.name || ''}`.trim() : 'Пользователь';
    return `Добавлено вручную: ${name || 'Сотрудник'}`;
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatMoneyOrEmpty = (val) => {
    const n = parseFloat(val);
    if (!n || isNaN(n)) return '';
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDateForInput = (dateStr) => {
    if (!dateStr) return '';
    return String(dateStr).substring(0, 10);
};

const formatDateTime = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
};

// Читаемые имена полей для истории аудита
const translateField = (field) => {
    const map = {
        name: 'Наименование',
        part_number: 'Кат. номер',
        price_ic: 'Цена СК',
        price_pzn: 'Цена ПЗН',
        price_original: 'Оригинал',
        price_analogue: 'Аналог',
        price_used: 'Б/у',
        price_sto: 'Цена СТО',
        selected_mrk: 'Выбор МРК',
        status: 'Статус',
        order_date: 'Дата заказа',
        delivery_date: 'Дата поставки',
        comment: 'Комментарий',
        created: 'Создание',
        deleted: 'Удаление',
        supplier_selected: 'Выбор варианта закупки',
    };
    return map[field] || field;
};

onMounted(fetchParts);
</script>

<style scoped>
/* ТАБЛИЦА СМЕТЫ ЗАПЧАСТЕЙ */
.table-mandarin {
    table-layout: fixed;
    width: 100%;
    font-size: 0.75rem;
    border-collapse: collapse;
}

/* Синяя шапка */
.header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-weight: 600;
    padding: 5px 3px;
    border: 1px solid #1f8abf;
    white-space: nowrap;
}

/* Цветовое разделение: автоматические vs добавленные вручную */
.row-auto {
    background-color: #ffffff;
}

.row-manual {
    background-color: #fefce8 !important;
    /* Мягкий отличительный оттенок */
}

.row-small-parts {
    background-color: #f1f5f9 !important;
}

/* Редактируемые ячейки contenteditable */
.editable-cell {
    outline: none;
    cursor: text;
    padding: 3px 5px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: background-color 0.1s ease;
}

.editable-cell:focus {
    background-color: #e0f2fe !important;
    box-shadow: inset 0 0 0 1px #0284c7;
    color: #000000 !important;
}

/* Тёмные ячейки цен СК и ПЗН */
.cell-dark {
    background-color: #1e293b !important;
}

/* Выделение цены ПЗН жирным при несовпадении с ценой СК */
.pzn-highlight {
    font-weight: 900 !important;
    color: #fde047 !important;
    /* Яркий акцент на тёмном фоне */
}

/* Выбор варианта закупки по ПКМ (selected_mrk) */
.selectable-price {
    user-select: none;
    cursor: pointer;
    transition: all 0.15s ease;
}

.selected-mrk-active {
    background: linear-gradient(135deg, #16a34a, #22c55e) !important;
    color: #ffffff !important;
    font-weight: 800 !important;
    box-shadow: inset 0 0 0 1px #15803d;
}

/* Ссылки-стрелки на агрегаторы */
.shop-link-btn {
    display: inline-block;
    padding: 1px 6px;
    font-size: 0.72rem;
    font-weight: 800;
    text-decoration: none;
    border-radius: 2px;
    color: #ffffff;
    background-color: #299bd1;
}

.shop-link-btn:hover {
    background-color: #0284c7;
}

/* Комментарий */
.comment-cell {
    cursor: pointer;
    padding: 2px 6px !important;
    max-width: 130px;
}

.comment-placeholder {
    color: #94a3b8;
    font-size: 0.72rem;
}

/* Всплывающий тултип автора при наведении на номер # */
.has-creator-tooltip {
    position: relative;
    cursor: help;
}

.has-creator-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 105%;
    top: 50%;
    transform: translateY(-50%);
    background: #0f172a;
    color: #ffffff;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.70rem;
    white-space: nowrap;
    z-index: 1060;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    pointer-events: none;
}

/* Инпуты дат и селекты */
.status-select {
    font-size: 0.72rem;
    padding: 1px 2px;
    height: 22px;
}

.date-input-table {
    border: 1px solid #cbd5e1;
    border-radius: 2px;
    font-size: 0.70rem;
    padding: 0 2px;
    height: 20px;
    width: 100%;
}

/* Кнопки верхней панели */
.btn-mandarin-add {
    background-color: #299bd1;
    color: #ffffff;
}

.btn-mandarin-add:hover {
    background-color: #0284c7;
    color: #ffffff;
}

.btn-small-parts {
    background-color: #475569;
    color: #ffffff;
    border-radius: 20px;
}

.btn-small-parts:hover {
    background-color: #334155;
    color: #ffffff;
}

.hover-danger:hover {
    color: #dc2626 !important;
}

/* Всплывающий Popover для комментария */
.comment-popover {
    position: absolute;
    width: 270px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px;
    z-index: 1070;
}

.btn-close-xs {
    font-size: 0.60rem;
}

/* Модальное окно истории аудита */
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1080;
    backdrop-filter: blur(2px);
}

.modal-dialog-custom {
    background: #ffffff;
    border-radius: 8px;
    width: 90%;
    max-width: 820px;
    overflow: hidden;
}
</style>