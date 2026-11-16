/**
 * Корпоративная политика паролей Mandarin Portal:
 * - Минимум 8 символов
 * - Заглавная буква (A-Z / А-Я)
 * - Строчная буква (a-z / а-я)
 * - Цифра (0-9)
 * - Специальный символ (!@#$%^&* и др.)
 */

// Регулярное выражение спецсимволов, полностью совместимое с Laravel Password::symbols()
const SPECIAL_CHARS_REGEX = /[!@#$%^&*()_+\-=[\]{}|;':",.<>?/`~\\№]/;

export function validatePasswordStrength(password = '') {
    const pwd = String(password || '');

    const rules = {
        hasMinLength: pwd.length >= 8,
        hasLowerCase: /[a-zа-яё]/u.test(pwd),
        hasUpperCase: /[A-ZА-ЯЁ]/u.test(pwd),
        hasNumber: /[0-9]/.test(pwd),
        hasSpecialChar: SPECIAL_CHARS_REGEX.test(pwd),
    };

    const errors = [];

    if (!rules.hasMinLength) {
        errors.push('Минимум 8 символов');
    }
    if (!rules.hasLowerCase) {
        errors.push('Минимум одна строчная буква');
    }
    if (!rules.hasUpperCase) {
        errors.push('Минимум одна заглавная буква');
    }
    if (!rules.hasNumber) {
        errors.push('Минимум одна цифра');
    }
    if (!rules.hasSpecialChar) {
        errors.push('Минимум один спецсимвол (!, @, #, $, %, ^, &, * и др.)');
    }

    const passedCount = Object.values(rules).filter(Boolean).length;

    return {
        isValid: errors.length === 0,
        errors,
        rules,
        // Прогресс выполнения требований: от 0 до 100%
        score: (passedCount / 5) * 100,
    };
}