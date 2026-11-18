// resources/js/composables/useRowStyles.js

export function useRowStyles() {
    /**
     * Проверка: осталось менее суток (<= 24 ч) для основного или финансового статуса
     */
    const isItemUrgent = (item) => {
        // 1. Проверяем основной статус дела (исключаем Архив id=25 и просроченные)
        const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);

        // Менее суток по статусу дела: таймер активен, не просрочен, и режим часов (is_day == 0)
        const isAppUrgent = !isAppArchive && !isAppOverdue && Boolean(
            item.app_timer?.has_timer &&
            (item.app_timer?.is_day === 0 || item.app_timer?.is_day === false) &&
            Number(item.app_timer?.count_hour ?? item.count_hour ?? 0) > 0
        );

        // 2. Проверяем финансовый статус
        const finTitle = String(item.finance_status?.name || item.finance_status_name || item.soglas_title || '').toLowerCase();
        const isFinPaid = finTitle.includes('оплата получена') || finTitle.includes('оплачен');
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);

        const isFinUrgent = !isFinPaid && !isFinOverdue && Boolean(
            item.finance_timer?.has_timer &&
            (item.finance_timer?.is_day === 0 || item.finance_timer?.is_day === false) &&
            Number(item.finance_timer?.count_hour_f ?? item.count_hour_f ?? 0) > 0
        );

        return isAppUrgent || isFinUrgent;
    };

    /**
     * Фоновый тулбар с анимацией бегущих полос при срочности < 24ч
     */
    const getRowBackgroundStyle = (item) => {
        const BLUE_COLOR = 'rgba(37, 99, 235, 0.14)';   // Синий (дни)
        const GREEN_COLOR = 'rgba(22, 163, 74, 0.16)';   // Зеленый (фин. статус / оплата)
        const URGENT_COLOR = 'rgba(245, 158, 11, 0.26)';  // Янтарный для < 24ч
        const PASTEL_PINK = 'rgba(254, 226, 226, 0.45)'; // Фон свободного поля
        const OVERDUE_RED = 'rgba(248, 113, 113, 0.38)'; // Просрочено

        const isAppArchive = Number(item.id_status || item.status_id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);
        const isAppUrgent = !isAppArchive && !isAppOverdue && item.app_timer?.has_timer && (item.app_timer?.is_day === 0 || item.app_timer?.is_day === false);

        let appFill = isAppUrgent ? URGENT_COLOR : BLUE_COLOR;
        let appBg = '';

        if (isAppArchive) {
            appBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
        } else if (isAppOverdue) {
            appBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const appWidth = Math.min(100, Math.max(0, Number(item.app_timer?.percent ?? 0)));
            appBg = `linear-gradient(to right, ${appFill} 0%, ${appFill} ${appWidth}%, ${PASTEL_PINK} ${appWidth}%, ${PASTEL_PINK} 100%)`;
        }

        // Если нет активного фин. статуса — 100% высоты
        const finId = Number(item.finance_status_id || item.id_finance_status || 0);
        if (finId <= 1 && !item.finance_status_name) {
            return { background: `${appBg} top / 100% 100% no-repeat` };
        }

        // Нижняя полоса фин. статуса
        const isFinPaid = finId === 7;
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);
        const isFinUrgent = !isFinPaid && !isFinOverdue && item.finance_timer?.has_timer && (item.finance_timer?.is_day === 0 || item.finance_timer?.is_day === false);

        let finFill = isFinUrgent ? URGENT_COLOR : GREEN_COLOR;
        let finBg = '';

        if (isFinPaid) {
            finBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
        } else if (isFinOverdue) {
            finBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const finWidth = Math.min(100, Math.max(0, Number(item.finance_timer?.percent ?? 0)));
            finBg = `linear-gradient(to right, ${finFill} 0%, ${finFill} ${finWidth}%, ${PASTEL_PINK} ${finWidth}%, ${PASTEL_PINK} 100%)`;
        }

        return {
            background: `
        ${appBg} top / 100% 50% no-repeat,
        ${finBg} bottom / 100% 50% no-repeat
      `,
        };
    };

    return {
        isItemUrgent,
        getRowBackgroundStyle,
    };
}