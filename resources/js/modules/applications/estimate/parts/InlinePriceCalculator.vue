<template>
    <div class="calculator-root-wrapper">
        <!-- Затемняющий оверлей для смартфонов (клик вне окна закрывает калькулятор) -->
        <div v-if="isMobile" class="calc-backdrop" @click="$emit('close')"></div>

        <div ref="calculatorRef" class="calc-box shadow-lg"
            :class="{ 'calc-mobile-sheet': isMobile, 'calc-desktop-popover': !isMobile }" :style="calcPositionStyle"
            @click.stop @mousedown.stop>
            <!-- Шапка калькулятора -->
            <div class="calc-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-white">
                <div class="d-flex align-items-center text-truncate pe-2">
                    <i class="bi bi-calculator text-primary me-2 flex-shrink-0"></i>
                    <span class="calc-title text-truncate fw-bold text-dark" :title="title">{{ title }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-auto text-decoration-none"
                    title="Закрыть (Esc)" @click="$emit('close')">
                    <i class="bi bi-x-lg fs-6"></i>
                </button>
            </div>

            <!-- Дисплей с предыдущей операцией и текущим числом -->
            <div class="calc-display px-3 py-2 bg-light border-bottom text-end">
                <div class="calc-expression font-monospace text-muted small"
                    style="min-height: 16px; font-size: 0.72rem;">
                    {{ expressionText }}
                </div>
                <div
                    class="calc-current-val font-monospace fw-bold text-dark fs-5 d-flex align-items-baseline justify-content-end">
                    <span class="text-truncate">{{ formatNumberDisplay(currentInput) }}</span>
                    <span class="ms-1 fs-6 text-muted font-monospace">₽</span>
                </div>
            </div>

            <!-- Быстрые проценты в 1 клик (+5%, +10%, +15%, +20%, -10%) -->
            <div class="calc-quick-percents d-flex gap-1 p-2 border-bottom bg-white">
                <button v-for="p in [5, 10, 15, 20]" :key="p" type="button" class="btn btn-calc-chip flex-fill"
                    @click.stop="applyPercentDelta(p)">
                    +{{ p }}%
                </button>
                <button type="button" class="btn btn-calc-chip btn-calc-chip-negative flex-fill"
                    @click.stop="applyPercentDelta(-10)">
                    -10%
                </button>
            </div>

            <!-- Цифровая клавиатура -->
            <div class="calc-keyboard p-2 bg-white">
                <div class="calc-grid">
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('7')">7</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('8')">8</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('9')">9</button>
                    <button type="button" class="btn btn-calc-key btn-calc-op" @click.stop="setOperator('/')">/</button>

                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('4')">4</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('5')">5</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('6')">6</button>
                    <button type="button" class="btn btn-calc-key btn-calc-op" @click.stop="setOperator('*')">*</button>

                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('1')">1</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('2')">2</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('3')">3</button>
                    <button type="button" class="btn btn-calc-key btn-calc-op" @click.stop="setOperator('-')">-</button>

                    <button type="button" class="btn btn-calc-key btn-calc-clear" @click.stop="clearDisplay">C</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDigit('0')">0</button>
                    <button type="button" class="btn btn-calc-key" @click.stop="appendDecimal">.</button>
                    <button type="button" class="btn btn-calc-key btn-calc-op" @click.stop="setOperator('+')">+</button>
                </div>
            </div>

            <!-- Футер: Сброс и фирменная мандариновая кнопка Применить -->
            <div class="calc-footer d-flex gap-2 p-2 border-top bg-light">
                <button type="button" class="btn btn-sm calc-btn-reset flex-fill py-1.5" @click.stop="resetToInitial">
                    Сброс
                </button>
                <button type="button"
                    class="btn btn-sm calc-btn-apply flex-fill py-1.5 d-flex align-items-center justify-content-center"
                    @click.stop="submitResult">
                    <i class="bi bi-check2 me-1 fs-6"></i>
                    <span>Применить</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    initialValue: {
        type: [Number, String],
        default: 0,
    },
    title: {
        type: String,
        default: 'Калькулятор цены',
    },
    targetRect: {
        type: Object,
        default: () => ({ top: 0, left: 0, bottom: 0, right: 0 }),
    },
    targetElement: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['apply', 'close']);

