<template>
    <div class="lawyer-cell-wrapper d-inline-block text-truncate" style="max-width: 100%;">
        <!-- Если юрист назначен: цветной бейдж с фамилией/именем -->
        <span v-if="hasLawyer" class="badge-lawyer cell-truncate d-inline-block"
            :class="isCooperating ? 'badge-lawyer-coop' : 'badge-lawyer-no-coop'"
            @mouseenter="$emit('hover', $event, tooltipText)" @mouseleave="$emit('leave')">
            {{ displayName }}
        </span>

        <!-- Если юрист не назначен: прочерк -->
        <span v-else class="text-muted" style="font-size: 0.70rem;">—</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

defineEmits(['hover', 'leave']);

const lawyerObj = computed(() => {
    return props.item.lawyer || props.item.jurist || null;
});

const hasLawyer = computed(() => {
    const lid = Number(props.item.id_lawyer || props.item.lawyer_id || lawyerObj.value?.id || 0);
    return lid > 0 || Boolean(lawyerObj.value?.name);
});

const isCooperating = computed(() => {
    if (!lawyerObj.value) return true;
    return lawyerObj.value.is_cooperating !== false && lawyerObj.value.is_cooperating !== 0;
});

const displayName = computed(() => {
    if (!lawyerObj.value?.name) return `Юрист #${props.item.id_lawyer || ''}`;
    const parts = lawyerObj.value.name.trim().split(/\s+/);
    // Выводим Фамилию или первое слово ФИО
    return parts[0] || lawyerObj.value.name;
});

const tooltipText = computed(() => {
    if (!hasLawyer.value) return '—';
    const name = lawyerObj.value?.name || `Юрист #${props.item.id_lawyer}`;
    const phone = lawyerObj.value?.phone ? ` (${lawyerObj.value.phone})` : '';
    const status = isCooperating.value ? 'Сотрудничает' : 'НЕ сотрудничает';
    return `Юрист [${status}]: ${name}${phone}`;
});
</script>

<style scoped>
.badge-lawyer {
    font-size: 0.65rem !important;
    font-weight: 600;
    padding: 1px 4px;
    border-radius: 3px;
    line-height: 1.2;
    letter-spacing: -0.01em;
    max-width: 100%;
    cursor: default;
}

.badge-lawyer-coop {
    background-color: #dcfce7 !important;
    color: #15803d !important;
    border: 1px solid #86efac !important;
}

.badge-lawyer-no-coop {
    background-color: #fee2e2 !important;
    color: #b91c1c !important;
    border: 1px solid #fca5a5 !important;
}
</style>