/**
 * Composable для единого расчета стилей строк реестра и фонового тулбара
 */
export function useRowStyles() {
    /**
     * Получение понятного наименования финансового статуса
     */
    const getFinanceStatusTitle = (item) => {
        const title =
            item.finance_status?.name ||
            item.financeStatus?.name ||
            item.fin_status?.name ||
            item.FinStatusName ||
            item.finance_status_name ||
            item.soglas_title ||
            '';

        const clean = String(title).trim();
        if (!clean || clean.toLowerCase() === 'не определен' || clean.toLowerCase() === 'не определено') {
            return '';
        }
        return clean;
    };

    /**
     * Проверка наличия активного финансового статуса (строго id > 1 и не «Не определен»)
     */
    const hasActiveFinanceStatus = (item) => {
        const finId = Number(
            item.finance_status_id ||
            item.finance_statuses_id ||
            item.id_finance_status ||
            item.finance_status?.id ||
            item.financeStatus?.id ||
            0
        );

        if (finId <= 1) return false;

        const title = getFinanceStatusTitle(item);
        return Boolean(title);
    };

    /**
     * Проверка: осталось ли менее 24 часов (срочные заявки)
     */
    const isItemUrgent = (item) => {
        const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);

        // Основной статус срочный (в часах и > 0)
        const isAppUrgent =
            !isAppArchive &&
            !isAppOverdue &&
            Boolean(item.app_timer?.has_timer) &&
            item.app_timer?.is_day === false &&
            Number(item.app_timer?.count_hour ?? item.count_hour ?? 99) <= 24 &&
            Number(item.app_timer?.count_hour ?? item.count_hour ?? 0) > 0;

        // Фин статус срочный
        const hasFin = hasActiveFinanceStatus(item);
        const finTitle = getFinanceStatusTitle(item).toLowerCase();
        const isFinPaid = finTitle.includes('оплата получена') || finTitle.includes('оплачен');
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);

        const isFinUrgent =
            hasFin &&
            !isFinPaid &&
            !isFinOverdue &&
            Boolean(item.finance_timer?.has_timer) &&
            item.finance_timer?.is_day === false &&
            Number(item.finance_timer?.count_hour_f ?? item.count_hour_f ?? 99) <= 24 &&
            Number(item.finance_timer?.count_hour_f ?? item.count_hour_f ?? 0) > 0;

        return isAppUrgent || isFinUrgent;
    };

    /**
     * Расчет CSS-градиента фонового тулбара строки
     */
    const getRowBackgroundStyle = (item) => {
        // Цветовая палитра:
        const SKY_BLUE = 'rgba(56, 189, 248, 0.22)';  // Чистый небесно-голубой (основной статус)
        const VIOLET_INDIGO = 'rgba(129, 140, 248, 0.24)'; // Благородный фиалково-индиговый (фин. статус)
        const PASTEL_PINK = 'rgba(254, 226, 226, 0.45)'; // Нежный пастельно-розовый (свободное поле справа)
        const OVERDUE_RED = 'rgba(248, 113, 113, 0.38)'; // Красный цвет просрочки
        const SUCCESS_GREEN = 'rgba(34, 197, 94, 0.22)'; // Зеленый цвет архива / полной оплаты

        // 1. ВЕРХНЯЯ ПОЛОСА: СТАТУС ДЕЛА
        const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);

        let appBg = '';
        if (isAppArchive) {
            appBg = `linear-gradient(to right, ${SUCCESS_GREEN} 0%, ${SUCCESS_GREEN} 100%)`;
        } else if (isAppOverdue) {
            appBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const appWidth = Math.min(100, Math.max(0, Number(item.app_timer?.percent ?? 0)));
            appBg = `linear-gradient(to right, ${SKY_BLUE} 0%, ${SKY_BLUE} ${appWidth}%, ${PASTEL_PINK} ${appWidth}%, ${PASTEL_PINK} 100%)`;
        }

        // 2. ЕСЛИ ФИН СТАТУСА НЕТ — ПОЛОСА СТАТУСА ДЕЛА ЗАНИМАЕТ 100% ВЫСОТЫ СТРОКИ
        const hasFin = hasActiveFinanceStatus(item);
        if (!hasFin) {
            return {
                background: `${appBg} top / 100% 100% no-repeat`,
            };
        }

        // 3. НИЖНЯЯ ПОЛОСА: ФИНАНСОВЫЙ СТАТУС (50% высоты)
        const finTitle = getFinanceStatusTitle(item).toLowerCase();
        const finId = Number(item.finance_status_id || item.finance_statuses_id || item.id_finance_status || 0);
        const isFinPaid = finId === 7 || finTitle.includes('оплата получена') || finTitle.includes('оплачен');
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);

        let finBg = '';
        if (isFinPaid) {
            finBg = `linear-gradient(to right, ${SUCCESS_GREEN} 0%, ${SUCCESS_GREEN} 100%)`;
        } else if (isFinOverdue) {
            finBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const finWidth = Math.min(100, Math.max(0, Number(item.finance_timer?.percent ?? 0)));
            finBg = `linear-gradient(to right, ${VIOLET_INDIGO} 0%, ${VIOLET_INDIGO} ${finWidth}%, ${PASTEL_PINK} ${finWidth}%, ${PASTEL_PINK} 100%)`;
        }

        return {
            background: `
        ${appBg} top / 100% 50% no-repeat,
        ${finBg} bottom / 100% 50% no-repeat
      `,
        };
    };

    return {
        getFinanceStatusTitle,
        hasActiveFinanceStatus,
        isItemUrgent,
        getRowBackgroundStyle,
    };
}