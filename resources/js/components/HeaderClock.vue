<template>
    <div class="header-clock-widget d-flex align-items-center">
        <div class="clock-icon-wrap me-2">
            <i class="bi bi-clock"></i>
        </div>
        <div class="lh-1 text-end">
            <!-- Время с пульсирующим оранжевым двоеточием и секундами -->
            <div class="clock-time">
                <span class="hours">{{ hours }}</span>
                <span class="colon">:</span>
                <span class="minutes">{{ minutes }}</span>
                <span class="seconds ms-1">{{ seconds }}</span>
            </div>
            <!-- День недели и дата на русском -->
            <div class="clock-date">
                <span class="day-name">{{ dayName }},</span> {{ dateStr }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const hours = ref('00');
const minutes = ref('00');
const seconds = ref('00');
const dayName = ref('');
const dateStr = ref('');

let timer = null;

const daysOfWeek = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const months = [
    'янв', 'фев', 'мар', 'апр', 'мая', 'июн',
    'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'
];

const updateClock = () => {
    const now = new Date();

    hours.value = String(now.getHours()).padStart(2, '0');
    minutes.value = String(now.getMinutes()).padStart(2, '0');
    seconds.value = String(now.getSeconds()).padStart(2, '0');

    dayName.value = daysOfWeek[now.getDay()];
    dateStr.value = `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
};

onMounted(() => {
    updateClock();
    timer = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<style scoped>
.header-clock-widget {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border: 1px solid rgba(222, 226, 230, 0.7);
    border-radius: 6px;
    padding: 0.35rem 0.65rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    user-select: none;
}

.clock-icon-wrap {
    color: #f37021;
    font-size: 1.1rem;
}

.clock-time {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
    font-size: 0.95rem;
    font-weight: 700;
    color: #212529;
    letter-spacing: 0.02em;
}

.colon {
    color: #f37021;
    display: inline-block;
    animation: blinkColon 1s step-start infinite;
}

.seconds {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
}

.clock-date {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 2px;
}

.day-name {
    color: #e65100;
    font-weight: 600;
}

@keyframes blinkColon {
    50% {
        opacity: 0.3;
    }
}
</style>