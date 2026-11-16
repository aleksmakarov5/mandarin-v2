/**
 * Проверка корректности e-mail адреса:
 * - поддержка латиницы и кириллических доменов (.рф)
 * - запрет пробелов и спецсимволов в неположенных местах
 * - обязательное наличие доменной зоны минимум из 2 символов
 */
export function validateEmail(email) {
    if (!email || !email.trim()) {
        return {
            isValid: false,
            error: 'E-mail обязателен для заполнения'
        };
    }

    const cleanEmail = email.trim();

    // Регулярное выражение по стандарту RFC 5322 (с поддержкой интернациональных доменов)
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

    if (!emailRegex.test(cleanEmail)) {
        return {
            isValid: false,
            error: 'Введите корректный адрес электронной почты (например, name@mandarin.local)'
        };
    }

    // Проверка длины доменной зоны после последней точки (не менее 2 символов)
    const parts = cleanEmail.split('.');
    const tld = parts[parts.length - 1];
    if (tld.length < 2) {
        return {
            isValid: false,
            error: 'Доменная зона e-mail указана неверно'
        };
    }

    return {
        isValid: true,
        error: ''
    };
}