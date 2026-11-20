<template>
    <div class="directory-standard-works container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1"><i class="bi bi-book-half text-primary me-2"></i>Справочник типовых работ</h4>
                <p class="text-muted small m-0">Ведение каталога операций и индивидуальных прайс-листов для автосервисов
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary btn-sm fw-bold" @click="openCreateModal">
                    <i class="bi bi-plus-lg me-1"></i> Добавить типовую работу
                </button>
            </div>
        </div>

        <div class="row g-3">
            <!-- ЛЕВАЯ КОЛОНКА: ВЫБОР СТО ДЛЯ РЕДАКТИРОВАНИЯ ЦЕН -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2.5 fw-bold small border-bottom">
                        <i class="bi bi-building me-1 text-secondary"></i> Автосервисы (СТО)
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 520px; overflow-y: auto;">
                        <button v-for="svc in services" :key="svc.id" type="button"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small"
                            :class="{ 'active fw-bold': selectedServiceId === svc.id }" @click="selectService(svc.id)">
                            <span class="text-truncate">{{ svc.name }}</span>
                            <i class="bi bi-chevron-right small"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ПРАВАЯ КОЛОНКА: КАТАЛОГ РАБОТ И ИНПУТЫ ЦЕН ВЫБРАННОГО СТО -->
            <div class="col-md-9">
                <div class="card border-0 shadow-sm">
                    <div
                        class="card-header bg-white py-2.5 d-flex justify-content-between align-items-center border-bottom">
                        <span class="fw-bold small">
                            Прайс-лист: <span class="text-primary">{{ currentServiceName }}</span>
                        </span>
                        <input v-model="searchQuery" type="text" class="form-control form-control-sm"
                            placeholder="Поиск по названию операции..." style="max-width: 260px;" />
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-sm mb-0 align-middle font-monospace"
                            style="font-size: 0.78rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th>Наименование типовой работы</th>
                                    <th style="width: 140px;">Категория</th>
                                    <th style="width: 150px;" class="text-end">Цена СТО (₽)</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in filteredItems" :key="item.id">
                                    <td class="text-center text-muted">{{ idx + 1 }}</td>
                                    <td class="fw-semibold text-dark">{{ item.name }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ item.category || 'слесарные'
                                        }}</span>
                                    </td>
                                    <td class="p-1 text-end">
                                        <input type="number"
                                            class="form-control form-control-sm text-end font-monospace fw-bold"
                                            :value="item.service_price || 0"
                                            @change="saveServicePrice(item.id, $event.target.value)" />
                                    </td>
                                    <td class="text-center p-1">
                                        <button class="btn btn-xs btn-outline-secondary" title="Редактировать позицию"
                                            @click="openEditModal(item)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredItems.length === 0">
                                    <td colspan="5" class="text-center py-4 text-muted">Работы не найдены</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- МОДАЛКА СОЗДАНИЯ/РЕДАКТИРОВАНИЯ ТИПОВОЙ РАБОТЫ -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-backdrop-custom" @click="showModal = false">
                <div class="modal-dialog-custom shadow-lg p-3" @click.stop style="max-width: 440px;">
                    <h6 class="fw-bold mb-3">{{ editingItem.id ? 'Редактировать работу' : 'Новая типовая работа' }}</h6>

                    <div class="mb-2">
                        <label class="form-label small text-muted m-0">Наименование операции</label>
                        <input v-model="editingItem.name" type="text" class="form-control form-control-sm" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted m-0">Категория</label>
                        <select v-model="editingItem.category" class="form-select form-select-sm">
                            <option value="слесарные">Слесарные</option>
                            <option value="кузовные">Кузовные</option>
                            <option value="малярные">Малярные</option>
                            <option value="диагностика">Диагностика</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-light border" @click="showModal = false">Отмена</button>
                        <button class="btn btn-sm btn-primary fw-bold" @click="saveStandardWork">Сохранить</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const services = ref([]);
const selectedServiceId = ref(1);
const works = ref([]);
const searchQuery = ref('');

const showModal = ref(false);
const editingItem = ref({ id: null, name: '', category: 'слесарные' });

const currentServiceName = computed(() => {
    const svc = services.value.find(s => s.id === selectedServiceId.value);
    return svc ? svc.name : 'Все СТО';
});

const filteredItems = computed(() => {
    if (!searchQuery.value.trim()) return works.value;
    const q = searchQuery.value.toLowerCase();
    return works.value.filter(w => w.name.toLowerCase().includes(q));
});

const fetchServices = async () => {
    try {
        const res = await axios.get('/api/services');
        services.value = res.data.data || res.data || [{ id: 1, name: 'СТО Мандарин Основной' }];
        if (services.value.length > 0) {
            selectedServiceId.value = services.value[0].id;
            fetchPrices();
        }
    } catch (e) {
        services.value = [{ id: 1, name: 'СТО Мандарин Основной' }];
        fetchPrices();
    }
};

const fetchPrices = async () => {
    try {
        const res = await axios.get(`/api/standard-works/service-prices/${selectedServiceId.value}`);
        works.value = res.data.data || [];
    } catch (e) {
        console.error('Ошибка загрузки цен СТО:', e);
    }
};

const selectService = (id) => {
    selectedServiceId.value = id;
    fetchPrices();
};

const saveServicePrice = async (workId, price) => {
    try {
        await axios.post(`/api/standard-works/service-prices/${selectedServiceId.value}`, {
            standard_work_id: workId,
            price: parseFloat(price) || 0,
        });
    } catch (e) {
        console.error('Ошибка сохранения цены СТО:', e);
    }
};

const openCreateModal = () => {
    editingItem.value = { id: null, name: '', category: 'слесарные' };
    showModal.value = true;
};

const openEditModal = (item) => {
    editingItem.value = { ...item };
    showModal.value = true;
};

const saveStandardWork = async () => {
    if (!editingItem.value.name.trim()) return;
    try {
        await axios.post('/api/standard-works', editingItem.value);
        showModal.value = false;
        fetchPrices();
    } catch (e) {
        console.error('Ошибка сохранения типовой работы:', e);
    }
};

onMounted(() => {
    fetchServices();
});
</script>

<style scoped>
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
    font-size: 0.65rem;
    padding: 1px 5px;
}
</style>