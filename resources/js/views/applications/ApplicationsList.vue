<template>
    <div class="applications-container">
        <!-- 1. ДЕСКТОПНЫЙ РЕЖИМ (ФИКСИРОВАННАЯ ЭЛЕГАНТНАЯ ТАБЛИЦА) -->
        <div class="applications-table-wrapper d-none d-lg-block" :class="{ 'fit-screen': !hasFinancialColumns }">
            <table class="table table-bordered table-mandarin mb-0 align-middle"
                :class="{ 'table-fit': !hasFinancialColumns }">
                <thead>
                    <!-- 1 СТРОКА: ЗАГОЛОВКИ -->
                    <tr class="header-blue">
                        <th class="col-num text-center">#</th>
                        <th class="col-dog text-center">Дог</th>
                        <th class="col-status text-center">Статус</th>
                        <th v-if="hasFinancialColumns" class="col-soglas text-center">Согл/Опл</th>
                        <th class="col-id text-center">#</th>
                        <th class="col-mrk">МРК</th>
                        <th class="col-city">Город</th>
                        <th class="col-sk text-center">СК</th>
                        <th class="col-sto text-center">СТО</th>
                        <th class="col-lawyer text-center">Юрист</th>
                        <th class="col-ub">№ убытка</th>
                        <th class="col-client">Клиент</th>
                        <th class="col-phone">Телефон</th>
                        <th class="col-car">Авто</th>
                        <th class="col-gnz text-center">ГРЗ</th>
                        <th class="col-date text-center">Дата</th>
                        <th class="col-type">Тип</th>
                        <th class="col-stoa">СТОА</th>

                        <!-- ФИНАНСОВЫЕ КОЛОНКИ (в Направлениях и Архиве) -->
                        <template v-if="hasFinancialColumns">
                            <th class="col-fin text-end">Сумма СК</th>
                            <th class="col-fin text-end">Сумма З/Ч</th>
                            <th class="col-fin text-end">Ремонт</th>
                            <th class="col-fin text-end">Затраты</th>
                            <th class="col-fin text-end">Оплата СК</th>
                            <th class="col-fin text-end">Оплата СТО</th>
                            <th class="col-fin-wide text-end">Остаток</th>
                        </template>

                        <th class="col-trash text-center"></th>
                        <th class="col-vin">VIN</th>
                    </tr>

                    <!-- 2 СТРОКА: ИНПУТЫ И МУЛЬТИСЕЛЕКТЫ -->
                    <tr class="header-filters">
                        <!-- КНОПКА ПОЛНОГО СБРОСА ВСЕХ ФИЛЬТРОВ -->
                        <th class="col-num text-center p-0">
                            <button type="button" class="btn-reset-filters" @click="resetAllFilters"
                                title="Сбросить все примененные фильтры">
                                <i class="bi bi-x-circle-fill" style="font-size: 0.8rem;"></i>
                            </button>
                        </th>
                        <th class="col-dog"></th>
                        <th class="col-status">
                            <MultiSelectDropdown id="f-status" placeholder="Статус" :options="statusOptions"
                                v-model="filters.status_ids" @change="fetchItems(1)" min-width="190px" />
                        </th>
                        <th v-if="hasFinancialColumns" class="col-soglas"></th>
                        <th class="col-id">
                            <input v-model="filters.id" @input="debounceFetch" type="text"
                                class="form-control-table text-center" />
                        </th>
                        <th class="col-mrk">
                            <MultiSelectDropdown id="f-mrk" placeholder="Все" :options="employeeOptions"
                                v-model="filters.mrk_ids" @change="fetchItems(1)" min-width="170px" />
                        </th>
                        <th class="col-city">
                            <MultiSelectDropdown id="f-city" placeholder="Все" :options="cityOptions"
                                v-model="filters.city_ids" @change="fetchItems(1)" min-width="150px" />
                        </th>
                        <th class="col-sk">
                            <MultiSelectDropdown id="f-sk" placeholder="СК" :options="insuranceOptions"
                                v-model="filters.insurance_ids" @change="fetchItems(1)" min-width="180px" />
                        </th>
                        <th class="col-sto">
                            <input v-model="filters.sto_name" @input="debounceFetch" type="text"
                                class="form-control-table" placeholder="СТО" />
                        </th>
                        <th class="col-lawyer"></th>
                        <th class="col-ub">
                            <input v-model="filters.ub_number" @input="debounceFetch" type="text"
                                class="form-control-table" placeholder="№ убытка" />
                        </th>
                        <th class="col-client">
                            <input v-model="filters.ClientName" @input="debounceFetch" type="text"
                                class="form-control-table" placeholder="Клиент" />
                        </th>
                        <th class="col-phone">
                            <input v-model="filters.ClientPhone" @input="debounceFetch" type="text"
                                class="form-control-table" placeholder="Телефон" />
                        </th>
                        <th class="col-car">
                            <input v-model="filters.CarModel" @input="debounceFetch" type="text"
                                class="form-control-table" placeholder="Авто" />
                        </th>
                        <th class="col-gnz">
                            <input v-model="filters.gnz" @input="debounceFetch" type="text"
                                class="form-control-table text-center" placeholder="ГРЗ" />
                        </th>
                        <th class="col-date"></th>
                        <th class="col-type">
                            <MultiSelectDropdown id="f-ub-type" placeholder="Тип"
                                :options="[{ value: 0, label: 'Осаго' }, { value: 1, label: 'Каско' }]"
                                v-model="filters.ub_types" @change="fetchItems(1)" min-width="110px" />
                        </th>
                        <th class="col-stoa">
                            <MultiSelectDropdown id="f-branch" placeholder="СТОА" :options="branchOptions"
                                v-model="filters.branch_ids" @change="fetchItems(1)" min-width="180px" />
                        </th>

                        <template v-if="hasFinancialColumns">
                            <th class="col-fin"></th>
                            <th class="col-fin"></th>
                            <th class="col-fin"></th>
                            <th class="col-fin"></th>
                            <th class="col-fin"></th>
                            <th class="col-fin"></th>
                            <th class="col-fin-wide"></th>
                        </template>

                        <th class="col-trash"></th>
                        <th class="col-vin">
                            <input v-model="filters.vin" @input="debounceFetch" type="text" class="form-control-table"
                                placeholder="VIN" />
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in items" :key="item.id" class="data-row"
                        :class="{ 'row-overdue': item.is_overdue }" @click="openApplication(item.id)">
                        <!-- 1. Индекс строки -->
                        <td class="col-num text-center fw-bold">
                            <div class="cell-truncate">{{ (pagination.current_page - 1) * 50 + (idx + 1) }}</div>
                        </td>

                        <!-- 2. Дог -->
                        <td class="col-dog text-center p-0">
                            <span v-if="item.dogovor" class="dog-badge dog-yes">да</span>
                            <span v-else class="dog-badge dog-no">нет</span>
                        </td>

                        <!-- 3. Статус + таймер -->
                        <td class="col-status status-cell text-center p-1">
                            <div class="status-title-box cell-truncate" :style="{
                                backgroundColor: item.status?.bg_color || '#fef08a',
                                color: item.status?.text_color || '#000000',
                            }" @mouseenter="handleCellMouseEnter($event, item.status?.name)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.status?.name || 'Без статуса' }}
                            </div>
                            <div class="status-time-box cell-truncate" :class="{ 'time-overdue': item.is_overdue }">
                                {{ item.time_status_text }}
                            </div>
                        </td>

                        <!-- 4. Согл/Опл -->
                        <td class="col-soglas status-cell text-center p-1" v-if="hasFinancialColumns">
                            <div v-if="item.soglas_title" class="soglas-title-box cell-truncate"
                                @mouseenter="handleCellMouseEnter($event, item.soglas_title)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.soglas_title }}
                            </div>
                            <div v-if="item.soglas_time_text" class="soglas-time-box cell-truncate"
                                :class="{ 'time-overdue': item.soglas_overdue }">
                                {{ item.soglas_time_text }}
                            </div>
                        </td>

                        <!-- 5. # ID -->
                        <td class="col-id text-center fw-bold text-dark">
                            <div class="cell-truncate">{{ item.id }}</div>
                        </td>

                        <!-- 6. МРК -->
                        <td class="col-mrk">
                            <div class="cell-truncate"
                                @mouseenter="handleCellMouseEnter($event, item.mrk?.last_name || item.mrk?.name)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.mrk?.last_name || item.mrk?.name || '—' }}
                            </div>
                        </td>

                        <!-- 7. Город -->
                        <td class="col-city">
                            <div class="cell-truncate" @mouseenter="handleCellMouseEnter($event, item.city?.name)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.city?.name || '—' }}
                            </div>
                        </td>

                        <!-- 8. СК -->
                        <td class="col-sk text-center">
                            <span class="badge-sk cell-truncate d-inline-block"
                                @mouseenter="handleCellMouseEnter($event, item.insurance_company?.name)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.insurance_company?.name || 'ИНГОС' }}
                            </span>
                        </td>

                        <!-- 9. СТО -->
                        <td class="col-sto text-center">
                            <span class="badge-sto cell-truncate d-inline-block"
                                @mouseenter="handleCellMouseEnter($event, item.service_name || 'Прочее')"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.service_name || 'Прочее' }}
                            </span>
                        </td>

                        <!-- 10. Юрист -->
                        <td class="col-lawyer text-center">
                            <div class="lawyer-line"></div>
                        </td>

                        <!-- 11. № убытка -->
                        <td class="col-ub">
                            <div class="cell-truncate font-monospace"
                                @mouseenter="handleCellMouseEnter($event, item.numberInsuranceCompanyApplication || item.ub_number)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.numberInsuranceCompanyApplication || item.ub_number || '—' }}
                            </div>
                        </td>

                        <!-- 12. Клиент -->
                        <td class="col-client">
                            <div class="cell-truncate text-uppercase"
                                @mouseenter="handleCellMouseEnter($event, item.ClientName)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.ClientName || '—' }}
                            </div>
                        </td>

                        <!-- 13. Телефон -->
                        <td class="col-phone">
                            <div class="cell-truncate font-monospace" style="color: #2563eb; font-weight: 500;"
                                @mouseenter="handleCellMouseEnter($event, item.ClientPhone)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.ClientPhone || '—' }}
                            </div>
                        </td>

                        <!-- 14. Авто -->
                        <td class="col-car">
                            <div class="cell-truncate text-uppercase fw-medium"
                                @mouseenter="handleCellMouseEnter($event, item.CarModel)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.CarModel || '—' }}
                            </div>
                        </td>

                        <!-- 15. ГРЗ (РЕАЛИСТИЧНЫЙ АВТОНОМЕР РФ) -->
                        <td class="col-gnz text-center p-0">
                            <div class="plate-ru-container" @mouseenter="handleCellMouseEnter($event, item.gnz)"
                                @mouseleave="handleCellMouseLeave">
                                <div class="plate-ru">
                                    <span class="plate-number">{{ parseGnz(item.gnz).number }}</span>
                                    <div v-if="parseGnz(item.gnz).region" class="plate-region-box">
                                        <span class="plate-region">{{ parseGnz(item.gnz).region }}</span>
                                        <div class="plate-flag-row">
                                            <span class="plate-rus-text">RUS</span>
                                            <span class="plate-flag">
                                                <span class="flag-white"></span>
                                                <span class="flag-blue"></span>
                                                <span class="flag-red"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- 16. Дата -->
                        <td class="col-date text-center small">
                            <div class="cell-truncate">{{ formatDate(item.created_at) }}</div>
                        </td>

                        <!-- 17. Тип убытка -->
                        <td class="col-type">
                            <div class="cell-truncate">{{ item.ub_type === 1 ? 'Каско' : 'Осаго' }}</div>
                        </td>

                        <!-- 18. СТОА -->
                        <td class="col-stoa">
                            <div class="cell-truncate"
                                @mouseenter="handleCellMouseEnter($event, item.our_company?.name)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.our_company?.name || 'ООО "Мандарин групп"' }}
                            </div>
                        </td>

                        <!-- ФИНАНСОВЫЕ КОЛОНКИ СУММ -->
                        <template v-if="hasFinancialColumns">
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.sum_sk) }} р.</div>
                            </td>
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.sum_zap) }} р.</div>
                            </td>
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.remont_sum) }} р.</div>
                            </td>
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.total_costs) }} р.</div>
                            </td>
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.ic_pay) }} р.</div>
                            </td>
                            <td class="col-fin text-end fw-medium">
                                <div class="cell-truncate">{{ formatMoney(item.sto_pay) }} р.</div>
                            </td>
                            <td class="col-fin-wide text-end text-success fw-bold">
                                <div class="cell-truncate">{{ formatMoney(item.remains) }} ({{ item.remains_percent }}%)
                                </div>
                            </td>
                        </template>

                        <!-- Удаление -->
                        <td class="col-trash text-center p-0" @click.stop>
                            <button class="btn btn-link btn-sm text-secondary p-0" @click="moveToTrash(item)">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>

                        <!-- VIN -->
                        <td class="col-vin">
                            <div class="cell-truncate font-monospace small text-uppercase"
                                @mouseenter="handleCellMouseEnter($event, item.vin)" @mouseleave="handleCellMouseLeave">
                                {{ item.vin || '—' }}
                            </div>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0 && !loading">
                        <td :colspan="hasFinancialColumns ? 26 : 19" class="text-center py-5 text-muted">
                            Дела не найдены
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 2. МОБИЛЬНАЯ ВЕРХНЯЯ СТРОКА: ПОИСК + КНОПКА "ФИЛЬТРЫ" (< 992px) -->
        <div class="mobile-filter-bar d-lg-none bg-white p-2 border-bottom shadow-sm mb-1">
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm flex-grow-1">
                    <span class="input-group-text bg-light text-muted border-end-0 py-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input v-model="filters.CarModel" @input="debounceFetch" type="text"
                        class="form-control border-start-0 ps-0" placeholder="Поиск по авто, клиенту, ГНЗ..." />
                    <button v-if="filters.CarModel" class="btn btn-outline-secondary py-0"
                        @click="filters.CarModel = ''; fetchItems(1)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>

                <button class="btn btn-sm mob-filter-btn d-flex align-items-center text-nowrap" :class="{
                    'btn-warning text-dark fw-bold': activeFiltersCount > 0,
                    'btn-outline-dark': activeFiltersCount === 0,
                }" @click="isMobileFilterOpen = true">
                    <i class="bi bi-funnel-fill me-1"></i>
                    <span>Фильтры</span>
                    <span v-if="activeFiltersCount > 0" class="badge bg-dark text-white rounded-pill ms-1"
                        style="font-size: 0.65rem;">
                        {{ activeFiltersCount }}
                    </span>
                </button>

                <button v-if="activeFiltersCount > 0" class="btn btn-sm btn-outline-danger p-1" @click="resetAllFilters"
                    title="Сбросить все фильтры">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- 3. МОБИЛЬНЫЙ СПИСОК КАРТОЧЕК (< 992px) -->
        <div class="mobile-registry-list d-lg-none px-1 py-1">
            <div v-for="item in items" :key="'mob-' + item.id"
                class="mob-deal-card mb-1 bg-white border rounded shadow-sm p-2"
                :class="{ 'mob-row-overdue': item.is_overdue }" @click="openApplication(item.id)">
                <!-- 1 СТРОКА: АВТО + ГНЗ + СТАТУС -->
                <div class="d-flex justify-content-between align-items-center gap-1 mb-1">
                    <div class="d-flex align-items-center gap-1 text-truncate flex-grow-1">
                        <span class="fw-bold text-dark text-truncate mob-car-title" :title="item.CarModel">
                            {{ item.CarModel || 'Автомобиль не указан' }}
                        </span>
                        <span class="mob-plate-badge text-nowrap flex-shrink-0">
                            {{ item.gnz || 'Б/Н' }}
                        </span>
                    </div>

                    <div class="mob-status-pill text-nowrap flex-shrink-0 text-center">
                        <div class="mob-status-name" :style="{
                            backgroundColor: item.status?.bg_color || '#fef08a',
                            color: item.status?.text_color || '#000000',
                        }">
                            {{ item.status?.name || 'Без статуса' }}
                        </div>
                        <div class="mob-status-time" :class="{ 'time-overdue': item.is_overdue }">
                            {{ item.time_status_text }}
                        </div>
                    </div>
                </div>

                <!-- 2 СТРОКА: КЛИЕНТ (ПОЛНОЕ ФИО БЕЗ ОБРЕЗАНИЯ) -->
                <div class="mob-client-row text-dark mb-1">
                    <i class="bi bi-person text-secondary me-1"></i>
                    <span class="fw-semibold mob-client-name text-uppercase">
                        {{ item.ClientName || 'Клиент не указан' }}
                    </span>
                </div>

                <!-- 3 СТРОКА: ТЕЛЕФОН (КЛИКАБЕЛЬНЫЙ) -->
                <div class="mob-phone-row mb-1 pb-1 border-bottom" v-if="item.ClientPhone">
                    <a :href="'tel:' + item.ClientPhone"
                        class="mob-phone-link d-inline-flex align-items-center text-decoration-none text-primary fw-medium"
                        @click.stop>
                        <i class="bi bi-telephone-fill me-1" style="font-size: 0.70rem;"></i>
                        <span>{{ item.ClientPhone }}</span>
                    </a>
                </div>

                <!-- 4 СТРОКА: ДЕТАЛИ -->
                <div class="mob-details-grid d-flex flex-wrap align-items-center justify-content-between gap-1 text-muted"
                    style="font-size: 0.68rem;">
                    <div class="text-nowrap">
                        <span class="fw-bold text-dark">#{{ item.id }}</span>
                        <span v-if="item.numberInsuranceCompanyApplication || item.ub_number"
                            class="text-secondary ms-1">
                            ({{ item.numberInsuranceCompanyApplication || item.ub_number }})
                        </span>
                    </div>

                    <span class="badge-sk text-nowrap">
                        {{ item.insurance_company?.name || 'ИНГОС' }}
                    </span>

                    <div class="text-truncate" style="max-width: 120px;">
                        <i class="bi bi-geo-alt me-1"></i>{{ item.our_company?.name || item.city?.name || 'СТОА' }}
                    </div>

                    <div class="text-nowrap text-end">
                        {{ formatDate(item.created_at) }}
                    </div>
                </div>

                <!-- ФИНАНСОВЫЙ БЛОК -->
                <div v-if="hasFinancialColumns && item.sum_sk"
                    class="mob-fin-bar mt-1 pt-1 border-top d-flex justify-content-between align-items-center small"
                    style="font-size: 0.70rem;">
                    <span>Сумма СК: <strong>{{ formatMoney(item.sum_sk) }} р.</strong></span>
                    <span class="text-success fw-bold">Остаток: {{ formatMoney(item.remains) }} ({{ item.remains_percent
                    }}%)</span>
                </div>
            </div>

            <div v-if="items.length === 0 && !loading" class="text-center py-5 text-muted bg-white rounded shadow-sm">
                <i class="bi bi-inbox fs-2 d-block text-secondary mb-1"></i>
                Дела не найдены
            </div>
        </div>

        <!-- 4. ПАГИНАЦИЯ -->
        <div v-if="pagination.last_page > 1"
            class="d-flex justify-content-between align-items-center px-3 py-2 border-top bg-white pagination-bar shadow-sm">
            <small class="text-muted">
                Показано {{ (pagination.current_page - 1) * 50 + 1 }} - {{ Math.min(pagination.current_page * 50,
                    pagination.total) }} из {{ pagination.total }}
            </small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                    <button class="page-link" @click="fetchItems(pagination.current_page - 1)">&laquo;</button>
                </li>
                <li v-for="p in pagination.last_page" :key="p" class="page-item"
                    :class="{ active: pagination.current_page === p }">
                    <button class="page-link" @click="fetchItems(p)">{{ p }}</button>
                </li>
                <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                    <button class="page-link" @click="fetchItems(pagination.current_page + 1)">&raquo;</button>
                </li>
            </ul>
        </div>

        <!-- 5. МОБИЛЬНАЯ ШТОРКА ФИЛЬТРОВ -->
        <Teleport to="body">
            <div v-if="isMobileFilterOpen" class="mob-filter-backdrop" @click="isMobileFilterOpen = false"></div>
            <div v-if="isMobileFilterOpen" class="mob-filter-drawer shadow-lg">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                    <h6 class="mb-0 fw-bold d-flex align-items-center">
                        <i class="bi bi-funnel-fill text-primary me-2"></i>
                        Фильтры реестра
                    </h6>
                    <button class="btn btn-sm btn-light border-0" @click="isMobileFilterOpen = false">
                        <i class="bi bi-x-lg fs-5"></i>
                    </button>
                </div>

                <div class="p-3 mob-filter-content">
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Статус дела</label>
                        <div class="mob-chip-list">
                            <span v-for="st in statusOptions" :key="st.value" class="mob-chip"
                                :class="{ active: filters.status_ids.includes(st.value) }"
                                @click="toggleMobileArrayFilter('status_ids', st.value)">
                                {{ st.label }}
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Сотрудник (МРК)</label>
                        <select v-model="filters.mrk_ids" class="form-select form-select-sm" multiple
                            style="height: 90px;">
                            <option v-for="emp in employeeOptions" :key="emp.value" :value="emp.value">
                                {{ emp.label }}
                            </option>
                        </select>
                        <small class="text-muted" style="font-size: 0.65rem;">Удерживайте для выбора нескольких</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Город</label>
                        <div class="mob-chip-list">
                            <span v-for="c in cityOptions" :key="c.value" class="mob-chip"
                                :class="{ active: filters.city_ids.includes(c.value) }"
                                @click="toggleMobileArrayFilter('city_ids', c.value)">
                                {{ c.label }}
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Страховая компания (СК)</label>
                        <select v-model="filters.insurance_ids" class="form-select form-select-sm" multiple
                            style="height: 90px;">
                            <option v-for="ic in insuranceOptions" :key="ic.value" :value="ic.value">
                                {{ ic.label }}
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Тип убытка</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm flex-fill"
                                :class="filters.ub_types.includes(0) ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="toggleMobileArrayFilter('ub_types', 0)">
                                ОСАГО
                            </button>
                            <button type="button" class="btn btn-sm flex-fill"
                                :class="filters.ub_types.includes(1) ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="toggleMobileArrayFilter('ub_types', 1)">
                                КАСКО
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1 text-muted">ГРЗ (номер)</label>
                            <input v-model="filters.gnz" type="text" class="form-control form-control-sm text-uppercase"
                                placeholder="А001АА29" />
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1 text-muted">№ убытка / дела</label>
                            <input v-model="filters.ub_number" type="text" class="form-control form-control-sm"
                                placeholder="№ дела СК" />
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1 text-muted">VIN автомобиля</label>
                            <input v-model="filters.vin" type="text" class="form-control form-control-sm text-uppercase"
                                placeholder="VIN..." />
                        </div>
                    </div>
                </div>

                <div class="p-3 border-top bg-light d-flex gap-2">
                    <button class="btn btn-outline-danger btn-sm flex-fill py-2" @click="resetAllFilters">
                        Сбросить
                    </button>
                    <button class="btn btn-primary btn-sm flex-fill py-2 fw-bold" @click="applyMobileFilters">
                        Показать ({{ pagination.total }})
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- 6. БЕЛЫЙ ПАРЯЩИЙ ТУЛТИП ДЛЯ УСЕЧЕННЫХ ЯЧЕЕК -->
        <Teleport to="body">
            <div v-if="tooltip.visible" class="floating-cell-tooltip shadow-lg" :style="{
                top: tooltip.y + 'px',
                left: tooltip.x + 'px',
            }">
                {{ tooltip.text }}
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import MultiSelectDropdown from '../../components/MultiSelectDropdown.vue';

