import {
    computed,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';

import axios from 'axios';

import { normalizeSummary } from './financeSummaryAdapter';
import { time } from './financeFormatters';

export function useApplicationFinance(getApplicationId) {
    // Финансовая сводка
    const summary = ref(null);
    const warnings = ref([]);
    const loading = ref(false);
    const errorMessage = ref('');
    const updatedAt = ref(null);

    // Сохранение режима НДС
    const vatSaving = ref(false);
    const vatError = ref('');

    // Сохранение согласованной суммы
    const approvedSaving = ref(false);
    const approvedError = ref('');

    // Сохранение ДДС
    const cashSaving = ref(false);
    const cashError = ref('');

    /*
     * null — разрешение ещё не получено;
     * true — сервер явно разрешил просмотр;
     * false — сервер отказал.
     */
    const viewPermission = ref(null);

    let summaryController = null;
    let vatController = null;
    let approvedController = null;
    let cashController = null;

    /*
     * При смене дела или уничтожении компонента увеличивается.
     * Ответы запросов предыдущего поколения не применяются.
     */
    let generation = 0;

    const validApplicationId = computed(() => {
        const id = Number(getApplicationId());

        return Number.isSafeInteger(id) && id > 0
            ? id
            : null;
    });

    const canViewFinance = computed(() => {
        return viewPermission.value === true;
    });

    const busy = computed(() => {
        return loading.value
            || vatSaving.value
            || approvedSaving.value
            || cashSaving.value;
    });

    const canToggleVat = computed(() => {
        const rate = summary.value?.approved?.vat_rate;
        const mode = summary.value?.approved?.vat_mode;

        return canViewFinance.value
            && summary.value?.permissions?.edit_vat === true
            && rate !== null
            && rate !== undefined
            && rate !== ''
            && Number.isFinite(Number(rate))
            && Number(rate) > 0
            && ['added', 'included'].includes(mode)
            && !busy.value;
    });

    const vatButtonTitle = computed(() => {
        const rate = summary.value?.approved?.vat_rate;
        const mode = summary.value?.approved?.vat_mode;

        if (
            rate === null
            || rate === undefined
            || rate === ''
        ) {
            return 'У юридического лица не настроена ставка НДС';
        }

        if (Number(rate) === 0) {
            return 'Юридическое лицо работает без НДС';
        }

        if (!['added', 'included'].includes(mode)) {
            return 'Не настроен режим НДС дела';
        }

        return mode === 'added'
            ? 'Переключить на «Сумма включая НДС»'
            : 'Переключить на «Сумма + НДС»';
    });

    const updatedAtLabel = computed(() => {
        return updatedAt.value
            ? time(updatedAt.value)
            : 'Пересчитать';
    });

    const refreshTitle = computed(() => {
        const lastUpdate = updatedAt.value
            ? `Последний успешный расчёт: ${time(updatedAt.value)}. `
            : '';

        return `${lastUpdate}Получить свежие показатели без перезагрузки страницы`;
    });

    function isCurrent(requestGeneration) {
        return requestGeneration === generation;
    }

    function clearSummary() {
        summary.value = null;
        warnings.value = [];
        updatedAt.value = null;
    }

    function clearMutationErrors() {
        vatError.value = '';
        approvedError.value = '';
        cashError.value = '';
    }

    /**
     * Принимаем только согласованный ответ API.
     *
     * normalizeSummary не должен повторно переводить
     * текущую сводку из рублей в копейки:
     * сервер уже возвращает money_unit = kopeck.
     */
    function acceptResponse(payload) {
        const normalized = normalizeSummary(payload);

        if (!normalized) {
            throw new Error('FINANCE_CONTRACT_ERROR');
        }

        const data = normalized.summary;
        const permission = data.permissions?.view_finance;

        if (permission === false) {
            viewPermission.value = false;
            clearSummary();
            clearMutationErrors();

            throw new Error('FINANCE_ACCESS_DENIED');
        }

        if (permission !== true) {
            viewPermission.value = null;
            clearSummary();
            clearMutationErrors();

            throw new Error('FINANCE_PERMISSION_MISSING');
        }

        viewPermission.value = true;
        summary.value = data;

        const serverWarnings = Array.isArray(payload?.warnings)
            ? payload.warnings.filter(
                item => typeof item === 'string'
            )
            : [];

        const legacyWarnings = normalized.legacy
            ? [
                'Показана базовая сводка. Полный финансовый расчёт ещё не подключён.',
            ]
            : [];

        warnings.value = [
            ...new Set([
                ...serverWarnings,
                ...legacyWarnings,
            ]),
        ];

        updatedAt.value = new Date();
        errorMessage.value = '';

        clearMutationErrors();
    }

    /**
     * Ошибки загрузки полной сводки.
     *
     * 403 при GET означает отсутствие права просмотра:
     * убираем ранее загруженные финансовые данные.
     */
    function handleLoadError(error) {
        const status = error.response?.status;

        if (
            status === 403
            || error.message === 'FINANCE_ACCESS_DENIED'
        ) {
            viewPermission.value = false;

            clearSummary();
            clearMutationErrors();

            // Отсутствие доступа не показываем как сбой интерфейса.
            errorMessage.value = '';

            return;
        }

        if ([401, 404].includes(status)) {
            viewPermission.value = null;

            clearSummary();
            clearMutationErrors();
        }

        if (status === 401) {
            errorMessage.value = 'Сессия завершена. Войдите заново.';
        } else if (status === 404) {
            errorMessage.value =
                'Дело или маршрут финансовой сводки не найден.';
        } else if (error.message === 'FINANCE_PERMISSION_MISSING') {
            errorMessage.value =
                'Сервер не передал разрешение на просмотр финансов.';
        } else if (error.message === 'FINANCE_CONTRACT_ERROR') {
            errorMessage.value =
                'Ответ сервера не соответствует формату финансовой сводки.';
        } else {
            errorMessage.value =
                'Не удалось пересчитать показатели. Повторите запрос.';
        }
    }

    function getRequestError(error, fallback) {
        const validationErrors = error.response?.data?.errors;

        const firstValidationError = (
            validationErrors
            && typeof validationErrors === 'object'
        )
            ? Object.values(validationErrors)
                .flat()
                .find(message => typeof message === 'string')
            : null;

        if (firstValidationError) {
            return firstValidationError;
        }

        if (error.message === 'FINANCE_CONTRACT_ERROR') {
            return (
                'Сервер вернул неподдерживаемый формат сводки. '
                + 'Обновите показатели перед повторным сохранением.'
            );
        }

        const serverMessage = error.response?.data?.message;

        if (typeof serverMessage === 'string' && serverMessage) {
            return serverMessage;
        }

        return fallback;
    }

    /**
     * GET свежей сводки.
     *
     * Вызывается при открытии дела и по кнопке.
     * Изменения запчастей и работ автоматически его не запускают.
     */
    async function loadSummary() {
        if (!validApplicationId.value || busy.value) {
            return;
        }

        const applicationId = validApplicationId.value;
        const requestGeneration = generation;
        const controller = new AbortController();

        summaryController = controller;

        loading.value = true;
        errorMessage.value = '';

        clearMutationErrors();

        try {
            const response = await axios.get(
                `/api/applications/${applicationId}/finance/summary`,
                {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (!isCurrent(requestGeneration)) return;

            acceptResponse(response.data);
        } catch (error) {
            if (!isCurrent(requestGeneration)) return;
            if (error.code === 'ERR_CANCELED') return;

            handleLoadError(error);

            if (viewPermission.value !== false) {
                console.error('Ошибка финансовой сводки:', error);
            }
        } finally {
            if (isCurrent(requestGeneration)) {
                loading.value = false;
                summaryController = null;
            }
        }
    }

    /**
     * Общий обработчик финансовых PATCH-запросов.
     *
     * Суммы передаются без преобразования единиц:
     * API принимает целые копейки.
     *
     * Возвращает true только после успешного сохранения
     * и применения полной сводки от сервера.
     */
    async function saveMutation(type, payload) {
        const configurations = {
            vat: {
                permission: 'edit_vat',
                endpoint: 'vat-mode',
                saving: vatSaving,
                error: vatError,
                fallback: 'Не удалось изменить режим НДС.',
            },

            approved: {
                permission: 'edit_approved',
                endpoint: 'approved-sum',
                saving: approvedSaving,
                error: approvedError,
                fallback: 'Сумма не сохранена. Повторите попытку.',
            },

            cash: {
                permission: 'edit_cash',
                endpoint: 'cash',
                saving: cashSaving,
                error: cashError,
                fallback: 'Не удалось сохранить ДДС.',
            },
        };

        const config = configurations[type];

        if (!config) return false;

        if (
            !canViewFinance.value
            || !validApplicationId.value
            || summary.value?.permissions?.[config.permission] !== true
            || busy.value
        ) {
            return false;
        }

        const applicationId = validApplicationId.value;
        const requestGeneration = generation;
        const controller = new AbortController();

        if (type === 'vat') {
            vatController = controller;
        } else if (type === 'approved') {
            approvedController = controller;
        } else {
            cashController = controller;
        }

        config.saving.value = true;
        config.error.value = '';

        try {
            const response = await axios.patch(
                `/api/applications/${applicationId}/finance/${config.endpoint}`,
                payload,
                {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            if (!isCurrent(requestGeneration)) {
                return false;
            }

            // Не подменяем ответ сервера локальными расчётами.
            acceptResponse(response.data);

            return true;
        } catch (error) {
            if (!isCurrent(requestGeneration)) {
                return false;
            }

            if (error.code === 'ERR_CANCELED') {
                return false;
            }

            const status = error.response?.status;

            if (
                error.message === 'FINANCE_ACCESS_DENIED'
                || error.message === 'FINANCE_PERMISSION_MISSING'
                || [401, 404].includes(status)
            ) {
                handleLoadError(error);

                return false;
            }

            /*
             * 403 при PATCH может означать запрет только
             * на редактирование, а не на просмотр сводки.
             * Не скрываем всю панель по такому ответу.
             */
            if (status === 403) {
                if (summary.value?.permissions) {
                    summary.value = {
                        ...summary.value,
                        permissions: {
                            ...summary.value.permissions,
                            [config.permission]: false,
                        },
                    };
                }

                config.error.value = getRequestError(
                    error,
                    'Нет разрешения на это изменение. Обновите сводку.'
                );

                return false;
            }

            config.error.value = getRequestError(
                error,
                config.fallback
            );

            return false;
        } finally {
            if (isCurrent(requestGeneration)) {
                config.saving.value = false;

                if (type === 'vat') {
                    vatController = null;
                } else if (type === 'approved') {
                    approvedController = null;
                } else {
                    cashController = null;
                }
            }
        }
    }

    async function toggleVatMode() {
        if (!canToggleVat.value) {
            return false;
        }

        const currentMode = summary.value.approved.vat_mode;

        return saveMutation('vat', {
            vat_mode: currentMode === 'added'
                ? 'included'
                : 'added',
        });
    }

    async function saveApproved(payload) {
        return saveMutation('approved', payload);
    }

    async function saveCash(payload) {
        return saveMutation('cash', payload);
    }

    /**
     * Отмена запросов и очистка состояния.
     *
     * Отмена HTTP-запроса не гарантирует отмену уже начатой
     * записи на сервере, но его ответ больше не применяется
     * к другому делу.
     */
    function reset() {
        generation += 1;

        summaryController?.abort();
        vatController?.abort();
        approvedController?.abort();
        cashController?.abort();

        summaryController = null;
        vatController = null;
        approvedController = null;
        cashController = null;

        clearSummary();
        clearMutationErrors();

        viewPermission.value = null;
        errorMessage.value = '';

        loading.value = false;
        vatSaving.value = false;
        approvedSaving.value = false;
        cashSaving.value = false;
    }

    /*
     * Только первоначальная загрузка и изменение ID дела.
     * Подписок на финансовые изменения других модулей нет.
     */
    watch(
        validApplicationId,
        () => {
            reset();

            if (validApplicationId.value) {
                loadSummary();
            }
        },
        { immediate: true }
    );

    onBeforeUnmount(reset);

    return {
        summary,
        warnings,
        loading,
        errorMessage,
        updatedAt,

        validApplicationId,
        canViewFinance,
        busy,

        vatSaving,
        vatError,
        canToggleVat,
        vatButtonTitle,

        approvedSaving,
        approvedError,

        cashSaving,
        cashError,

        updatedAtLabel,
        refreshTitle,

        loadSummary,
        toggleVatMode,
        saveApproved,
        saveCash,
    };
}