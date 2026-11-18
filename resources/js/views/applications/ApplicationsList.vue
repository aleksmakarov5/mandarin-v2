<template>
    <div class="applications-container">
        <!-- 1. ДЕСКТОПНЫЙ РЕЖИМ (ТАБЛИЦА) -->
        <div class="applications-table-wrapper d-none d-lg-block"
            :class="{ 'fit-screen': currentTab !== 'applications' && currentTab !== 'archive' }">
            <table class="table table-bordered table-mandarin mb-0 align-middle"
                :class="{ 'table-fit': currentTab !== 'applications' && currentTab !== 'archive' }">
                <thead>
                    <!-- 1 СТРОКА: ЗАГОЛОВКИ -->
                    <tr class="header-blue">
                        <th class="col-num text-center">#</th>
                        <th class="col-dog text-center">Дог</th>
                        <th class="col-status text-center">Статус</th>
                        <!-- 3. СТОЛБЕЦ СОГЛ/ОПЛ ТОЛЬКО ВО ВКЛАДКЕ НАПРАВЛЕНИЯ -->
                        <th v-if="currentTab === 'applications'" class="col-soglas text-center">Согл/Опл</th>
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
                        <th class="col-gnz">ГРЗ</th>
                        <th class="col-date text-center">Дата</th>
                        <th class="col-type">Тип</th>
                        <th class="col-stoa">СТОА</th>

                        <!-- Финансовые столбцы в Направлениях и Архиве -->
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

                    <!-- 2 СТРОКА: ФИЛЬТРЫ -->
                    <tr class="header-filters">
                        <th class="col-num text-center p-0">
                            <button class="btn btn-sm btn-link text-white p-0"
                                style="font-size: 0.72rem; text-decoration: none;" @click="resetAllFilters"
                                title="Сбросить все фильтры">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </th>
                        <th class="col-dog"></th>
                        <th class="col-status">
                            <MultiSelectDropdown id="f-status" placeholder="Статус" :options="statusOptions"
                                v-model="filters.status_ids" @change="fetchItems(1)" min-width="190px" />
                        </th>
                        <!-- ФИЛЬТР СОГЛ/ОПЛ ТОЛЬКО ВО ВКЛАДКЕ НАПРАВЛЕНИЯ -->
                        <th v-if="currentTab === 'applications'" class="col-soglas">
                            <MultiSelectDropdown id="f-fin-status" placeholder="Согл/Опл"
                                :options="financeStatusOptions" v-model="filters.finance_status_ids"
                                @change="fetchItems(1)" min-width="190px" />
                        </th>
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
                            <input v-model="filters.gnz" @input="debounceFetch" type="text" class="form-control-table"
                                placeholder="ГРЗ" />
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
                        :style="getRowBackgroundStyle(item)" @click="openApplication(item.id)">
                        <!-- 1. Индекс (#) с ярко-красной заливкой при срочности (< 24ч) -->
                        <td class="col-num text-center fw-bold" :class="{ 'urgent-cell-red': isItemUrgent(item) }">
                            <div class="cell-truncate">{{ (pagination.current_page - 1) * 50 + (idx + 1) }}</div>
                        </td>

                        <!-- 2. Дог -->
                        <td class="col-dog text-center p-0">
                            <span v-if="item.dogovor" class="dog-badge dog-yes">да</span>
                            <span v-else class="dog-badge dog-no">нет</span>
                        </td>

                        <!-- 3. СТАТУС ДЕЛА -->
                        <td class="col-status text-center p-1">
                            <div class="status-compact-pill cell-truncate" :style="{
                                backgroundColor: item.status?.bg_color || item.status_theme || '#fef08a',
                                color: item.status?.text_color || '#1e293b',
                            }" @mouseenter="handleStatusMouseEnter($event, item, 'app')"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.status?.name || item.StatusName || 'Без статуса' }}
                            </div>
                        </td>

                        <!-- 4. СОГЛ/ОПЛ (ФИН. СТАТУС ТОЛЬКО В НАПРАВЛЕНИЯХ) -->
                        <td v-if="currentTab === 'applications'" class="col-soglas text-center p-1">
                            <!-- 1. Если фин. статус задан и НЕ "Не определен" — выводим плашку -->
                            <div v-if="hasActiveFinanceStatus(item)" class="status-compact-pill cell-truncate" :style="{
                                backgroundColor: item.finance_status?.bg_color || item.financeStatus?.bg_color || item.soglas_bg || '#dcfce7',
                                color: item.finance_status?.text_color || item.financeStatus?.text_color || item.soglas_color || '#15803d',
                            }" @mouseenter="handleStatusMouseEnter($event, item, 'fin')"
                                @mouseleave="handleCellMouseLeave">
                                {{ getFinanceStatusTitle(item) }}
                            </div>
                            <!-- Если "Не определен" — ставим серый прочерк -->
                            <span v-else class="text-muted small">—</span>
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

                        <!-- 10. ЮРИСТ (4. Фамилия, зеленый/красный бейдж, прочерк) -->
                        <td class="col-lawyer text-center" @click.stop>
                            <ApplicationLawyerBadge :item="item" @hover="handleCellMouseEnter"
                                @leave="handleCellMouseLeave" />
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

                        <!-- Телефон (всегда целиком) -->
                        <td class="col-phone text-nowrap">
                            <a v-if="item.ClientPhone" :href="'tel:' + item.ClientPhone"
                                class="text-decoration-none text-dark fw-medium text-nowrap"
                                style="font-size: 0.72rem; letter-spacing: -0.2px;" @click.stop>
                                {{ item.ClientPhone }}
                            </a>
                            <span v-else class="text-muted">—</span>
                        </td>

                        <!-- 14. Авто -->
                        <td class="col-car">
                            <div class="cell-truncate text-uppercase fw-medium"
                                @mouseenter="handleCellMouseEnter($event, item.CarModel)"
                                @mouseleave="handleCellMouseLeave">
                                {{ item.CarModel || '—' }}
                            </div>
                        </td>

                        <!-- ГРЗ в таблице -->
                        <td class="col-gnz text-center p-0">
                            <div class="plate-ru-container text-nowrap">
                                <div class="plate-ru flex-shrink-0">
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

                        <!-- Финансовые колонки -->
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

                        <!-- Корзина -->
                        <td class="col-trash text-center p-0" @click.stop>
                            <button class="btn btn-link btn-sm text-secondary p-0" @click="moveToTrash(item)"><i
                                    class="bi bi-trash3"></i></button>
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
                        <td :colspan="currentTab === 'applications' ? 27 : (hasFinancialColumns ? 26 : 19)"
                            class="text-center py-5 text-muted">
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
                    <span class="input-group-text bg-light text-muted border-end-0 py-0"><i
                            class="bi bi-search"></i></span>
                    <input v-model="filters.CarModel" @input="debounceFetch" type="text"
                        class="form-control border-start-0 ps-0" placeholder="Поиск по авто, клиенту, ГНЗ..." />
                    <button v-if="filters.CarModel" class="btn btn-outline-secondary py-0"
                        @click="filters.CarModel = ''; fetchItems(1)"><i class="bi bi-x"></i></button>
                </div>

                <button class="btn btn-sm mob-filter-btn d-flex align-items-center text-nowrap"
                    :class="{ 'btn-warning text-dark fw-bold': activeFiltersCount > 0, 'btn-outline-dark': activeFiltersCount === 0 }"
                    @click="isMobileFilterOpen = true">
                    <i class="bi bi-funnel-fill me-1"></i>
                    <span>Фильтры</span>
                    <span v-if="activeFiltersCount > 0" class="badge bg-dark text-white rounded-pill ms-1"
                        style="font-size: 0.65rem;">{{ activeFiltersCount }}</span>
                </button>

                <button v-if="activeFiltersCount > 0" class="btn btn-sm btn-outline-danger p-1" @click="resetAllFilters"
                    title="Сбросить все фильтры">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- 3. МОБИЛЬНЫЙ СПИСОК ПЛИТОК/КАРТОЧЕК (< 992px) -->
        <div class="mobile-registry-list d-lg-none px-1 py-1">
            <div v-for="item in items" :key="'mob-' + item.id"
                class="mob-deal-card mb-2 bg-white border rounded shadow-sm p-2 position-relative overflow-hidden"
                :class="{
                    'mob-row-overdue': item.is_overdue || item.app_timer?.is_overdue,
                    'mob-row-urgent': isItemUrgent(item),
                }" :style="getRowBackgroundStyle(item)" @click="openApplication(item.id)">
                <!-- 1 СТРОКА: РАЗДВИНУТЫ #ID, МАРКА И ГОСНОМЕР + ПЛАШКИ СТАТУСОВ ДРУГ ПОД ДРУГОМ -->
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                    <!-- Левая часть: ID + Авто + ГРЗ -->
                    <div class="d-flex flex-column gap-1 min-w-0 flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-dark font-monospace flex-shrink-0">#{{ item.id }}</span>
                            <span class="fw-bold text-dark text-truncate mob-car-title" :title="item.CarModel">
                                {{ item.CarModel || 'Авто не указано' }}
                            </span>
                        </div>
                        <div v-if="item.gnz">
                            <span class="mob-plate-badge text-nowrap">{{ item.gnz }}</span>
                        </div>
                    </div>

                    <!-- Правая часть: Статус дела + под ним Фин. статус (если задан) -->
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        <!-- Основной статус -->
                        <span class="status-compact-pill text-truncate px-2 py-0.5 text-center" :style="{
                            backgroundColor: item.status?.bg_color || item.status_theme || '#fef08a',
                            color: item.status?.text_color || '#1e293b',
                            maxWidth: '135px',
                        }">
                            {{ item.status?.name || item.StatusName || 'Без статуса' }}
                        </span>

                        <!-- 1. Фин. статус ТОЛЬКО если задан и НЕ "Не определен" -->
                        <span v-if="hasActiveFinanceStatus(item)"
                            class="status-compact-pill text-truncate px-2 py-0.5 text-center" :style="{
                                backgroundColor: item.finance_status?.bg_color || item.financeStatus?.bg_color || item.soglas_bg || '#dcfce7',
                                color: item.finance_status?.text_color || item.financeStatus?.text_color || item.soglas_color || '#15803d',
                                maxWidth: '135px',
                            }">
                            {{ getFinanceStatusTitle(item) }}
                        </span>
                    </div>
                </div>

                <!-- 2 СТРОКА: КЛИЕНТ (ПОЛНОЕ ФИО) -->
                <div class="mob-client-row text-dark mb-1">
                    <i class="bi bi-person text-secondary me-1"></i>
                    <span class="fw-semibold mob-client-name text-uppercase">
                        {{ item.ClientName || 'Клиент не указан' }}
                    </span>
                </div>

                <!-- 3 СТРОКА: ТЕЛЕФОН КЛИЕНТА (ЗВОНОК В 1 ТАП) -->
                <div class="mob-phone-row mb-1 pb-1 border-bottom" v-if="item.ClientPhone">
                    <a :href="'tel:' + item.ClientPhone"
                        class="mob-phone-link d-inline-flex align-items-center text-decoration-none text-primary fw-medium"
                        @click.stop>
                        <i class="bi bi-telephone-fill me-1" style="font-size: 0.70rem;"></i>
                        <span>{{ item.ClientPhone }}</span>
                    </a>
                </div>

                <!-- 4 СТРОКА: ЮРИСТ (МОБИЛЬНЫЙ ВИД) -->
                <div v-if="item.lawyer || item.id_lawyer" class="d-flex align-items-center gap-1 mb-1"
                    style="font-size: 0.72rem;">
                    <span class="text-muted">Юрист:</span>
                    <ApplicationLawyerBadge :item="item" />
                </div>

                <!-- 5 СТРОКА: СК, СТОА, ДАТА -->
                <div class="mob-details-grid d-flex flex-wrap align-items-center justify-content-between gap-1 text-muted"
                    style="font-size: 0.68rem;">
                    <div class="text-nowrap">
                        <span v-if="item.numberInsuranceCompanyApplication || item.ub_number" class="text-secondary">
                            Убыток: {{ item.numberInsuranceCompanyApplication || item.ub_number }}
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

                <!-- Финансовый блок карточки -->
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
                    <!-- Статус дела -->
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

                    <!-- Фин. статус -->
                    <div class="mb-3" v-if="currentTab === 'applications'">
                        <label class="form-label small fw-bold mb-1 text-muted">Согл/Опл (Фин. статус)</label>
                        <div class="mob-chip-list">
                            <span v-for="fst in financeStatusOptions" :key="fst.value" class="mob-chip"
                                :class="{ active: filters.finance_status_ids.includes(fst.value) }"
                                @click="toggleMobileArrayFilter('finance_status_ids', fst.value)">
                                {{ fst.label }}
                            </span>
                        </div>
                    </div>

                    <!-- Сотрудник (МРК) -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Сотрудник (МРК)</label>
                        <select v-model="filters.mrk_ids" class="form-select form-select-sm" multiple
                            style="height: 90px;">
                            <option v-for="emp in employeeOptions" :key="emp.value" :value="emp.value">
                                {{ emp.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Город -->
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

                    <!-- Страховая компания -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1 text-muted">Страховая компания (СК)</label>
                        <select v-model="filters.insurance_ids" class="form-select form-select-sm" multiple
                            style="height: 90px;">
                            <option v-for="ic in insuranceOptions" :key="ic.value" :value="ic.value">
                                {{ ic.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Текстовые поля -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1 text-muted">ГРЗ (номер)</label>
                            <input v-model="filters.gnz" type="text" class="form-control form-control-sm text-uppercase"
                                placeholder="А001АА29" />
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1 text-muted">№ убытка</label>
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

        <!-- 6. ПАРЯЩИЙ БЕЛЫЙ ТУЛТИП -->
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
import ApplicationLawyerBadge from '../../components/applications/ApplicationLawyerBadge.vue';
import { useRowStyles } from '../../composables/useRowStyles';

const route = useRoute();
const router = useRouter();

const currentTab = computed(() => route.name || 'requests');
// Финансовые суммы СК / З/Ч / Ремонт / Остаток отображаются в Направлениях и Архиве
const hasFinancialColumns = computed(() => ['applications', 'archive'].includes(currentTab.value));

const items = ref([]);
const loading = ref(false);

const options = reactive({
    statuses: [],
    finance_statuses: [],
    employees: [],
    cities: [],
    branches: [],
    insurance_companies: [],
});

const STORAGE_KEY = 'mandarin_global_registry_filters';

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

const {
    getFinanceStatusTitle,
    hasActiveFinanceStatus,
    isItemUrgent,
    getRowBackgroundStyle,
} = useRowStyles();

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

// ================= ОПЦИИ СЕЛЕКТОВ =================
const statusOptions = computed(() => (options.statuses || []).map((s) => ({ value: s.id, label: s.name || `Статус #${s.id}` })));
const financeStatusOptions = computed(() => (options.finance_statuses || []).map((f) => ({ value: f.id, label: f.name || `Фин. статус #${f.id}` })));
const employeeOptions = computed(() => (options.employees || []).map((e) => ({ value: e.id, label: e.name || `Сотрудник #${e.id}` })));
const cityOptions = computed(() => (options.cities || []).map((c) => ({ value: c.id, label: c.name || `Город #${c.id}` })));
const branchOptions = computed(() => (options.branches || []).map((b) => ({ value: b.id, label: b.name || `Филиал #${b.id}` })));
const insuranceOptions = computed(() => (options.insurance_companies || []).map((i) => ({ value: i.id, label: i.name || `СК #${i.id}` })));

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
let timer = null;

// ================= ТУЛТИП =================
const tooltip = reactive({ visible: false, text: '', x: 0, y: 0 });

const handleCellMouseEnter = (event, fullText) => {
    const el = event.currentTarget;
    if (!el || !fullText) return;
    const rect = el.getBoundingClientRect();
    tooltip.text = String(fullText);
    let top = rect.top - 28;
    let left = rect.left + rect.width / 2;
    if (top < 10) top = rect.bottom + 6;
    tooltip.x = left;
    tooltip.y = top;
    tooltip.visible = true;
};

// 5. Корректное всплывающее окно для основного и финансового статуса
const handleStatusMouseEnter = (event, item, type = 'app') => {
    const el = event.currentTarget;
    if (!el || !item) return;
    const rect = el.getBoundingClientRect();

    if (type === 'app') {
        const name = item.status?.name || item.StatusName || 'Статус';
        if (item.app_timer?.is_overdue || item.is_overdue) {
            tooltip.text = `${name}: просрочено`;
        } else if (item.app_timer?.display_text) {
            tooltip.text = `${name}: ${item.app_timer.display_text}`;
        } else {
            const isDay = item.app_timer?.is_day !== false && item.app_timer?.is_day !== 0;
            const unit = isDay ? 'дней' : 'часов';
            const count = item.app_timer?.count_hour ?? item.count_hour ?? 0;
            const max = item.app_timer?.max_hour ?? item.max_hour ?? 0;
            tooltip.text = max > 0 ? `${name}: ${count} из ${max} ${unit}` : `${name}: ${count} ${unit}`;
        }
    } else {
        // Фин. статус
        const name = getFinanceStatusTitle(item) || 'Фин. статус';
        if (item.finance_timer?.is_overdue || item.soglas_overdue) {
            tooltip.text = `${name}: просрочено`;
        } else if (item.finance_timer?.display_text) {
            tooltip.text = `${name}: ${item.finance_timer.display_text}`;
        } else {
            const isDay = item.finance_timer?.is_day === true || item.finance_timer?.is_day === 1;
            const unit = isDay ? 'дней' : 'часов';
            const count = item.finance_timer?.count_hour_f ?? item.count_hour_f ?? 0;
            const max = item.finance_timer?.max_hour_f ?? item.max_hour_f ?? 0;
            tooltip.text = max > 0 ? `${name}: ${count} из ${max} ${unit}` : `${name}: ${count} ${unit}`;
        }
    }

    let top = rect.top - 28;
    if (top < 10) top = rect.bottom + 6;
    tooltip.x = rect.left + rect.width / 2;
    tooltip.y = top;
    tooltip.visible = true;
};

const handleCellMouseLeave = () => {
    tooltip.visible = false;
};

// ================= МОБИЛЬНАЯ ШТОРКА =================
const isMobileFilterOpen = ref(false);

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

const toggleMobileArrayFilter = (field, value) => {
    const current = [...filters[field]];
    const idx = current.indexOf(value);
    if (idx > -1) current.splice(idx, 1);
    else current.push(value);
    filters[field] = current;
};

const applyMobileFilters = () => {
    isMobileFilterOpen.value = false;
    fetchItems(1);
};

// ================= ЗАГРУЗКА ДАННЫХ =================
const fetchFilterOptions = async () => {
    try {
        const res = await axios.get('/api/applications/filters');
        if (res.data) {
            options.statuses = res.data.statuses || [];
            options.finance_statuses = res.data.finance_statuses || res.data.soglas_statuses || [];
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
            finance_status_ids: filters.finance_status_ids.length ? filters.finance_status_ids.join(',') : undefined,
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
        console.error('Ошибка загрузки дел:', err);
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
        filters.finance_status_ids = [];
        saveFilters();
        fetchItems(1);
    }
);

onMounted(async () => {
    loadSavedFilters();
    await fetchFilterOptions();
    fetchItems(1);
});

// Разбор ГРЗ на номер и регион
const parseGnz = (raw) => {
    if (!raw) return { number: '', region: '' };
    const str = String(raw).trim().toUpperCase().replace(/\s+/g, '');
    const match = str.match(/^([АВЕКМНОРСТУХABEKMHOPCTYX]\d{3}[АВЕКМНОРСТУХABEKMHOPCTYX]{2})(\d{2,3})$/i);
    if (match) {
        const numPart = match[1];
        return {
            number: `${numPart[0]} ${numPart.slice(1, 4)} ${numPart.slice(4, 6)}`,
            region: match[2],
        };
    }
    return { number: raw, region: '' };
};
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

.table-mandarin {
    table-layout: fixed !important;
    width: max-content;
    min-width: 100%;
    border-collapse: collapse !important;
    border: 1px solid #000000 !important;
    letter-spacing: -0.01em;
}

.table-mandarin.table-fit {
    width: 100% !important;
    max-width: 100% !important;
}

.table-mandarin thead .header-blue th {
    background-color: #299bd1 !important;
    color: #ffffff !important;
    font-size: 0.70rem !important;
    font-weight: 600 !important;
    text-align: center;
    padding: 4px 2px !important;
    border: 1px solid #000000 !important;
    white-space: nowrap;
}

.table-mandarin thead .header-filters th {
    background-color: #299bd1 !important;
    padding: 2px 2px !important;
    border: 1px solid #000000 !important;
}

:deep(.form-control-table),
.form-control-table {
    width: 100%;
    font-size: 0.68rem !important;
    padding: 1px 3px !important;
    height: 20px !important;
    line-height: 1.2;
    border: 1px solid #000000;
    border-radius: 2px;
    background-color: #ffffff;
    outline: none;
}

.table-mandarin td {
    padding: 2px 3px !important;
    font-size: 0.70rem !important;
    line-height: 1.2;
    border: 1px solid #000000 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    color: #1e293b;
    background-color: transparent !important;
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

.urgent-cell-red {
    background-color: #dc2626 !important;
    color: #ffffff !important;
}

.status-compact-pill {
    display: block;
    width: 100%;
    font-size: 0.64rem !important;
    font-weight: 600;
    padding: 1px 3px;
    border-radius: 2px;
    line-height: 1.2;
    text-align: center;
    border: 1px solid #000000;
    cursor: pointer;
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
    width: 92px;
    min-width: 85px;
    max-width: 100px;
}

.col-soglas {
    width: 92px;
    min-width: 85px;
    max-width: 100px;
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
    width: 65px;
    min-width: 55px;
    max-width: 80px;
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
    width: 122px !important;
    min-width: 122px !important;
    max-width: 130px !important;
    white-space: nowrap !important;
}

.col-car {
    width: 95px;
    min-width: 85px;
    max-width: 115px;
}

.col-gnz {
    width: 90px !important;
    min-width: 90px !important;
    max-width: 96px !important;
    white-space: nowrap !important;
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
    transition: filter 0.12s ease;
}

.data-row:hover {
    filter: brightness(0.95);
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

.cell-gnz-plate {
    display: inline-block;
    background-color: #ffffff;
    color: #000000;
    border: 1px solid #000000;
    border-radius: 3px;
    padding: 0 4px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.pagination-bar {
    position: sticky;
    bottom: 0;
    z-index: 5;
    border-top: 1px solid #000000 !important;
}

/* ========================================================= */
/* МОБИЛЬНЫЙ ВИД (< 992px)                                   */
/* ========================================================= */
.mob-filter-btn {
    height: 31px;
    font-size: 0.75rem;
}

.mob-deal-card {
    cursor: pointer;
    border: 1px solid #000000 !important;
    border-left: 5px solid #299bd1 !important;
    transition: transform 0.08s ease;
    line-height: 1.25;
}

.mob-deal-card:active {
    transform: scale(0.99);
}

.mob-row-urgent {
    border-left-color: #dc2626 !important;
    box-shadow: 0 0 0 1px #dc2626 !important;
}

.mob-row-overdue {
    border-left-color: #b91c1c !important;
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

.mob-phone-link {
    font-size: 0.72rem;
    padding: 1px 6px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 4px;
}

/* МОБИЛЬНАЯ ШТОРКА ФИЛЬТРОВ */
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
/* ГЛОБАЛЬНЫЙ ПАРЯЩИЙ ТУЛТИП */
.floating-cell-tooltip {
    position: fixed !important;
    transform: translateX(-50%) !important;
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

.plate-ru {
    display: inline-flex;
    align-items: center;
    background-color: #ffffff;
    color: #111827;
    border: 1.5px solid #1f2937;
    border-radius: 3px;
    height: 20px;
    padding: 0 3px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
    user-select: none;
    line-height: 1;
    flex-shrink: 0;
    white-space: nowrap;
}

.plate-number {
    font-size: 0.70rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    padding: 0 3px 0 1px;
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
    min-width: 18px;
}

.plate-region {
    font-size: 0.60rem;
    font-weight: 800;
    line-height: 1;
    color: #000000;
    letter-spacing: -0.2px;
}
</style>