/**
 * Форматирует строку цифр в формат +7 (XXX) XXX-XX-XX
 */
export function formatPhone(val) {
    if (!val) return '';
    let digits = String(val).replace(/\D/g, '');

    // Если начали ввод с 8 или 7 — заменяем на 7
    if (digits.startsWith('8') || digits.startsWith('7')) {
        digits = '7' + digits.substring(1);
    } else if (digits.length > 0 && !digits.startsWith('7')) {
        digits = '7' + digits;
    }

    // Ограничиваем 11 цифрами (код страны + 10 цифр)
    digits = digits.substring(0, 11);

    if (!digits) return '';

    let res = '+7';
    if (digits.length > 1) {
        res += ' (' + digits.substring(1, 4);
    }
    if (digits.length >= 4) {
        res += ') ';
    }
    if (digits.length >= 5) {
        res += digits.substring(4, 7);
    }
    if (digits.length >= 8) {
        res += '-' + digits.substring(7, 9);
    }
    if (digits.length >= 10) {
        res += '-' + digits.substring(9, 11);
    }

    return res;
}

/**
 * Vue директива v-mask-phone
 */
export const maskPhoneDirective = {
    mounted(el) {
        const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
        if (!input) return;

        const handler = (e) => {
            const raw = input.value;
            const formatted = formatPhone(raw);
            if (raw !== formatted) {
                input.value = formatted;
                // Генерируем событие input, чтобы v-model обновился
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };

        input.addEventListener('input', handler);
        input._maskPhoneHandler = handler;

        // Форматируем стартовое значение, если оно есть
        if (input.value) {
            input.value = formatPhone(input.value);
        }
    },
    unmounted(el) {
        const input = el.tagName === 'INPUT' ? el : el.querySelector('input');
        if (input && input._maskPhoneHandler) {
            input.removeEventListener('input', input._maskPhoneHandler);
            delete input._maskPhoneHandler;
        }
    },
};