const route = useRoute();
const router = useRouter();

const currentTab = computed(() => route.name || 'requests');
const hasFinancialColumns = computed(() => ['applications', 'archive'].includes(currentTab.value));

const items = ref([]);
const loading = ref(false);

const options = reactive({
    statuses: [],
    employees: [],
    cities: [],
    branches: [],
    insurance_companies: [],
});

const STORAGE_KEY = 'mandarin_global_registry_filters';

const getInitialFilters = () => ({
    id: '',
    status_ids: [],
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
    fetchItems(1);
};

const parseGnz = (raw) => {
    if (!raw) return { number: '—', region: '' };
    const str = String(raw).trim().toUpperCase().replace(/\s+/g, '');
    const match = str.match(/^([АВЕКМНОРСТУХABEKMHOPCTYX]\d{3}[АВЕКМНОРСТУХABEKMHOPCTYX]{2})(\d{2,3})$/i);
    if (match) {
        const numPart = match[1];
        const formatted = `${numPart[0]} ${numPart.slice(1, 4)} ${numPart.slice(4, 6)}`;
        return { number: formatted, region: match[2] };
    }
    return { number: raw, region: '' };
};

const statusOptions = computed(() => {
    return (options.statuses || []).map((s) => ({
        value: s.id,
        label: s.name || `Статус #${s.id}`,
    }));
});

const employeeOptions = computed(() => {
    return (options.employees || []).map((e) => ({
        value: e.id,
        label: e.name || `Сотрудник #${e.id}`,
    }));
});

const cityOptions = computed(() => {
    return (options.cities || []).map((c) => ({
        value: c.id,
        label: c.name || `Город #${c.id}`,
    }));
});

const branchOptions = computed(() => {
    return (options.branches || []).map((b) => ({
        value: b.id,
        label: b.name || `Филиал #${b.id}`,
    }));
});

const insuranceOptions = computed(() => {
    return (options.insurance_companies || []).map((i) => ({
        value: i.id,
        label: i.name || `СК #${i.id}`,
    }));
});

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
let timer = null;

// Тултип логика для десктопа
const tooltip = reactive({
    visible: false,
    text: '',
    x: 0,
    y: 0,
});

const handleCellMouseEnter = (event, fullText) => {
    const el = event.currentTarget;
    if (!el || !fullText) return;

    const rawText = String(fullText).trim();
    if (!rawText || rawText === '—') return;

    // Проверяем: физически ли обрезается текст (с допуском 1px)
    if (el.scrollWidth > el.clientWidth + 1) {
        const rect = el.getBoundingClientRect();
        tooltip.text = rawText;

        let top = rect.top - 32;
        let left = rect.left + rect.width / 2;

        if (top < 10) {
            top = rect.bottom + 8;
        }

        left = Math.max(120, Math.min(window.innerWidth - 120, left));

        tooltip.x = left;
        tooltip.y = top;
        tooltip.visible = true;
    }
};

const handleCellMouseLeave = () => {
    tooltip.visible = false;
};

// Мобильная панель фильтров
const isMobileFilterOpen = ref(false);

const activeFiltersCount = computed(() => {
    let count = 0;
    if (filters.id) count++;
    if (filters.status_ids?.length) count += filters.status_ids.length;
    if (filters.mrk_ids?.length) count += filters.mrk_ids.length;
    if (filters.city_ids?.length) count += filters.city_ids.length;
    if (filters.insurance_ids?.length) count += filters.insurance_ids.length;
    if (filters.branch_ids?.length) count += filters.branch_ids.length;
    if (filters.ub_types?.length) count += filters.ub_types.length;
    if (filters.sto_name) count++;
    if (filters.ub_number) count++;
    if (filters.ClientName) count++; if (filters.ClientPhone) count++;
    if (filters.CarModel) count++;
    if (filters.gnz) count++;
    if (filters.vin) count++;
    return count;
});

const toggleMobileArrayFilter = (field, value) => {
    const current = [...filters[field]];
    const idx = current.indexOf(value);
    if (idx > -1) {
        current.splice(idx, 1);
    } else {
        current.push(value);
    }
    filters[field] = current;
};

const applyMobileFilters = () => {
    isMobileFilterOpen.value = false;
    fetchItems(1);
};

const fetchFilterOptions = async () => {
    try {
        const res = await axios.get('/api/applications/filters');
        if (res.data) {
            options.statuses = res.data.statuses || [];
            options.employees = res.data.employees || [];
            options.cities = res.data.cities || [];
            options.branches = res.data.branches || [];
            options.insurance_companies = res.data.insurance_companies || [];
        }
    } catch (err) {
        console.error('Ошибка загрузки фильтров:', err);
    }
};

const fetchItems = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            tab: currentTab.value,
            page,
            id: filters.id || undefined,
            status_ids: filters.status_ids.length ? filters.status_ids.join(',') : undefined,
            mrk_ids: filters.mrk_ids.length ? filters.mrk_ids.join(',') : undefined,
            city_ids: filters.city_ids.length ? filters.city_ids.join(',') : undefined,
            insurance_ids: filters.insurance_ids.length ? filters.insurance_ids.join(',') : undefined,
            branch_ids: filters.branch_ids.length ? filters.branch_ids.join(',') : undefined,
            ub_types: filters.ub_types.length ? filters.ub_types.join(',') : undefined,
            sto_name: filters.sto_name || undefined,
            ub_number: filters.ub_number || undefined,
            ClientName: filters.ClientName || undefined,
            ClientPhone: filters.ClientPhone || undefined,
            CarModel: filters.CarModel || undefined,
            gnz: filters.gnz || undefined,
            vin: filters.vin || undefined,
        };

        const res = await axios.get('/api/applications', { params });
        items.value = res.data.data || [];
        pagination.value = {
            current_page: res.data.current_page || 1,
            last_page: res.data.last_page || 1,
            total: res.data.total || 0,
        };
    } catch (err) {
        console.error('Ошибка загрузки списка дел:', err);
    } finally {
        loading.value = false;
    }
};

