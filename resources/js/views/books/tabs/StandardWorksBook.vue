<template>
    <div class="standard-works-book-wrapper bg-white border rounded shadow-xs p-3">
        <!-- ПАНЕЛЬ УПРАВЛЕНИЯ -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-wrap" style="max-width: 700px;">
                <!-- Выбор режима: Общий каталог или СТО -->
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-light text-muted fw-semibold">Режим:</span>
                    <select v-model.number="selectedServiceId" class="form-select fw-semibold" @change="fetchWorks">
                        <option :value="0">Общий каталог (базовые цены)</option>
                        <option v-for="srv in servicesList" :key="srv.id" :value="srv.id">
                            СТО: {{ srv.name }}
                        </option>
                    </select>
                </div>

                <!-- Поиск -->
                <div class="input-group input-group-sm" style="min-width: 200px; max-width: 300px;">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input v-model="searchQuery" type="text" class="form-control"
                        placeholder="Поиск типовой работы..." />
                </div>
            </div>

            <!-- Кнопка создания -->
            <div>
                <button v-if="selectedServiceId === 0" type="button"
                    class="btn btn-sm btn-primary fw-bold d-flex align-items-center shadow-xs" @click="openWorkModal()">
                    <i class="bi bi-plus-lg me-1"></i> Добавить операцию
                </button>
                <span v-else class="badge bg-light text-dark border p-2">
                    Установка расценок для: <strong class="text-primary">{{ currentServiceName }}</strong>
                </span>
            </div>
        </div>

        <!-- ТАБЛИЦА СПРАВОЧНИКА -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-mandarin mb-0 align-middle">
                <thead>
                    <tr class="header-blue text-center">
                        <th style="width: 45px;">№</th>
                        <th class="text-start ps-3">Наименование типовой работы</th>
                        <th style="width: 140px;">Категория</th>
                        <th style="width: 110px;">Нормо-часы</th>
                        <th style="width: 150px;" class="text-end pe-3">Базовая цена (₽)</th>
                        <th v-if="selectedServiceId > 0" style="width: 180px;" class="text-end pe-3">
                            Цена СТО ({{ currentServiceName }})
                        </th>
                        <th style="width: 80px;">Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in filteredWorks" :key="item.id">
                        <td class="text-center font-monospace text-muted small">{{ idx + 1 }}</td>

                        <!-- Наименование -->
                        <td class="text-start ps-3 fw-semibold text-dark">
                            {{ item.name }}
                        </td>

                        <!-- Категория -->
                        <td class="text-center">
                            <span class="badge bg-light text-secondary border font-monospace">
                                {{ item.category || 'слесарные' }}
                            </span>
                        </td>

                        <!-- Нормо-часы -->
                        <td class="text-center font-monospace">
                            {{ item.default_norm_hours ? Number(item.default_norm_hours).toFixed(2) : '0.00' }} н/ч
                        </td>

                        <!-- БАЗОВАЯ ЦЕНА: в общем каталоге доступно быстрое inline-редактирование -->
                        <td class="text-end pe-3 font-monospace fw-bold text-dark"
                            :class="{ 'editable-cell': selectedServiceId === 0 }"
                            :contenteditable="selectedServiceId === 0" @blur="onBasePriceBlur(item, $event)"
                            @keydown.enter.prevent="$event.target.blur()" title="Нажмите, чтобы изменить базовую цену">
                            {{ formatMoney(item.base_price) }} ₽
                        </td>

                        <!-- ЦЕНА ВЫБРАННОГО СТО -->
                        <td v-if="selectedServiceId > 0" class="text-end pe-3">
                            <div v-if="item.has_service_price"
                                class="d-flex align-items-center justify-content-end gap-1">
                                <span class="font-monospace fw-bold text-success">{{ formatMoney(item.service_price) }}
                                    ₽</span>
                                <button type="button" class="btn btn-link btn-xs text-muted p-0 ms-1"
                                    title="Изменить цену для СТО" @click="openSetServicePriceModal(item)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                            <div v-else>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                    @click="openSetServicePriceModal(item)">
                                    + Задать цену
                                </button>
                            </div>
                        </td>

                        <!-- ДЕЙСТВИЯ -->
                        <td class="text-center p-1">
                            <div v-if="selectedServiceId === 0" class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-light border btn-xs" title="Редактировать работу"
                                    @click="openWorkModal(item)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-xs" title="Удалить из каталога"
                                    @click="deleteWork(item)">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                            <div v-else>
                                <button v-if="item.has_service_price" type="button"
                                    class="btn btn-link btn-xs text-danger p-0" title="Сбросить цену для этого СТО"
                                    @click="removeServicePrice(item)">
                                    <i class="bi bi-x-circle fs-6"></i>
                                </button>
                                <span v-else class="text-muted small">—</span>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="filteredWorks.length === 0">
                        <td :colspan="selectedServiceId > 0 ? 7 : 6" class="text-center py-4 text-muted">
                            Типовые работы не найдены. Добавьте первую операцию.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- МОДАЛЬНОЕ ОКНО: ДОБАВЛЕНИЕ / РЕДАКТИРОВАНИЕ ОПЕРАЦИИ В КАТАЛОГ -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-backdrop-custom" @click="showModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 480px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold">{{ editForm.id ? 'Редактировать операцию' : 'Новая типовая работа' }}
                        </h6>
                        <button type="button" class="btn-close" @click="showModal = false"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="mb-2.5">
                            <label class="form-label small fw-bold mb-1">Наименование работы *</label>
                            <input v-model="editForm.name" type="text" class="form-control form-control-sm"
                                placeholder="Например: Снятие и установка бампера" autofocus />
                        </div>

                        <div class="row g-2 mb-2.5">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Категория</label>
                                <select v-model="editForm.category" class="form-select form-select-sm">
                                    <option value="слесарные">Слесарные</option>
                                    <option value="кузовные">Кузовные</option>
                                    <option value="малярные">Малярные</option>
                                    <option value="арматурные">Арматурные</option>
                                    <option value="диагностика">Диагностика</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">Нормо-часы</label>
                                <input v-model.number="editForm.default_norm_hours" type="number" step="0.1"
                                    class="form-control form-control-sm font-monospace" placeholder="1.0" />
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-bold mb-1">Базовая стоимость (₽) *</label>
                            <input v-model.number="editForm.price" type="number" step="50"
                                class="form-control form-control-sm font-monospace" placeholder="1000" />
                        </div>
                    </div>
                    <div class="modal-footer p-2.5 px-3 bg-light border-top d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            @click="showModal = false">Отмена</button>
                        <button type="button" class="btn btn-sm btn-primary fw-bold"
                            :disabled="!editForm.name.trim() || editForm.price === null" @click="saveWork">
                            Сохранить в каталог
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- МОДАЛЬНОЕ ОКНО: УСТАНОВКА ЦЕНЫ ДЛЯ ВЫБРАННОГО СТО -->
        <Teleport to="body">
            <div v-if="showPriceModal" class="modal-backdrop-custom" @click="showPriceModal = false">
                <div class="modal-dialog-custom shadow-lg" @click.stop style="max-width: 420px;">
                    <div
                        class="modal-header d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                        <h6 class="m-0 fw-bold">Цена для СТО: {{ currentServiceName }}</h6>
                        <button type="button" class="btn-close" @click="showPriceModal = false"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="fw-semibold text-dark mb-1">{{ targetWorkItem?.name }}</div>
                        <div class="text-muted small mb-2">Базовая цена в каталоге: {{
                            formatMoney(targetWorkItem?.base_price) }} ₽</div>

                        <div>
                            <label class="form-label small fw-bold mb-1">Стоимость в данном СТО (₽) *</label>
                            <input v-model.number="customPriceInput" type="number" step="50"
                                class="form-control form-control-sm font-monospace" placeholder="Введите сумму"
                                autofocus />
                        </div>
                    </div>
                    <div class="modal-footer p-2.5 px-3 bg-light border-top d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            @click="showPriceModal = false">Отмена</button>
                        <button type="button" class="btn btn-sm btn-success fw-bold"
                            :disabled="!customPriceInput || customPriceInput <= 0" @click="saveCustomPrice">
                            Сохранить цену СТО
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const works = ref([]);
const servicesList = ref([]);
const selectedServiceId = ref(0);
const searchQuery = ref('');

