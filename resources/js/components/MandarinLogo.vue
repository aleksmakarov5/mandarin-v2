<template>
    <div class="mandarin-brand-wrap d-inline-flex align-items-center">
        <!-- Векторный значок цитруса с анимацией вращения раз в 5 секунд -->
        <svg :width="size" :height="size" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg"
            class="flex-shrink-0 logo-spin">
            <defs>
                <!-- Металлический ободок -->
                <linearGradient id="metalRim" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#f0f2f5" />
                    <stop offset="25%" stop-color="#a8b0b8" />
                    <stop offset="50%" stop-color="#ffffff" />
                    <stop offset="75%" stop-color="#7a828a" />
                    <stop offset="100%" stop-color="#d0d5dd" />
                </linearGradient>

                <linearGradient id="innerRim" x1="100%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#6c757d" />
                    <stop offset="50%" stop-color="#e9ecef" />
                    <stop offset="100%" stop-color="#495057" />
                </linearGradient>

                <!-- Металлические дольки слева -->
                <linearGradient id="metalSlice1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#707880" />
                    <stop offset="45%" stop-color="#d8dee4" />
                    <stop offset="100%" stop-color="#ffffff" />
                </linearGradient>
                <linearGradient id="metalSlice2" x1="0%" y1="50%" x2="100%" y2="50%">
                    <stop offset="0%" stop-color="#adb5bd" />
                    <stop offset="50%" stop-color="#ffffff" />
                    <stop offset="100%" stop-color="#6c757d" />
                </linearGradient>
                <linearGradient id="metalSlice3" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#ffffff" />
                    <stop offset="55%" stop-color="#adb5bd" />
                    <stop offset="100%" stop-color="#495057" />
                </linearGradient>
                <linearGradient id="metalSlice4" x1="50%" y1="100%" x2="50%" y2="0%">
                    <stop offset="0%" stop-color="#adb5bd" />
                    <stop offset="60%" stop-color="#e9ecef" />
                    <stop offset="100%" stop-color="#6c757d" />
                </linearGradient>

                <!-- Оранжевые дольки справа -->
                <linearGradient id="orangeSlice" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#ff7a00" />
                    <stop offset="100%" stop-color="#e65100" />
                </linearGradient>
            </defs>

            <!-- Внешний металлический кант -->
            <circle cx="50" cy="50" r="48" stroke="url(#metalRim)" stroke-width="4.5" fill="none" />
            <circle cx="50" cy="50" r="44.5" stroke="url(#innerRim)" stroke-width="1.5" fill="#ffffff" />

            <!-- 8 ДОЛЕК -->
            <path d="M 52 45 L 58 16 C 68 18 76 25 81 33 L 57 47 C 55 46 53 45 52 45 Z" fill="url(#orangeSlice)" />
            <path d="M 55 50 L 84 37 C 87 45 87 55 84 63 L 55 50 Z" fill="url(#orangeSlice)" />
            <path d="M 52 55 L 81 67 C 76 75 68 82 58 84 L 52 55 Z" fill="url(#orangeSlice)" />
            <path d="M 48 56 L 54 84 C 47 85 41 84 36 82 L 46 55 C 47 55.5 47.5 56 48 56 Z" fill="url(#orangeSlice)" />

            <path d="M 44 54 L 32 80 C 24 75 19 68 16 59 L 43 51 C 43 52 43.5 53 44 54 Z" fill="url(#metalSlice4)" />
            <path d="M 43 49 L 15 55 C 13 47 15 39 18 32 L 44 46 C 43.3 47 43 48 43 49 Z" fill="url(#metalSlice3)" />
            <path d="M 45 44 L 21 28 C 27 21 35 16 44 15 L 47 43 C 46 43.3 45.4 43.6 45 44 Z"
                fill="url(#metalSlice2)" />
            <path d="M 48 43 L 47 15 C 49 14.8 51 14.8 53 15 L 50 43 C 49.3 43 48.7 43 48 43 Z"
                fill="url(#metalSlice1)" />

            <!-- Центральная перемычка -->
            <circle cx="49" cy="49" r="4.5" fill="#ffffff" />
        </svg>

        <!-- Текстовый заголовок на русском -->
        <div v-if="showText" class="ms-2 lh-1">
            <span class="fw-bold text-white d-block" style="font-size: 1.05rem; letter-spacing: 0.03em;">МАНДАРИН</span>
            <span class="text-uppercase fw-semibold"
                style="font-size: 0.65rem; color: #f37021; letter-spacing: 0.12em;">ПОРТАЛ</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    size: {
        type: [Number, String],
        default: 30,
    },
    showText: {
        type: Boolean,
        default: true,
    },
});
</script>

<style scoped>
.mandarin-brand-wrap {
    user-select: none;
}

/* Оборот на 360 градусов за 1 секунду, затем 4 секунды паузы (полный цикл 5 сек) */
.logo-spin {
    transform-origin: 50% 50%;
    animation: rotateOnceEveryFive 5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes rotateOnceEveryFive {
    0% {
        transform: rotate(0deg);
    }

    20% {
        transform: rotate(360deg);
    }

    100% {
        transform: rotate(360deg);
    }
}
</style>