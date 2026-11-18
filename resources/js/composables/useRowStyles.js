export function useRowStyles() {
    const getFinanceStatusTitle = (item) => {
        const raw =
            item.finance_status_name ||
            item.finance_status?.name ||
            item.financeStatus?.name ||
            item.fin_status?.name ||
            item.FinStatusName ||
            item.soglas_title ||
            '';

        const trimmed = String(raw).trim();
        if (!trimmed || trimmed.toLowerCase() === 'не определен' || trimmed.toLowerCase() === 'не определено') {
            return '';
        }
        return trimmed;
    };

    const hasActiveFinanceStatus = (item) => {
        const finId = Number(
            item.finance_status_id ||
            item.finance_statuses_id ||
            item.id_finance_status ||
            item.finance_status?.id ||
            item.financeStatus?.id ||
            0
        );
        const title = getFinanceStatusTitle(item);
        if (!title) return false;
        if (finId === 1) return false;
        return true;
    };

    const isItemUrgent = (item) => {
        const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);
        const isAppUrgent = !isAppArchive && !isAppOverdue && Boolean(item.app_timer?.has_timer && (item.app_timer?.is_day === false || item.app_timer?.is_day === 0));

        const finTitle = getFinanceStatusTitle(item).toLowerCase();
        const isFinPaid = finTitle.includes('оплата получена') || finTitle.includes('оплачен');
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);
        const isFinUrgent = hasActiveFinanceStatus(item) && !isFinPaid && !isFinOverdue && Boolean(item.finance_timer?.has_timer && (item.finance_timer?.is_day === false || item.finance_timer?.is_day === 0));

        return isAppUrgent || isFinUrgent;
    };

    const getRowBackgroundStyle = (item) => {
        // ВМЕСТО ЖЕЛТОГО — КРАСИВЫЙ НЕЖНО-ГОЛУБОЙ
        const BLUE_COLOR = 'rgba(56, 189, 248, 0.22)';   // Пастельно-голубой
        const GREEN_COLOR = 'rgba(34, 197, 94, 0.18)';    // Зеленый (фин. статус / оплата / архив)
        const PASTEL_PINK = 'rgba(254, 226, 226, 0.45)';  // Пастельно-розовый справа
        const OVERDUE_RED = 'rgba(248, 113, 113, 0.38)';  // Красный (просрочено)

        // 1. Верхняя полоса: Статус дела (голубой)
        const isAppArchive = Number(item.id_status || item.status_id || item.status?.id || 0) === 25;
        const isAppOverdue = !isAppArchive && Boolean(item.app_timer?.is_overdue || item.is_overdue);

        let appBg = '';
        if (isAppArchive) {
            appBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
        } else if (isAppOverdue) {
            appBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const appWidth = Math.min(100, Math.max(0, Number(item.app_timer?.percent ?? 0)));
            appBg = `linear-gradient(to right, ${BLUE_COLOR} 0%, ${BLUE_COLOR} ${appWidth}%, ${PASTEL_PINK} ${appWidth}%, ${PASTEL_PINK} 100%)`;
        }

        // 2. Если фин. статус отсутствует — статус дела занимает 100% высоты строки
        const hasFin = hasActiveFinanceStatus(item);
        if (!hasFin) {
            return {
                background: `${appBg} top / 100% 100% no-repeat`,
            };
        }

        // 3. Нижняя полоса: Фин. статус (зеленый, 50% высоты)
        const finTitle = getFinanceStatusTitle(item).toLowerCase();
        const finId = Number(item.finance_status_id || item.finance_statuses_id || 0);
        const isFinPaid = finId === 7 || finTitle.includes('оплата получена') || finTitle.includes('оплачен');
        const isFinOverdue = !isFinPaid && Boolean(item.finance_timer?.is_overdue || item.soglas_overdue);

        let finBg = '';
        if (isFinPaid) {
            finBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} 100%)`;
        } else if (isFinOverdue) {
            finBg = `linear-gradient(to right, ${OVERDUE_RED} 0%, ${OVERDUE_RED} 100%)`;
        } else {
            const finWidth = Math.min(100, Math.max(0, Number(item.finance_timer?.percent ?? 0)));
            finBg = `linear-gradient(to right, ${GREEN_COLOR} 0%, ${GREEN_COLOR} ${finWidth}%, ${PASTEL_PINK} ${finWidth}%, ${PASTEL_PINK} 100%)`;
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