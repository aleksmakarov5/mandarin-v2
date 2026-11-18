import { reactive, computed } from 'vue';

const STORAGE_KEY = 'mandarin_global_registry_filters';

export function useRegistryFilters(onFilterChange) {
    const options = reactive({
        statuses: [],
        finance_statuses: [],
        employees: [],
        cities: [],
        branches: [],
        insurance_companies: [],
    });

    const getInitialFilters = () => ({
        id: '',
        status_ids: [],
        finance_status_ids: [],
        mrk_ids: [],
        city_ids: [],
        insurance_ids: [],
        branch_ids: [],
        ub_types: [],
        sto_name: '',
        ub_number: '',
        ClientName: '',
        ClientPhone: '',
        CarModel: '',
        gnz: '',
        vin: '',
    });

    const filters = reactive(getInitialFilters());

    const loadSavedFilters = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                Object.assign(filters, getInitialFilters(), parsed);
            }
        } catch (e) {
            console.error('Ошибка чтения фильтров:', e);
        }
    };

    const saveFilters = () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        } catch (e) {
            console.error('Ошибка сохранения фильтров:', e);
        }
    };

    const resetAllFilters = () => {
        Object.assign(filters, getInitialFilters());
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { }
        if (onFilterChange) onFilterChange(1);
    };

    const statusOptions = computed(() =>
        (options.statuses || []).map((s) => ({ value: s.id, label: s.name || `Статус #${s.id}` }))
    );
    const financeStatusOptions = computed(() =>
        (options.finance_statuses || []).map((f) => ({ value: f.id, label: f.name || `Фин. статус #${f.id}` }))
    );
    const employeeOptions = computed(() =>
        (options.employees || []).map((e) => ({ value: e.id, label: e.name || `Сотрудник #${e.id}` }))
    );
    const cityOptions = computed(() =>
        (options.cities || []).map((c) => ({ value: c.id, label: c.name || `Город #${c.id}` }))
    );
    const branchOptions = computed(() =>
        (options.branches || []).map((b) => ({ value: b.id, label: b.name || `Филиал #${b.id}` }))
    );
    const insuranceOptions = computed(() =>
        (options.insurance_companies || []).map((i) => ({ value: i.id, label: i.name || `СК #${i.id}` }))
    );

    const activeFiltersCount = computed(() => {
        let count = 0;
        if (filters.id) count++;
        if (filters.status_ids?.length) count += filters.status_ids.length;
        if (filters.finance_status_ids?.length) count += filters.finance_status_ids.length;
        if (filters.mrk_ids?.length) count += filters.mrk_ids.length;
        if (filters.city_ids?.length) count += filters.city_ids.length;
        if (filters.insurance_ids?.length) count += filters.insurance_ids.length;
        if (filters.branch_ids?.length) count += filters.branch_ids.length;
        if (filters.ub_types?.length) count += filters.ub_types.length;
        if (filters.sto_name) count++;
        if (filters.ub_number) count++;
        if (filters.ClientName) count++;
        if (filters.ClientPhone) count++;
        if (filters.CarModel) count++;
        if (filters.gnz) count++;
        if (filters.vin) count++;
        return count;
    });

    return {
        options,
        filters,
        loadSavedFilters,
        saveFilters,
        resetAllFilters,
        statusOptions,
        financeStatusOptions,
        employeeOptions,
        cityOptions,
        branchOptions,
        insuranceOptions,
        activeFiltersCount,
    };
}