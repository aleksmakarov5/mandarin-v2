<template>
    <Teleport to="body">
        <div v-if="isOpen" class="mob-filter-backdrop" @click="$emit('close')"></div>
        <div v-if="isOpen" class="mob-filter-drawer shadow-lg">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                <h6 class="mb-0 fw-bold d-flex align-items-center">
                    <i class="bi bi-funnel-fill text-primary me-2"></i>
                    Фильтры реестра
                </h6>
                <button class="btn btn-sm btn-light border-0" @click="$emit('close')">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            <div class="p-3 mob-filter-content">
                <!-- Статус дела -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-muted">Статус дела</label>
                    <div class="mob-chip-list">
                        <span v-for="st in statusOptions" :key="st.value" class="mob-chip"
                            :class="{ active: filters.status_ids.includes(st.value) }"
                            @click="toggleArrayFilter('status_ids', st.value)">
                            {{ st.label }}
                        </span>
                    </div>
                </div>

                <!-- Фин. статус -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-muted">Согл/Опл (Фин. статус)</label>
                    <div class="mob-chip-list">
                        <span v-for="fst in financeStatusOptions" :key="fst.value" class="mob-chip"
                            :class="{ active: filters.finance_status_ids.includes(fst.value) }"
                            @click="toggleArrayFilter('finance_status_ids', fst.value)">
                            {{ fst.label }}
                        </span>
                    </div>
                </div>

                <!-- Сотрудник (МРК) -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-muted">Сотрудник (МРК)</label>
                    <select v-model="filters.mrk_ids" class="form-select form-select-sm" multiple style="height: 90px;">
                        <option v-for="emp in employeeOptions" :key="emp.value" :value="emp.value">
                            {{ emp.label }}
                        </option>
                    </select>
                </div>

                <!-- Город -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-muted">Город</label>
                    <div class="mob-chip-list">
                        <span v-for="c in cityOptions" :key="c.value" class="mob-chip"
                            :class="{ active: filters.city_ids.includes(c.value) }"
                            @click="toggleArrayFilter('city_ids', c.value)">
                            {{ c.label }}
                        </span>
                    </div>
                </div>

                <!-- Страховая компания -->
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1 text-muted">Страховая компания (СК)</label>
                    <select v-model="filters.insurance_ids" class="form-select form-select-sm" multiple
                        style="height: 90px;">
                        <option v-for="ic in insuranceOptions" :key="ic.value" :value="ic.value">
                            {{ ic.label }}
                        </option>
                    </select>
                </div>

                <!-- Текстовые поля -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1 text-muted">ГРЗ (номер)</label>
                        <input v-model="filters.gnz" type="text" class="form-control form-control-sm text-uppercase"
                            placeholder="А001АА29" />
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1 text-muted">№ убытка</label>
                        <input v-model="filters.ub_number" type="text" class="form-control form-control-sm"
                            placeholder="№ дела СК" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold mb-1 text-muted">VIN автомобиля</label>
                        <input v-model="filters.vin" type="text" class="form-control form-control-sm text-uppercase"
                            placeholder="VIN..." />
                    </div>
                </div>
            </div>

            <div class="p-3 border-top bg-light d-flex gap-2">
                <button class="btn btn-outline-danger btn-sm flex-fill py-2" @click="$emit('reset')">
                    Сбросить
                </button>
                <button class="btn btn-primary btn-sm flex-fill py-2 fw-bold" @click="$emit('apply')">
                    Показать ({{ total }})
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
const props = defineProps({
    isOpen: Boolean,
    filters: Object,
    statusOptions: Array,
    financeStatusOptions: Array,
    employeeOptions: Array,
    cityOptions: Array,
    insuranceOptions: Array,
    total: Number,
});

const emit = defineEmits(['close', 'apply', 'reset']);

const toggleArrayFilter = (field, value) => {
    const current = [...props.filters[field]];
    const idx = current.indexOf(value);
    if (idx > -1) current.splice(idx, 1);
    else current.push(value);
    props.filters[field] = current;
};
</script>

<style scoped>
.mob-filter-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999998;
    backdrop-filter: blur(2px);
}

.mob-filter-drawer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: 85vh;
    max-height: 85vh;
    background: #ffffff;
    border-radius: 16px 16px 0 0;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.25s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
    }

    to {
        transform: translateY(0);
    }
}

.mob-filter-content {
    overflow-y: auto;
    flex: 1;
}

.mob-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.mob-chip {
    padding: 4px 8px;
    font-size: 0.72rem;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

.mob-chip.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #1d4ed8;
    font-weight: 600;
}
</style>