const calculatorRef = ref(null);
const windowWidth = ref(window.innerWidth);

const isMobile = computed(() => windowWidth.value < 768);

const handleResize = () => {
    windowWidth.value = window.innerWidth;
};

// Безопасный парсинг любого строкового или числового формата
const parseNumber = (val) => {
    if (val === null || val === undefined || val === '') return 0;
    const clean = String(val)
        .replace(/\s+/g, '')
        .replace(/\u00a0/g, '')
        .replace(',', '.');
    const num = parseFloat(clean);
    return isNaN(num) ? 0 : num;
};

const currentInput = ref(String(parseNumber(props.initialValue)));
const previousValue = ref(null);
const currentOperator = ref(null);
const isTypingNewNumber = ref(true);

// Сброс и подхват свежего числа при любой смене ячейки
watch(
    () => [props.initialValue, props.targetRect],
    ([newVal]) => {
        currentInput.value = String(parseNumber(newVal));
        previousValue.value = null;
        currentOperator.value = null;
        isTypingNewNumber.value = true;
    },
    { deep: true, immediate: true }
);

const expressionText = computed(() => {
    if (previousValue.value !== null && currentOperator.value) {
        const prevFormatted = formatNumberDisplay(previousValue.value);
        const currPart = isTypingNewNumber.value ? '' : ` ${formatNumberDisplay(currentInput.value)}`;
        return `${prevFormatted} ${currentOperator.value}${currPart}`;
    }
    return '';
});

// ТОЧНЫЙ РАСЧЕТ ПОЛОЖЕНИЯ:
// На смартфонах — фиксированная нижняя шторка (Bottom Sheet).
// На десктопе — открывается СБОКУ (справа/слева), никогда не перекрывая саму строку!
const calcPositionStyle = computed(() => {
    if (isMobile.value) {
        return {}; // На мобильных действует Bottom Sheet снизу
    }

    // Реальные внешние габариты калькулятора с тенями
    const CALC_WIDTH = 226;
    const CALC_HEIGHT = 300;
    const GAP = 10;
    const VIEWPORT_PADDING = 14;

    const rect = props.targetRect || { top: 0, left: 0, bottom: 0, right: 0 };
    const vh = window.innerHeight;
    const vw = window.innerWidth;

    let left = 0;
    let top = 0;

    // 1. ГОРИЗОНТАЛЬ (X):
    // Проверяем, помещается ли справа от ячейки
    if (rect.right + GAP + CALC_WIDTH <= vw - VIEWPORT_PADDING) {
        left = rect.right + GAP;
    }
    // Если справа не помещается — открываем слева от ячейки
    else if (rect.left - GAP - CALC_WIDTH >= VIEWPORT_PADDING) {
        left = rect.left - GAP - CALC_WIDTH;
    }
    // Если колонка шире экрана — центрируем по ячейке
    else {
        left = Math.max(VIEWPORT_PADDING, Math.min(vw - CALC_WIDTH - VIEWPORT_PADDING, rect.left));
    }

    // 2. ВЕРТИКАЛЬ (Y) — КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ:
    // Если ячейка в нижней половине экрана (как на скриншоте строка 55,31):
    // Прижимаем калькулятор снизу к нижнему срезу ячейки, чтобы он рос ВВЕРХ!
    const isLowerHalf = rect.top > vh / 2;

    if (isLowerHalf) {
        // Выравниваем нижний край калькулятора по нижнему краю ячейки
        top = rect.bottom - CALC_HEIGHT;
    } else {
        // В верхней половине экрана выравниваем по верхнему краю ячейки
        top = rect.top;
    }

    // 3. ЖЕСТКОЕ ОГРАНИЧЕНИЕ ПО ВЫСОТЕ ЭКРАНА (никогда не уходит под срез!):
    // Верхняя граница: не выше 14px от верхнего края
    // Нижняя граница: строго не ниже чем (vh - CALC_HEIGHT - 14px)
    const maxAllowedTop = vh - CALC_HEIGHT - VIEWPORT_PADDING;
    top = Math.max(VIEWPORT_PADDING, Math.min(maxAllowedTop, top));

    return {
        position: 'fixed',
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
        zIndex: 9999999,
    };
});