const showModal = ref(false);
const showPriceModal = ref(false);
const targetWorkItem = ref(null);
const customPriceInput = ref(0);

const editForm = reactive({
    id: null,
    name: '',
    category: 'слесарные',
    default_norm_hours: 0,
    price: 0,
});

const currentServiceName = computed(() => {
    const s = servicesList.value.find(item => item.id === selectedServiceId.value);
    return s ? s.name : 'СТО';
});

// Отфильтрованные работы (исключаем пустые строки из базы)
const filteredWorks = computed(() => {
    const cleanList = works.value.filter(w => w && w.name && String(w.name).trim() !== '');
    if (!searchQuery.value.trim()) return cleanList;
    const q = searchQuery.value.toLowerCase();
    return cleanList.filter(w =>
        w.name.toLowerCase().includes(q) ||
        (w.category && w.category.toLowerCase().includes(q))
    );
});

// Загрузка списка автосервисов
const fetchServices = async () => {
    try {
        const res = await axios.get('/api/standard-works/services-list');
        servicesList.value = res.data?.data || [];
    } catch (e) {
        // Резервный вызов
        try {
            const fb = await axios.get('/api/admin/services');
            servicesList.value = fb.data?.data || fb.data || [];
        } catch (fbErr) {
            servicesList.value = [];
        }
    }
};