const debounceFetch = () => {
    clearTimeout(timer);
    timer = setTimeout(() => fetchItems(1), 350);
};

const openApplication = (id) => router.push(`/applications/${id}`);

const moveToTrash = async (item) => {
    if (confirm(`Переместить дело #${item.id} в корзину?`)) {
        await axios.put(`/api/applications/${item.id}`, { id_status: 27 });
        fetchItems(pagination.value.current_page);
    }
};

const formatDate = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatMoney = (val) => {
    const n = parseFloat(val) || 0;
    return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

watch(
    filters,
    () => {
        saveFilters();
    },
    { deep: true }
);

watch(
    () => route.name,
    () => {
        filters.status_ids = [];
        saveFilters();
        fetchItems(1);
    }
);

onMounted(async () => {
    loadSavedFilters();
    await fetchFilterOptions();
    fetchItems(1);
});
</script>

<style scoped>
.applications-container,
.table-mandarin,
.table-mandarin th,
.table-mandarin td,
.table-mandarin input,
.table-mandarin select,
.table-mandarin button,
.table-mandarin span,
.table-mandarin div {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    text-rendering: optimizeLegibility !important;
}

.applications-container {
    display: flex;
    flex-direction: column;
    min-height: 100%;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.applications-table-wrapper {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
}

.applications-table-wrapper.fit-screen {
    overflow-x: hidden !important;
}

/* 1 СТРОКА: ЗАГОЛОВКИ КОЛОНОК */
.table-mandarin thead .header-blue th {
    background: linear-gradient(180deg, #1e88e5 0%, #1565c0 100%) !important;
    color: #ffffff !important;
    font-size: 0.69rem !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 6px 3px !important;
    border-top: none !important;
    border-bottom: 1px solid #0d47a1 !important;
    border-right: 1px solid rgba(255, 255, 255, 0.18) !important;
    border-left: none !important;
    white-space: nowrap;
    vertical-align: middle;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    user-select: none;
}

.table-mandarin thead .header-blue th:last-child {
    border-right: none !important;
}

/* 2 СТРОКА: ПОЛЯ ФИЛЬТРАЦИИ */
.table-mandarin thead .header-filters th {
    background: #f1f5f9 !important;
    padding: 3px 2px !important;
    border-bottom: 2px solid #cbd5e1 !important;
    border-right: 1px solid #e2e8f0 !important;
    border-top: none !important;
    vertical-align: middle;
}

.table-mandarin thead .header-filters th:last-child {
    border-right: none !important;
}

/* ИНПУТЫ ФИЛЬТРОВ */
:deep(.form-control-table),
.form-control-table {
    width: 100%;
    font-size: 0.68rem !important;
    font-weight: 500;
    font-family: inherit;
    padding: 1px 5px !important;
    height: 22px !important;
    line-height: 1.2;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    background-color: #ffffff;
    color: #1e293b;
    outline: none;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: all 0.15s ease-in-out;
}

:deep(.form-control-table::placeholder),
.form-control-table::placeholder {
    color: #94a3b8;
    font-size: 0.66rem;
}

:deep(.form-control-table:hover),
.form-control-table:hover {
    border-color: #94a3b8;
}

:deep(.form-control-table:focus),
.form-control-table:focus {
    border-color: #0284c7;
    background-color: #ffffff;
    box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
}

/* КНОПКА СБРОСА ВСЕХ ФИЛЬТРОВ */
.btn-reset-filters {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: none;
    background: transparent;
    color: #94a3b8;
    transition: all 0.15s ease;
    cursor: pointer;
}

.btn-reset-filters:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: scale(1.1);
}

.table-mandarin td {
    padding: 2px 3px !important;
    font-size: 0.70rem !important;
    line-height: 1.2;
    border: 1px solid #000000 !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #1e293b;
}

.cell-truncate {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

.col-num {
    width: 24px;
    min-width: 24px;
    max-width: 24px;
}

.col-dog {
    width: 28px;
    min-width: 28px;
    max-width: 28px;
}

.col-status {
    width: 100px;
    min-width: 90px;
    max-width: 110px;
}

.col-soglas {
    width: 95px;
    min-width: 90px;
    max-width: 105px;
}

.col-id {
    width: 44px;
    min-width: 44px;
    max-width: 50px;
}

.col-mrk {
    width: 75px;
    min-width: 70px;
    max-width: 90px;
}

.col-city {
    width: 75px;
    min-width: 70px;
    max-width: 85px;
}

.col-sk {
    width: 68px;
    min-width: 65px;
    max-width: 75px;
}

.col-sto {
    width: 68px;
    min-width: 65px;
    max-width: 75px;
}

.col-lawyer {
    width: 44px;
    min-width: 40px;
    max-width: 48px;
}

.col-ub {
    width: 95px;
    min-width: 85px;
    max-width: 110px;
}

.col-client {
    width: 105px;
    min-width: 95px;
    max-width: 130px;
}

.col-phone {
    font-size: 0.6rem;
    width: 105px;
    min-width: 80px;
    max-width: 105px;
}

.col-car {
    width: 95px;
    min-width: 85px;
    max-width: 115px;
}

.col-gnz {
    width: 72px;
    min-width: 70px;
    max-width: 82px;
}

.col-date {
    width: 60px;
    min-width: 55px;
    max-width: 65px;
}

.col-type {
    width: 55px;
    min-width: 50px;
    max-width: 65px;
}

.col-stoa {
    width: 95px;
    min-width: 85px;
    max-width: 120px;
}

.col-fin {
    width: 78px;
    min-width: 75px;
    max-width: 85px;
}

.col-fin-wide {
    width: 95px;
    min-width: 90px;
    max-width: 110px;
}

.col-trash {
    width: 24px;
    min-width: 24px;
    max-width: 26px;
}

.col-vin {
    width: 110px;
    min-width: 100px;
    max-width: 130px;
}

.data-row {
    cursor: pointer;
    background-color: #ffffff;
    transition: background-color 0.12s ease-in-out;
}

.data-row:hover td {
    background-color: #e0f2fe !important;
    color: #0f172a !important;
}

.row-overdue {
    background-color: #fca5a5 !important;
}

.row-overdue td {
    background-color: #fca5a5 !important;
    color: #000000 !important;
}

.row-overdue:hover td {
    background-color: #f87171 !important;
    color: #000000 !important;
}

.status-cell {
    padding: 1px 2px !important;
}

.status-title-box,
.soglas-title-box {
    font-size: 0.63rem !important;
    font-weight: 600;
    padding: 1px 2px;
    line-height: 1.15;
    white-space: nowrap;
    border-top: 1px solid #000000;
    border-left: 1px solid #000000;
    border-right: 1px solid #000000;
    border-radius: 2px 2px 0 0;
}

.status-time-box,
.soglas-time-box {
    font-size: 0.62rem !important;
    font-weight: 700;
    padding: 1px 2px;
    line-height: 1.15;
    background-color: #1d4ed8;
    color: #ffffff;
    border: 1px solid #000000;
    border-radius: 0 0 2px 2px;
}

.time-overdue {
    background-color: #b91c1c !important;
    color: #ffffff !important;
}

.dog-badge {
    display: inline-block;
    font-size: 0.60rem !important;
    font-weight: 700;
    padding: 0 4px;
    border-radius: 3px;
    color: #ffffff;
    border: 1px solid #000000;
    line-height: 1.2;
}

.dog-yes {
    background-color: #16a34a;
}

.dog-no {
    background-color: #dc2626;
}

.badge-sk {
    font-size: 0.63rem !important;
    font-weight: 700;
    padding: 1px 4px;
    background-color: #fce7f3;
    color: #be185d;
    border-radius: 3px;
    border: 1px solid #000000;
    line-height: 1.2;
}

.badge-sto {
    font-size: 0.63rem !important;
    font-weight: 500;
    padding: 1px 4px;
    background-color: #e0f2fe;
    color: #0284c7;
    border-radius: 3px;
    border: 1px solid #000000;
    line-height: 1.2;
}

.lawyer-line {
    width: 24px;
    height: 4px;
    background-color: #16a34a;
    border: 1px solid #000000;
    border-radius: 2px;
    display: inline-block;
}

.plate-ru-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.plate-ru {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    color: #111827;
    border: 1px solid #1f2937;
    border-radius: 2px;
    height: 18px;
    padding: 0 2px;
    line-height: 1;
    user-select: none;
}

.plate-number {
    font-size: 0.64rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    padding: 0 2px 0 1px;
    text-transform: uppercase;
    color: #000000;
    white-space: nowrap;
}

.plate-region-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-left: 1px solid #1f2937;
    padding-left: 2px;
    padding-right: 1px;
    height: 100%;
    min-width: 14px;
}

.plate-region {
    font-size: 0.54rem;
    font-weight: 800;
    line-height: 1;
    color: #000000;
    letter-spacing: -0.2px;
}

.plate-flag-row {
    display: flex;
    align-items: center;
    gap: 1px;
    margin-top: 0.5px;
}

.plate-rus-text {
    font-size: 0.34rem;
    font-weight: 800;
    line-height: 1;
    color: #000000;
    transform: scale(0.85);
}

.plate-flag {
    display: flex;
    flex-direction: column;
    width: 5px;
    height: 3.5px;
    border: 0.5px solid #94a3b8;
    border-radius: 0.5px;
    overflow: hidden;
}

.plate-flag .flag-white {
    background-color: #ffffff;
    height: 1.2px;
    width: 100%;
}

.plate-flag .flag-blue {
    background-color: #0039a6;
    height: 1.2px;
    width: 100%;
}

.plate-flag .flag-red {
    background-color: #d52b1e;
    height: 1.2px;
    width: 100%;
}

.pagination-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    border-top: 1px solid #000000 !important;
}

.mob-filter-btn {
    height: 31px;
    font-size: 0.75rem;
}

.mob-deal-card {
    cursor: pointer;
    border: 1px solid #000000 !important;
    border-left: 4px solid #299bd1 !important;
    transition: transform 0.08s ease, background-color 0.12s ease;
    line-height: 1.25;
}

.mob-deal-card:active {
    transform: scale(0.99);
    background-color: #f1f5f9;
}

.mob-car-title {
    font-size: 0.82rem;
    letter-spacing: -0.01em;
}

.mob-plate-badge {
    display: inline-block;
    background-color: #ffffff;
    color: #000000;
    border: 1px solid #000000;
    border-radius: 3px;
    padding: 0 4px;
    font-size: 0.70rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.mob-status-pill {
    width: 90px;
}

.mob-status-name {
    font-size: 0.60rem;
    font-weight: 600;
    padding: 1px 3px;
    border: 1px solid #000000;
    border-bottom: none;
    border-radius: 2px 2px 0 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mob-status-time {
    font-size: 0.58rem;
    font-weight: 700;
    padding: 1px 3px;
    background-color: #1d4ed8;
    color: #ffffff;
    border: 1px solid #000000;
    border-radius: 0 0 2px 2px;
}

.mob-client-row {
    font-size: 0.76rem;
    line-height: 1.25;
}

.mob-client-name {
    word-break: break-word;
    white-space: normal;
    display: inline;
}

.mob-phone-row {
    line-height: 1.2;
}

.mob-phone-link {
    font-size: 0.72rem;
    padding: 1px 6px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
    letter-spacing: 0.2px;
}

.mob-phone-link:active {
    background-color: #dbeafe;
}

.mob-row-overdue {
    background-color: #fca5a5 !important;
    border-left-color: #b91c1c !important;
}

.mob-row-overdue .mob-plate-badge {
    background-color: #fee2e2;
}

.mob-filter-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999998;
    backdrop-filter: blur(2px);
}

.mob-filter-drawer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    height: 85vh;
    max-height: 85vh;
    background: #ffffff;
    border-radius: 16px 16px 0 0;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.25s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
    }

    to {
        transform: translateY(0);
    }
}

.mob-filter-content {
    overflow-y: auto;
    flex: 1;
}

.mob-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.mob-chip {
    padding: 4px 8px;
    font-size: 0.72rem;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    color: #334155;
    cursor: pointer;
    user-select: none;
}

.mob-chip.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #1d4ed8;
    font-weight: 600;
}
</style>

<style>
.floating-cell-tooltip {
    position: fixed;
    transform: translateX(-50%);
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.72rem;
    font-weight: 500;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    white-space: nowrap;
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.18), 0 4px 6px -2px rgba(0, 0, 0, 0.08) !important;
    z-index: 9999999 !important;
    pointer-events: none;
    animation: tooltipFadeIn 0.12s ease-out;
}

.floating-cell-tooltip::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 5px;
    border-style: solid;
    border-color: #ffffff transparent transparent transparent;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, 4px);
    }

    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}
</style>