const appendDigit = (digit) => {
    if (isTypingNewNumber.value) {
        currentInput.value = digit;
        isTypingNewNumber.value = false;
    } else {
        if (currentInput.value === '0') {
            currentInput.value = digit;
        } else {
            currentInput.value += digit;
        }
    }
};

const appendDecimal = () => {
    if (isTypingNewNumber.value) {
        currentInput.value = '0.';
        isTypingNewNumber.value = false;
    } else if (!currentInput.value.includes('.')) {
        currentInput.value += '.';
    }
};

const clearDisplay = () => {
    currentInput.value = '0';
    isTypingNewNumber.value = true;
};

const resetToInitial = () => {
    currentInput.value = String(parseNumber(props.initialValue));
    previousValue.value = null;
    currentOperator.value = null;
    isTypingNewNumber.value = true;
};

const setOperator = (op) => {
    if (previousValue.value !== null && currentOperator.value && !isTypingNewNumber.value) {
        calculateIntermediate();
    } else {
        previousValue.value = parseNumber(currentInput.value);
    }
    currentOperator.value = op;
    isTypingNewNumber.value = true;
};

const calculateIntermediate = () => {
    if (previousValue.value === null || !currentOperator.value) return;
    const prev = previousValue.value;
    const curr = parseNumber(currentInput.value);
    let res = curr;

    switch (currentOperator.value) {
        case '+': res = prev + curr; break;
        case '-': res = prev - curr; break;
        case '*': res = prev * curr; break;
        case '/': res = curr !== 0 ? prev / curr : 0; break;
    }

    res = Math.round(res * 100) / 100;
    currentInput.value = String(res);
    previousValue.value = res;
};

const applyPercentDelta = (percent) => {
    const current = parseNumber(currentInput.value);
    const factor = 1 + percent / 100;
    const res = Math.round(current * factor * 100) / 100;
    currentInput.value = String(res);
    isTypingNewNumber.value = true;
};

const submitResult = () => {
    if (previousValue.value !== null && currentOperator.value && !isTypingNewNumber.value) {
        calculateIntermediate();
    }
    const finalVal = parseNumber(currentInput.value);
    emit('apply', Math.round(finalVal * 100) / 100);
    emit('close');
};

const formatNumberDisplay = (val) => {
    const n = parseNumber(val);
    return n.toLocaleString('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
        emit('close');
    } else if (e.key === 'Enter') {
        e.preventDefault();
        submitResult();
    } else if (e.key >= '0' && e.key <= '9') {
        appendDigit(e.key);
    } else if (e.key === '.' || e.key === ',') {
        appendDecimal();
    } else if (['+', '-', '*', '/'].includes(e.key)) {
        setOperator(e.key);
    }
};

