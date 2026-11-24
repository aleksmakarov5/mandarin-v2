/*
 * Существующий контракт:
 *
 * деньги — целые копейки;
 * ставки — сотые доли процента: 500 = 5%.
 *
 * Здесь только форматирование. Финансовых расчётов нет.
 */

const moneyFormatter = new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const percentFormatter = new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: 2,
});

const timeFormatter = new Intl.DateTimeFormat('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
});

export function isAvailable(value) {
    return (
        value !== null
        && value !== undefined
        && value !== ''
        && typeof value !== 'boolean'
        && Number.isSafeInteger(Number(value))
    );
}

export function money(value, withCurrency = true) {
    if (!isAvailable(value)) return '—';

    const formatted = moneyFormatter.format(Number(value) / 100);

    return withCurrency ? `${formatted} ₽` : formatted;
}

export function percent(value) {
    if (!isAvailable(value)) return '—';

    return percentFormatter.format(Number(value) / 100);
}

export function amountClass(value) {
    if (!isAvailable(value)) return 'amount-unavailable';

    return Number(value) < 0
        ? 'amount-negative'
        : 'amount-positive';
}

export function date(value) {
    if (!value) return '—';

    const match = String(value).match(
        /^(\d{4})-(\d{2})-(\d{2})/
    );

    return match
        ? `${match[3]}.${match[2]}.${match[1]}`
        : '—';
}

export function time(value) {
    if (!value) return '—';

    const parsed = value instanceof Date
        ? value
        : new Date(value);

    if (!Number.isFinite(parsed.getTime())) {
        return '—';
    }

    return timeFormatter.format(parsed);
}