<template>
    <div class="multiselect-dropdown-root" ref="dropdownRef">
        <!-- Кнопка-триггер в ячейке шапки -->
        <button type="button"
            class="form-control-table multiselect-trigger text-truncate text-start d-flex align-items-center justify-content-between"
            :class="{ 'has-value': selectedCount > 0, 'is-open': isOpen }" @click="toggleDropdown">
            <span class="text-truncate trigger-text">{{ displayLabel }}</span>
            <i class="bi bi-chevron-down trigger-icon ms-1"></i>
        </button>

        <!-- Выпадающее меню через Teleport, чтобы не резалось overflow таблицы -->
        <Teleport to="body">
            <div v-if="isOpen" class="multiselect-floating-menu shadow-lg" :style="{
                top: menuPosition.top + 'px',
                left: menuPosition.left + 'px',
                minWidth: minWidth || '180px',
            }" ref="menuRef">
                <!-- Поиск внутри выпадающего списка -->
                <div class="p-1 border-bottom bg-light">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 py-0 text-muted">
                            <i class="bi bi-search" style="font-size: 0.65rem;"></i>
                        </span>
                        <input ref="searchInputRef" v-model="searchQuery" type="text"
                            class="form-control form-control-sm border-start-0 py-0" placeholder="Поиск..."
                            style="font-size: 0.70rem; height: 24px;" />
                        <button v-if="searchQuery" class="btn btn-outline-secondary py-0 px-2" type="button"
                            @click="searchQuery = ''">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>

                <!-- Кнопки быстрого действия (Выбрать все / Снять все) -->
                <div class="d-flex justify-content-between px-2 py-1 bg-white border-bottom select-actions">
                    <button type="button" class="btn-link-action" @click="selectAll">
                        Выбрать все
                    </button>
                    <button type="button" class="btn-link-action text-danger" @click="clearAll">
                        Сбросить
                    </button>
                </div>

                <!-- Список элементов с чекбоксами -->
                <div class="multiselect-options-list">
                    <label v-for="opt in filteredOptions" :key="opt.value"
                        class="multiselect-option-item d-flex align-items-center px-2 py-1"
                        :class="{ selected: isSelected(opt.value) }">
                        <input type="checkbox" class="form-check-input me-2 mt-0" :checked="isSelected(opt.value)"
                            @change="toggleOption(opt.value)" />
                        <span class="option-label text-truncate" :title="opt.label">
                            {{ opt.label }}
                        </span>
                    </label>

                    <div v-if="filteredOptions.length === 0" class="p-2 text-center text-muted small">
                        Ничего не найдено
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Все',
    },
    minWidth: {
        type: String,
        default: '180px',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);
const menuRef = ref(null);
const searchInputRef = ref(null);

const menuPosition = ref({ top: 0, left: 0 });

const selectedCount = computed(() => (props.modelValue || []).length);

const displayLabel = computed(() => {
    if (!selectedCount.value) return props.placeholder;
    if (selectedCount.value === 1) {
        const found = props.options.find((o) => o.value === props.modelValue[0]);
        return found ? found.label : props.placeholder;
    }
    return `${props.placeholder} (${selectedCount.value})`;
});

const filteredOptions = computed(() => {
    if (!searchQuery.value.trim()) return props.options;
    const q = searchQuery.value.toLowerCase();
    return props.options.filter((o) => String(o.label).toLowerCase().includes(q));
});

const isSelected = (val) => (props.modelValue || []).includes(val);

const updatePosition = () => {
    if (!dropdownRef.value) return;
    const rect = dropdownRef.value.getBoundingClientRect();
    menuPosition.value = {
        top: rect.bottom + 2,
        left: rect.left,
    };
};

const toggleDropdown = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        updatePosition();
        searchQuery.value = '';
        await nextTick();
        if (searchInputRef.value) {
            searchInputRef.value.focus();
        }
    }
};

const toggleOption = (val) => {
    const current = [...(props.modelValue || [])];
    const idx = current.indexOf(val);
    if (idx > -1) {
        current.splice(idx, 1);
    } else {
        current.push(val);
    }
    emit('update:modelValue', current);
    emit('change', current);
};

const selectAll = () => {
    const allValues = props.options.map((o) => o.value);
    emit('update:modelValue', allValues);
    emit('change', allValues);
};

const clearAll = () => {
    emit('update:modelValue', []);
    emit('change', []);
};

const handleClickOutside = (e) => {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(e.target) &&
        menuRef.value &&
        !menuRef.value.contains(e.target)
    ) {
        isOpen.value = false;
    }
};

const handleScroll = () => {
    if (isOpen.value) {
        updatePosition();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleScroll);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('scroll', handleScroll, true);
    window.removeEventListener('resize', handleScroll);
});
</script>

<style scoped>
.multiselect-dropdown-root {
    position: relative;
    width: 100%;
}

.multiselect-trigger {
    cursor: pointer;
    background-color: #ffffff;
    user-select: none;
    font-weight: 500;
    color: #334155;
}

.multiselect-trigger.has-value {
    background-color: #fef08a !important;
    color: #854d0e !important;
    font-weight: 700;
}

.trigger-icon {
    font-size: 0.55rem;
    transition: transform 0.15s ease;
}

.multiselect-trigger.is-open .trigger-icon {
    transform: rotate(180deg);
}

.trigger-text {
    max-width: calc(100% - 12px);
}
</style>

<!-- Стили парящего меню (вставляется в body) -->
<style>
.multiselect-floating-menu {
    position: fixed;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    z-index: 999999;
    max-height: 260px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}

.select-actions {
    font-size: 0.65rem;
}

.btn-link-action {
    background: none;
    border: none;
    padding: 0;
    font-size: 0.65rem;
    color: #2563eb;
    cursor: pointer;
    text-decoration: underline;
}

.btn-link-action:hover {
    color: #1d4ed8;
}

.multiselect-options-list {
    overflow-y: auto;
    max-height: 180px;
    padding: 2px 0;
}

.multiselect-option-item {
    cursor: pointer;
    font-size: 0.70rem;
    color: #1e293b;
    user-select: none;
    transition: background-color 0.1s ease;
}

.multiselect-option-item:hover {
    background-color: #f1f5f9;
}

.multiselect-option-item.selected {
    background-color: #eff6ff;
    font-weight: 600;
    color: #1d4ed8;
}

.multiselect-option-item .form-check-input {
    width: 13px;
    height: 13px;
    cursor: pointer;
}

.option-label {
    line-height: 1.2;
}
</style>