onMounted(() => {
    window.addEventListener('resize', handleResize);
    window.addEventListener('keydown', handleKeyDown);

    // На смартфонах плавно центрируем строку, чтобы она оставалась над шторкой
    if (isMobile.value && props.targetElement) {
        setTimeout(() => {
            props.targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 120);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
/* ДЕСКТОПНЫЙ POPOVER (ОТКРЫВАЕТСЯ СБОКУ) */
.calc-desktop-popover {
    position: fixed !important;
    z-index: 9999999 !important;
    width: 226px;
    max-height: calc(100vh - 28px);
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 12px 30px -4px rgba(0, 0, 0, 0.28), 0 4px 6px -2px rgba(0, 0, 0, 0.08) !important;
    user-select: none;
    animation: calcPopIn 0.14s ease-out;
}

@keyframes calcPopIn {
    from {
        opacity: 0;
        transform: scale(0.96);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* МОБИЛЬНЫЙ ВЫЕЗЖАЮЩИЙ BOTTOM SHEET */
.calc-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 9999990 !important;
    backdrop-filter: blur(1.5px);
}

.calc-mobile-sheet {
    position: fixed !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
    background: #ffffff !important;
    border-radius: 16px 16px 0 0 !important;
    box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.3) !important;
    z-index: 9999999 !important;
    animation: slideUpSheet 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    padding-bottom: env(safe-area-inset-bottom, 12px);
}

@keyframes slideUpSheet {
    from {
        transform: translateY(100%);
    }

    to {
        transform: translateY(0);
    }
}

.calc-title {
    font-size: 0.76rem;
    letter-spacing: -0.01em;
}

.calc-display {
    background-color: #f8fafc;
}

/* БЫСТРЫЕ ПРОЦЕНТЫ */
.btn-calc-chip {
    font-size: 0.68rem;
    padding: 3px 2px;
    background-color: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-weight: 700;
    font-family: monospace;
    color: #334155;
    transition: all 0.12s ease;
}

.btn-calc-chip:hover {
    background-color: #e2e8f0;
    color: #0284c7;
}

.btn-calc-chip-negative {
    color: #dc2626 !important;
    background-color: #fef2f2 !important;
    border-color: #fecaca !important;
}

.btn-calc-chip-negative:hover {
    background-color: #fee2e2 !important;
}

/* КЛАВИАТУРА */
.calc-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4px;
}

.btn-calc-key {
    height: 28px;
    padding: 0;
    font-size: 0.85rem;
    font-weight: 700;
    font-family: monospace;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.1s ease;
}

.btn-calc-key:hover {
    background-color: #f1f5f9;
}

.btn-calc-key:active {
    background-color: #e2e8f0;
}

.btn-calc-op {
    background-color: #f0f9ff;
    border-color: #bae6fd;
    color: #0284c7;
    font-weight: 800;
}

.btn-calc-op:hover {
    background-color: #e0f2fe;
}

.btn-calc-clear {
    color: #dc2626;
    background-color: #fef2f2;
    border-color: #fecaca;
}

.btn-calc-clear:hover {
    background-color: #fee2e2;
}

@media (max-width: 767.98px) {
    .btn-calc-key {
        height: 44px;
        /* Удобный тач-таргет для пальца */
        font-size: 1.05rem;
    }
}

/* КНОПКИ ДЕЙСТВИЯ */
.calc-btn-reset {
    background-color: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
    font-weight: 600;
    border-radius: 4px;
    font-size: 0.78rem;
}

.calc-btn-reset:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}

/* Фирменная мандариновая кнопка Применить */
.calc-btn-apply {
    background: linear-gradient(135deg, #f25c05 0%, #ea580c 100%) !important;
    border: 1px solid #c2410c !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 0.78rem !important;
    border-radius: 4px !important;
    box-shadow: 0 2px 4px rgba(242, 92, 5, 0.28) !important;
    transition: all 0.15s ease-in-out;
}

.calc-btn-apply:hover {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%) !important;
    box-shadow: 0 4px 8px rgba(242, 92, 5, 0.38) !important;
    transform: translateY(-1px);
}

.calc-btn-apply:active {
    transform: translateY(0);
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2) !important;
}

.calc-footer {
    flex-shrink: 0;
    /* Футер и кнопка Применить никогда не сжимаются и не обрезаются */
}
</style>