// Загрузка списка работ
const fetchWorks = async () => {
    try {
        const url = selectedServiceId.value > 0
            ? `/api/standard-works/service-prices/${selectedServiceId.value}`
            : '/api/standard-works';
        const res = await axios.get(url);
        works.value = res.data?.data || [];
    } catch (e) {
        console.error('Ошибка загрузки типовых работ:', e);
        works.value = [];
    }
};

// Открытие модалки создания/редактирования работы каталога
const openWorkModal = (item = null) => {
    if (item) {
        editForm.id = item.id;
        editForm.name = item.name;
        editForm.category = item.category || 'слесарные';
        editForm.default_norm_hours = item.default_norm_hours || 0;
        editForm.price = item.base_price !== undefined ? item.base_price : (item.price || 0);
    } else {
        editForm.id = null;
        editForm.name = '';
        editForm.category = 'слесарные';
        editForm.default_norm_hours = 0;
        editForm.price = 0;
    }
    showModal.value = true;
};

// Сохранение работы каталога
const saveWork = async () => {
    if (!editForm.name.trim()) return;
    try {
        await axios.post('/api/standard-works', {
            id: editForm.id,
            name: editForm.name.trim(),
            category: editForm.category,
            default_norm_hours: editForm.default_norm_hours,
            base_price: editForm.price,
            price: editForm.price,
        });
        showModal.value = false;
        await fetchWorks();
    } catch (e) {
        alert(e.response?.data?.message || 'Ошибка сохранения');
    }
};

// Быстрое сохранение базовой цены прямо из ячейки таблицы
const onBasePriceBlur = async (item, event) => {
    const raw = event.target.innerText;
    const num = parseFloat(String(raw).replace(/\s+/g, '').replace(',', '.')) || 0;
    if (num === Number(item.base_price)) return;

    item.base_price = num;
    item.price = num;

    try {
        await axios.put(`/api/standard-works/${item.id}/field`, {
            field: 'base_price',
            value: num,
        });
    } catch (e) {
        console.error('Ошибка сохранения цены:', e);
    }
};

// Открытие модалки назначения цены для выбранного СТО
const openSetServicePriceModal = (item) => {
    targetWorkItem.value = item;
    customPriceInput.value = item.service_price || item.base_price || 0;
    showPriceModal.value = true;
};

// Сохранение цены для СТО
const saveCustomPrice = async () => {
    if (!targetWorkItem.value || !selectedServiceId.value) return;
    try {
        await axios.post(`/api/standard-works/service-prices/${selectedServiceId.value}`, {
            standard_work_id: targetWorkItem.value.id,
            price: customPriceInput.value,
        });
        showPriceModal.value = false;
        await fetchWorks();
    } catch (e) {
        alert('Не удалось сохранить цену для СТО');
    }
};

// Сброс цены для СТО
const removeServicePrice = async (item) => {
    if (!confirm(`Удалить назначенную цену для "${item.name}" в СТО ${currentServiceName.value}?`)) return;
    try {
        await axios.post(`/api/standard-works/service-prices/${selectedServiceId.value}`, {
            standard_work_id: item.id,
            price: null,
        });
        await fetchWorks();
    } catch (e) {
        alert('Ошибка удаления цены');
    }
};

// Удаление работы из каталога
const deleteWork = async (item) => {
    if (!confirm(`Удалить операцию "${item.name}" из общего каталога?`)) return;
    try {
        await axios.delete(`/api/standard-works/${item.id}`);
        await fetchWorks();
    } catch (e) {
        alert('Ошибка удаления');
    }
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(async () => {
    await fetchServices();
    await fetchWorks();
});
</script>

<style scoped>
.table-mandarin {
    table-layout: fixed;
    width: 100%;
    font-size: 0.74rem;
}

.header-blue th {
    background-color: #299bd1 !important;
    color: #fff;
    font-weight: 700;
    padding: 4px;
}

.editable-cell {
    outline: none;
    cursor: text;
}

.editable-cell:focus {
    background-color: #e0f2fe !important;
    box-shadow: inset 0 0 0 1px #0284c7;
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

.btn-xs {
    font-size: 0.70rem;
    padding: 2px 5px;
}
</style>