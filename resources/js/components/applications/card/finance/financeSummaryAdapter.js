/**
 * Принимает текущий контракт ApplicationFinanceService.
 *
 * Деньги уже переданы в копейках.
 * Ставки уже переданы в сотых долях процента.
 * Дополнительного преобразования единиц здесь нет.
 */
export function normalizeSummary(payload) {
    const data = payload?.data;

    if (
        !data
        || typeof data !== 'object'
        || Array.isArray(data)
        || data.money_unit !== 'kopeck'
        || !data.approved
        || !data.planned
        || !data.planned_expenses
        || !data.actual_expenses
        || !data.cash
    ) {
        return null;
    }

    return {
        summary: data,
        legacy: false,
    };
}