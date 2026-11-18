<template>
    <div class="admin-dashboard-page">
        <!-- 1. КОМПАКТНАЯ АДАПТИВНАЯ ШАПКА -->
        <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
            <div class="min-w-0">
                <h5 class="mb-0 fw-bold text-dark d-flex align-items-center text-truncate">
                    <i class="bi bi-shield-lock-fill text-primary me-2 flex-shrink-0"></i>
                    <span>Администрирование</span>
                </h5>
                <small class="text-muted d-none d-sm-inline">
                    Управление пользователями, филиалами, юр. лицами и справочниками
                </small>
            </div>

            <!-- Главная кнопка создания -->
            <button class="btn btn-primary btn-sm px-3 d-flex align-items-center flex-shrink-0 shadow-sm"
                @click="handleCreateNew">
                <i class="bi bi-plus-lg me-1"></i>
                <span>{{ createButtonText }}</span>
            </button>
        </div>

        <!-- 2. ВКЛАДКИ-ЧИПСЫ С ГОРИЗОНТАЛЬНЫМ СКРОЛЛОМ ДЛЯ СМАРТФОНОВ -->
        <!-- 2. ВКЛАДКИ-ЧИПСЫ С ГОРИЗОНТАЛЬНЫМ СКРОЛЛОМ ДЛЯ ДЕСКТОПА И МОБИЛЬНЫХ -->
        <div class="admin-tabs-nav-container position-relative mb-3">
            <!-- Кнопка прокрутки влево (Десктоп) -->
            <!-- <button v-show="canScrollLeft" type="button"
                class="scroll-arrow-btn left d-none d-md-flex align-items-center justify-content-center"
                @click="scrollTabs('left')" title="Прокрутить влево">
                <i class="bi bi-chevron-left"></i>
            </button> -->

            <!-- Прокручиваемый контейнер -->
            <div ref="tabsScrollRef" class="admin-tabs-wrapper py-1" @scroll="handleTabsScroll" @wheel="handleTabsWheel"
                @mousedown="startTabsDrag" @mousemove="onTabsDrag" @mouseup="stopTabsDrag" @mouseleave="stopTabsDrag">
                <div class="d-flex gap-2 flex-nowrap align-items-center">
                    <!-- 1. Пользователи -->
                    <button class="tab-chip" :class="{ active: currentTab === 'users' }" @click="setTab('users')">
                        <i class="bi bi-people-fill me-1"></i>
                        <span>Пользователи</span>
                        <span class="chip-count">{{ pagination.total || users.length }}</span>
                    </button>

                    <!-- 2. Юр. лица -->
                    <button class="tab-chip" :class="{ active: currentTab === 'legal-entities' }"
                        @click="setTab('legal-entities')">
                        <i class="bi bi-briefcase-fill me-1"></i>
                        <span>Юр. лица</span>
                        <span class="chip-count">{{ legalEntities.length }}</span>
                    </button>

                    <!-- 3. Наши филиалы -->
                    <button class="tab-chip" :class="{ active: currentTab === 'our-companies' }"
                        @click="setTab('our-companies')">
                        <i class="bi bi-shop me-1"></i>
                        <span>Наши филиалы</span>
                        <span class="chip-count">{{ ourCompanies.length }}</span>
                    </button>

                    <!-- 4. Автосервисы (СТО) -->
                    <button class="tab-chip" :class="{ active: currentTab === 'services' }" @click="setTab('services')">
                        <i class="bi bi-tools me-1"></i>
                        <span>Автосервисы (СТО)</span>
                        <span class="chip-count">{{ servicesList.length }}</span>
                    </button>

                    <!-- 5. Города -->
                    <button class="tab-chip" :class="{ active: currentTab === 'cities' }" @click="setTab('cities')">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        <span>Города</span>
                        <span class="chip-count">{{ cities.length }}</span>
                    </button>

                    <!-- 6. Страховые компании -->
                    <button class="tab-chip" :class="{ active: currentTab === 'companies' }"
                        @click="setTab('companies')">
                        <i class="bi bi-building me-1"></i>
                        <span>Страховые</span>
                        <span class="chip-count">{{ companies.length }}</span>
                    </button>

                    <!-- 7. Роли -->
                    <button class="tab-chip" :class="{ active: currentTab === 'groups' }" @click="setTab('groups')">
                        <i class="bi bi-person-badge-fill me-1"></i>
                        <span>Роли</span>
                        <span class="chip-count">{{ groups.length }}</span>
                    </button>

                    <!-- 8. Статусы заявок -->
                    <button class="tab-chip" :class="{ active: currentTab === 'statuses' }" @click="setTab('statuses')">
                        <i class="bi bi-tags-fill me-1"></i>
                        <span>Статусы заявок</span>
                        <span class="chip-count">{{ statuses.length }}</span>
                    </button>

                    <!-- 9. Финансовые статусы (если есть) -->
                    <button v-if="financeStatuses" class="tab-chip"
                        :class="{ active: currentTab === 'finance-statuses' }" @click="setTab('finance-statuses')">
                        <i class="bi bi-cash-stack me-1"></i>
                        <span>Фин. статусы</span>
                        <span class="chip-count">{{ financeStatuses.length }}</span>
                    </button>

                    <!-- 10. Производственный календарь -->
                    <button class="tab-chip" :class="{ active: currentTab === 'calendar' }" @click="setTab('calendar')">
                        <i class="bi bi-calendar3 me-1"></i>
                        <span>Производственный календарь</span>
                        <span class="chip-count">{{ calendarExceptions.length }}</span>
                    </button>
                </div>
            </div>

            <!-- Кнопка прокрутки вправо (Десктоп) -->
            <!-- <button v-show="canScrollRight" type="button"
                class="scroll-arrow-btn right d-none d-md-flex align-items-center justify-content-center"
                @click="scrollTabs('right')" title="Прокрутить вправо">
                <i class="bi bi-chevron-right"></i>
            </button> -->
        </div>

        <!-- 3. ВКЛАДКА: ПОЛЬЗОВАТЕЛИ -->
        <div v-if="currentTab === 'users'" class="content-block">
            <!-- Панель поиска и фильтрации -->
            <div class="card border-0 shadow-sm p-3 mb-3 bg-white">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-6 col-lg-5">
                        <div class="input-group input-group-sm search-input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input v-model="userSearchQuery" @input="debounceFetchUsers" type="text"
                                class="form-control border-start-0" placeholder="Поиск по ФИО, email, телефону..." />
                            <button v-if="userSearchQuery" class="btn btn-outline-secondary border-start-0"
                                @click="clearSearch">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <select v-model="selectedGroupFilter" @change="fetchUsers(1)"
                            class="form-select form-select-sm">
                            <option value="">Все роли</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 text-end text-muted small">
                        Найдено: <strong>{{ pagination.total || users.length }}</strong>
                    </div>
                </div>
            </div>

            <!-- Десктоп/Планшет: Таблица пользователей -->
            <div class="card border-0 shadow-sm d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>ФИО</th>
                                <th>Email / Логин</th>
                                <th>Телефон</th>
                                <th>Роль</th>
                                <th>Город</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td class="text-muted">#{{ u.id }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ u.full_name || u.name }}</div>
                                    <small class="text-muted" v-if="u.short_name && u.short_name !== u.full_name">
                                        {{ u.short_name }}
                                    </small>
                                </td>

                                <td>
                                    <a :href="'mailto:' + u.email" class="text-decoration-none text-dark">{{ u.email
                                    }}</a>
                                    <div class="small text-muted">{{ u.phone || '—' }}</div>
                                </td>
                                <td>
                                    <!-- Филиалы и роли в них -->
                                    <div class="d-flex flex-wrap gap-1" v-if="u.branch_roles && u.branch_roles.length">
                                        <span v-for="br in u.branch_roles" :key="br.id"
                                            class="badge bg-light text-dark border"
                                            :title="'Роль: ' + (br.role?.name || '')">
                                            <i class="bi bi-shop me-1 text-primary"></i>
                                            {{ br.branch?.name }}: <strong>{{ br.role?.name }}</strong>
                                        </span>
                                    </div>
                                    <span v-else class="text-muted small">Не прикреплен</span>
                                </td>
                                <td>
                                    <!-- Закрепленные города -->
                                    <div class="d-flex flex-wrap gap-1" v-if="u.cities && u.cities.length">
                                        <span v-for="c in u.cities" :key="c.id"
                                            class="badge bg-secondary-subtle text-secondary border">
                                            {{ c.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="openUserModal(u)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить" @click="deleteUser(u)"
                                            :disabled="authStore.user?.id === u.id">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">Пользователи не найдены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Пагинация для десктопа -->
                <div v-if="pagination.last_page > 1"
                    class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted">
                        Страница {{ pagination.current_page }} из {{ pagination.last_page }}
                    </small>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                            <button class="page-link" @click="fetchUsers(pagination.current_page - 1)">&laquo;</button>
                        </li>
                        <li v-for="p in pagination.last_page" :key="p" class="page-item"
                            :class="{ active: pagination.current_page === p }">
                            <button class="page-link" @click="fetchUsers(p)">{{ p }}</button>
                        </li>
                        <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                            <button class="page-link" @click="fetchUsers(pagination.current_page + 1)">&raquo;</button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Мобильный вид (карточки) -->
            <div class="d-md-none">
                <div v-for="u in users" :key="'mob-u-' + u.id"
                    class="card border-0 shadow-sm mb-2 p-3 user-mobile-card">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 pb-2 border-bottom">
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate">{{ u.full_name || u.name }}</div>
                            <small class="text-muted">ID: #{{ u.id }}</small>
                        </div>
                        <span class="badge bg-secondary flex-shrink-0">{{ u.group?.name || 'Без группы' }}</span>
                    </div>
                    <div class="small mb-3">
                        <div class="text-truncate mb-1">
                            <i class="bi bi-envelope text-muted me-2"></i>{{ u.email }}
                        </div>
                        <div v-if="u.phone" class="mb-1">
                            <i class="bi bi-telephone text-muted me-2"></i>{{ u.phone }}
                        </div>
                        <div v-if="u.city?.name">
                            <i class="bi bi-geo-alt text-muted me-2"></i>{{ u.city.name }}
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-light border btn-sm flex-grow-1 py-2" @click="openUserModal(u)">
                            <i class="bi bi-pencil me-1"></i> Изменить
                        </button>
                        <button class="btn btn-outline-danger btn-sm flex-grow-1 py-2" @click="deleteUser(u)"
                            :disabled="authStore.user?.id === u.id">
                            <i class="bi bi-trash me-1"></i> Удалить
                        </button>
                    </div>
                </div>

                <div v-if="users.length === 0" class="text-center py-5 text-muted bg-white rounded shadow-sm">
                    Пользователи не найдены
                </div>

                <!-- Мобильная пагинация -->
                <div v-if="pagination.last_page > 1"
                    class="d-flex justify-content-between align-items-center mt-3 px-1">
                    <button class="btn btn-sm btn-light border" :disabled="pagination.current_page === 1"
                        @click="fetchUsers(pagination.current_page - 1)">
                        &larr; Назад
                    </button>
                    <small class="text-muted">{{ pagination.current_page }} / {{ pagination.last_page }}</small>
                    <button class="btn btn-sm btn-light border"
                        :disabled="pagination.current_page === pagination.last_page"
                        @click="fetchUsers(pagination.current_page + 1)">
                        Вперед &rarr;
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. ВКЛАДКА: ЮРИДИЧЕСКИЕ ЛИЦА -->
        <div v-if="currentTab === 'legal-entities'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Наименование</th>
                                <th>ИНН / КПП</th>
                                <th>Руководитель</th>
                                <th>Контакты</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in legalEntities" :key="item.id">
                                <td class="text-muted">#{{ item.id }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ item.name }}</div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                        {{ item.full_name }}
                                    </small>
                                </td>
                                <td>
                                    <div>ИНН: {{ item.inn || '—' }}</div>
                                    <small class="text-muted">КПП: {{ item.kpp || '—' }}</small>
                                </td>
                                <td>{{ item.director_name || '—' }}</td>
                                <td>
                                    <div>{{ item.phone || '—' }}</div>
                                    <small class="text-muted">{{ item.email || '' }}</small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="legalEntityModalRef?.open(item)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteLegalEntity(item)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="legalEntities.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">Юридические лица еще не добавлены
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5. ВКЛАДКА: НАШИ ФИЛИАЛЫ -->
        <div v-if="currentTab === 'our-companies'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Название филиала</th>
                                <th>Email</th>
                                <th>Mailer</th>
                                <th>Юр. лицо</th>
                                <th>Подпись (Sign)</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in ourCompanies" :key="c.id">
                                <td class="text-muted">#{{ c.id }}</td>
                                <td class="fw-bold text-dark">{{ c.name }}</td>
                                <td>
                                    <a :href="'mailto:' + c.email" class="text-decoration-none text-dark">
                                        {{ c.email }}
                                    </a>
                                </td>
                                <td><code>{{ c.mailer || '—' }}</code></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ c.legal_entity?.name || 'Не привязано' }}
                                    </span>
                                </td>
                                <td>{{ c.address || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="ourCompanyModalRef?.open(c)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteOurCompany(c)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="ourCompanies.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">Филиалы еще не добавлены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 6. ВКЛАДКА: ГОРОДА -->
        <div v-if="currentTab === 'cities'" class="content-block">
            <div class="card border-0 shadow-sm" style="max-width: 600px;">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Город</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="city in cities" :key="city.id">
                                <td class="text-muted">#{{ city.id }}</td>
                                <td class="fw-semibold text-dark">{{ city.name }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="cityModalRef?.open(city)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteCity(city)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="cities.length === 0">
                                <td colspan="3" class="text-center py-4 text-muted">Города еще не добавлены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: СТРАХОВЫЕ КОМПАНИИ -->
        <div v-if="currentTab === 'companies'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Наименование СК</th>
                                <th>Тема бейджа</th>
                                <th>Куратор</th>
                                <th>Контакты</th>
                                <th>Адрес</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="comp in companies" :key="comp.id">
                                <td class="text-muted">#{{ comp.id }}</td>
                                <td class="fw-bold text-dark">{{ comp.name }}</td>
                                <td>
                                    <span class="badge px-2 py-1 fw-bold shadow-sm" :style="{
                                        backgroundColor: comp.bg_color || '#fce7f3',
                                        color: comp.text_color || '#be185d',
                                        border: '1px solid ' + (comp.text_color || '#be185d') + '33'
                                    }">
                                        {{ comp.name }}
                                    </span>
                                </td>
                                <td>{{ comp.curator || comp.manager || '—' }}</td>
                                <td>
                                    <div>{{ comp.phone || '—' }}</div>
                                    <small class="text-muted">{{ comp.email || '' }}</small>
                                </td>
                                <td>{{ comp.address || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать тему и данные"
                                            @click="insuranceCompanyModalRef?.open(comp)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteCompany(comp)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="companies.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">Страховые компании еще не добавлены
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: АВТОСЕРВИСЫ (СТО) -->
        <div v-if="currentTab === 'services'" class="content-block">
            <!-- Панель поиска и создания -->
            <div class="card border-0 shadow-sm p-3 mb-3 bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 280px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                            <input v-model="servicesSearch" @input="fetchServices" type="text"
                                class="form-control border-start-0"
                                placeholder="Поиск по названию, телефону, адресу..." />
                        </div>

                        <select v-model="servicesBranchFilter" @change="fetchServices"
                            class="form-select form-select-sm w-auto">
                            <option value="">Все филиалы</option>
                            <option v-for="b in ourCompanies" :key="b.id" :value="b.id">{{ b.our_name || b.name }}
                            </option>
                        </select>
                    </div>

                    <button class="btn btn-primary btn-sm px-3 fw-semibold" @click="serviceModalRef?.open()">
                        <i class="bi bi-plus-lg me-1"></i> Добавить автосервис
                    </button>
                </div>
            </div>

            <!-- Таблица автосервисов -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Автосервис / СТО</th>
                                <th>Контакты</th>
                                <th>Ответственный (User)</th>
                                <th>Привязанные филиалы</th>
                                <th>Адрес</th>
                                <th style="width: 90px;" class="text-center">Статус</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in servicesList" :key="item.id">
                                <td class="text-muted">#{{ item.id }}</td>
                                <td>
                                    <!-- Превью брендированного бейджа СТО -->
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge px-2 py-1 text-truncate" :style="{
                                            backgroundColor: item.bg_color || '#e0f2fe',
                                            color: item.text_color || '#0369a1',
                                            border: '1px solid rgba(0,0,0,0.1)',
                                            fontWeight: '600',
                                            fontSize: '0.72rem'
                                        }">
                                            {{ item.name }}
                                        </span>
                                    </div>
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;"
                                        :title="item.comment">
                                        {{ item.comment || '—' }}
                                    </small>
                                </td>
                                <td>
                                    <div v-if="item.phone" class="font-monospace small">
                                        <i class="bi bi-telephone me-1 text-muted"></i>{{ item.phone }}
                                    </div>
                                    <div v-if="item.email" class="small text-muted">
                                        <i class="bi bi-envelope me-1"></i>{{ item.email }}
                                    </div>
                                    <span v-if="!item.phone && !item.email" class="text-muted small">—</span>
                                </td>
                                <td>
                                    <span v-if="item.user" class="badge bg-light text-dark border">
                                        <i class="bi bi-person-fill text-primary me-1"></i>
                                        {{ item.user.last_name ? `${item.user.last_name} ${item.user.name}` :
                                            item.user.name }}
                                    </span>
                                    <span v-else class="text-muted small">Не привязан</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1" v-if="item.branches && item.branches.length">
                                        <span v-for="br in item.branches" :key="br.id"
                                            class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            {{ br.our_name || br.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted small">Все филиалы</span>
                                </td>
                                <td>
                                    <span class="small text-muted text-truncate d-inline-block"
                                        style="max-width: 200px;" :title="item.address">
                                        {{ item.address || '—' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="item.is_active"
                                        class="badge bg-success-subtle text-success border border-success">Активен</span>
                                    <span v-else class="badge bg-secondary-subtle text-secondary border">Отключен</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="serviceModalRef?.open(item)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteService(item)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="servicesList.length === 0">
                                <td colspan="8" class="text-center py-5 text-muted">Автосервисы не найдены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Модалка автосервиса -->
        <ServiceModal ref="serviceModalRef" :branches="ourCompanies" :users="users" @saved="fetchServices" />

        <!-- 8. ВКЛАДКА: РОЛИ -->
        <div v-if="currentTab === 'groups'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Наименование</th>
                                <th>Код</th>
                                <th>Описание</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in groups" :key="g.id">
                                <td class="text-muted">#{{ g.id }}</td>
                                <td class="fw-semibold text-dark">{{ g.name }}</td>
                                <td><code>{{ g.code }}</code></td>
                                <td>{{ g.description || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" v-if="g.code !== 'sys_admin'">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="groupModalRef?.open(g)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить" @click="deleteGroup(g)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <span v-else class="badge bg-light text-muted border">Системная</span>
                                </td>
                            </tr>
                            <tr v-if="groups.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">Роли еще не созданы</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 9. ВКЛАДКА: СТАТУСЫ -->
        <!-- ВКЛАДКА: СТАТУСЫ ДЕЛ -->
        <div v-if="currentTab === 'statuses'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Статус</th>
                                <th>Группа / Роль</th>
                                <th>Норматив таймера</th>
                                <th>Заявок</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="st in statuses" :key="st.id">
                                <td class="text-muted fw-bold">#{{ st.id }}</td>
                                <td>
                                    <span class="badge px-2 py-1 fw-semibold shadow-sm" :style="{
                                        backgroundColor: st.bg_color || '#fef08a',
                                        color: st.text_color || '#854d0e',
                                        border: '1px solid ' + (st.text_color || '#854d0e') + '33'
                                    }">
                                        {{ st.name }}
                                    </span>
                                </td>
                                <td>{{ st.group?.name || 'Общий статус' }}</td>
                                <td>
                                    <span v-if="st.time && st.time > 0" class="badge bg-light text-dark border">
                                        <i class="bi bi-clock me-1 text-primary"></i>
                                        {{ st.time }} {{ st.is_day ? 'раб. дн.' : 'ч.' }}
                                    </span>
                                    <span v-else class="text-muted small">Без таймера</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted border">{{ st.applications_count || 0
                                    }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="statusModalRef?.open(st)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteStatus(st)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="statuses.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">Статусы еще не созданы</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- ВКЛАДКА: ФИНАНСОВЫЕ СТАТУСЫ -->
        <div v-if="currentTab === 'finance-statuses'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Статус</th>
                                <th>Тема бейджа</th>
                                <th>Норматив таймера</th>
                                <th>Заявок</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="fst in financeStatuses" :key="fst.id">
                                <td class="text-muted fw-bold">#{{ fst.id }}</td>
                                <td class="fw-semibold text-dark">{{ fst.name }}</td>
                                <td>
                                    <span class="badge px-2 py-1 fw-semibold shadow-sm" :style="{
                                        backgroundColor: fst.bg_color || '#e0f2fe',
                                        color: fst.text_color || '#0369a1',
                                        border: '1px solid ' + (fst.text_color || '#0369a1') + '33'
                                    }">
                                        {{ fst.name }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="fst.time > 0" class="badge bg-light text-dark border">
                                        {{ fst.time }} {{ fst.is_day ? 'раб. дн.' : 'час.' }}
                                    </span>
                                    <span v-else class="text-muted small">Без таймера</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted border">{{ fst.applications_count || 0
                                    }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="financeStatusModalRef?.open(fst)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteFinanceStatus(fst)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="financeStatuses.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">Финансовые статусы еще не созданы
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВКЛАДКА: ПРОИЗВОДСТВЕННЫЙ КАЛЕНДАРЬ -->
        <div v-if="currentTab === 'calendar'" class="content-block">
            <!-- Панель управления годом и автозаполнением -->
            <div class="card border-0 shadow-sm p-3 mb-3 bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="small fw-semibold text-muted">Год:</span>
                        <select v-model="selectedCalendarYear" @change="fetchCalendarExceptions"
                            class="form-select form-select-sm" style="width: 110px;">

                            <option :value="2025">2025</option>
                            <option :value="2026">2026</option>
                            <option :value="2027">2027</option>
                            <option :value="2028">2028</option>
                            <option :value="2029">2029</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" @click="generateDefaultHolidays"
                            title="Заполнить стандартные государственные праздники РФ">
                            <i class="bi bi-magic me-1"></i> Заполнить гос. праздники
                        </button>
                        <button class="btn btn-primary btn-sm"
                            @click="calendarModalRef?.open(null, selectedCalendarYear)">
                            <i class="bi bi-plus-lg me-1"></i> Добавить день
                        </button>
                    </div>
                </div>
            </div>

            <!-- Таблица исключений -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 140px;">Дата</th>
                                <th style="width: 110px;">День недели</th>
                                <th style="width: 200px;">Тип</th>
                                <th>Описание / Праздник</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in calendarExceptions" :key="item.id">
                                <td class="fw-bold font-monospace">{{ formatCalendarDate(item.date) }}</td>
                                <td class="text-muted small">{{ getDayOfWeek(item.date) }}</td>
                                <td>
                                    <span v-if="item.is_workday"
                                        class="badge bg-success-subtle text-success border border-success">
                                        🟢 Рабочая суббота/воскресенье
                                    </span>
                                    <span v-else class="badge bg-danger-subtle text-danger border border-danger">
                                        🔴 Праздник / Выходной
                                    </span>
                                </td>
                                <td class="text-dark">{{ item.comment || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="calendarModalRef?.open(item)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteCalendarException(item)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="calendarExceptions.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-2 d-block mb-1 text-secondary"></i>
                                    На {{ selectedCalendarYear }} год исключений не настроено (действуют стандартные
                                    праздники РФ)
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Модалка календаря -->
        <CalendarModal ref="calendarModalRef" @saved="fetchCalendarExceptions" />


        <!-- ВСЕ МОДАЛЬНЫЕ ОКНА CRUD -->
        <UserModal ref="userModalRef" :groups="groups" :cities="cities" :branches="ourCompanies"
            @saved="fetchUsers(pagination.current_page || 1)" />

        <LegalEntityModal ref="legalEntityModalRef" @saved="fetchLegalEntities" />

        <OurCompanyModal ref="ourCompanyModalRef" :legal-entities="legalEntities" @saved="fetchOurCompanies" />

        <CityModal ref="cityModalRef" @saved="fetchCities" />

        <InsuranceCompanyModal ref="insuranceCompanyModalRef" @saved="fetchInsuranceCompanies" />

        <GroupModal ref="groupModalRef" @saved="fetchGroups" />
        <StatusModal ref="statusModalRef" :groups="groups" @saved="fetchStatuses" />
        <FinanceStatusModal ref="financeStatusModalRef" @saved="fetchFinanceStatuses" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../../stores/auth';

import UserModal from './components/UserModal.vue';
import LegalEntityModal from './components/LegalEntityModal.vue';
import OurCompanyModal from './components/OurCompanyModal.vue';
import CityModal from './components/CityModal.vue';
import InsuranceCompanyModal from './components/InsuranceCompanyModal.vue';
import GroupModal from './components/GroupModal.vue';
import StatusModal from './components/StatusModal.vue';
import CalendarModal from './components/CalendarModal.vue';
import FinanceStatusModal from './components/FinanceStatusModal.vue';
import ServiceModal from './components/ServiceModal.vue';

const tabsScrollRef = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

// Проверка доступности прокрутки влево/вправо
const checkScrollButtons = () => {
    const el = tabsScrollRef.value;
    if (!el) return;
    canScrollLeft.value = el.scrollLeft > 5;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
};

const handleTabsScroll = () => {
    checkScrollButtons();
};

// Прокрутка обычным колесиком мыши по горизонтали
const handleTabsWheel = (e) => {
    const el = tabsScrollRef.value;
    if (!el) return;
    // Если нет зажатого Shift, перенаправляем вертикальное колесо в горизонтальное
    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        e.preventDefault();
        el.scrollLeft += e.deltaY;
        checkScrollButtons();
    }
};

// Прокрутка кнопками-стрелками
const scrollTabs = (direction) => {
    const el = tabsScrollRef.value;
    if (!el) return;
    const offset = direction === 'left' ? -260 : 260;
    el.scrollBy({ left: offset, behavior: 'smooth' });
    setTimeout(checkScrollButtons, 300);
};

// Drag-to-Scroll (перетаскивание ленты мышкой)
let isDragging = false;
let startX = 0;
let scrollStart = 0;

const startTabsDrag = (e) => {
    // Игнорируем нажатие, если кликнули по интерактивному элементу
    isDragging = true;
    startX = e.pageX - tabsScrollRef.value.offsetLeft;
    scrollStart = tabsScrollRef.value.scrollLeft;
};

const onTabsDrag = (e) => {
    if (!isDragging) return;
    e.preventDefault();
    const x = e.pageX - tabsScrollRef.value.offsetLeft;
    const walk = (x - startX) * 1.5;
    tabsScrollRef.value.scrollLeft = scrollStart - walk;
    checkScrollButtons();
};

const stopTabsDrag = () => {
    isDragging = false;
};

onMounted(() => {
    nextTick(() => {
        checkScrollButtons();
        window.addEventListener('resize', checkScrollButtons);
    });
});

onUnmounted(() => {
    window.removeEventListener('resize', checkScrollButtons);
});
const serviceModalRef = ref(null);
const servicesList = ref([]);
const servicesSearch = ref('');
const servicesBranchFilter = ref('');

const fetchServices = async () => {
    try {
        const res = await axios.get('/api/admin/services', {
            params: {
                search: servicesSearch.value || undefined,
                branch_id: servicesBranchFilter.value || undefined,
            }
        });
        servicesList.value = res.data.data || [];
    } catch (err) {
        console.error('Ошибка загрузки сервисов:', err);
    }
};

const deleteService = async (item) => {
    if (confirm(`Удалить автосервис «${item.name}»?`)) {
        try {
            await axios.delete(`/api/admin/services/${item.id}`);
            fetchServices();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить сервис');
        }
    }
};

// При переключении на вкладку 'services' вызываем fetchServices()

const financeStatusModalRef = ref(null);
const financeStatuses = ref([]);

const fetchFinanceStatuses = async () => {
    try {
        const res = await axios.get('/api/admin/finance-statuses');
        // Гарантированно разбираем любой ответ сервера:
        const result = res.data?.data ?? res.data;
        financeStatuses.value = Array.isArray(result) ? result : [];
    } catch (e) {
        console.error('Ошибка загрузки фин. статусов:', e);
        financeStatuses.value = [];
    }
};

const deleteFinanceStatus = async (fst) => {
    if (confirm(`Удалить финансовый статус «${fst.name}»?`)) {
        try {
            await axios.delete(`/api/admin/finance-statuses/${fst.id}`);
            fetchFinanceStatuses();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить фин. статус.');
        }
    }
};
const calendarModalRef = ref(null);
const calendarExceptions = ref([]);
const selectedCalendarYear = ref(new Date().getFullYear());

const fetchCalendarExceptions = async () => {
    try {
        const res = await axios.get('/api/admin/calendar-exceptions', {
            params: { year: selectedCalendarYear.value }
        });
        calendarExceptions.value = res.data.data || [];
    } catch (e) {
        console.error('Ошибка загрузки календаря:', e);
    }
};

const generateDefaultHolidays = async () => {
    if (confirm(`Заполнить стандартные праздники РФ на ${selectedCalendarYear.value} год?`)) {
        try {
            await axios.post('/api/admin/calendar-exceptions/defaults', { year: selectedCalendarYear.value });
            fetchCalendarExceptions();
        } catch (e) {
            alert('Ошибка при генерации праздников');
        }
    }
};

const deleteCalendarException = async (item) => {
    if (confirm(`Удалить исключение на дату ${item.date}?`)) {
        try {
            await axios.delete(`/api/admin/calendar-exceptions/${item.id}`);
            fetchCalendarExceptions();
        } catch (e) {
            alert('Ошибка удаления записи');
        }
    }
};

const formatCalendarDate = (dateStr) => {
    if (!dateStr) return '—';
    const [y, m, d] = dateStr.split('-');
    return `${d}.${m}.${y}`;
};

const getDayOfWeek = (dateStr) => {
    if (!dateStr) return '';
    const days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    const d = new Date(dateStr);
    return days[d.getDay()];
};

// Вызовите fetchCalendarExceptions() в onMounted()


// Добавьте ссылку на модалку:
const statusModalRef = ref(null);

const authStore = useAuthStore();

const currentTab = ref('users');

// Реактивные списки данных
const users = ref([]);
const legalEntities = ref([]);
const ourCompanies = ref([]);
const cities = ref([]);
const companies = ref([]);
const groups = ref([]);
const statuses = ref([]);
watch(currentTab, (newTab) => {
    if (newTab === 'services') {
        fetchServices();
    }
});
// Фильтрация и пагинация
const userSearchQuery = ref('');
const selectedGroupFilter = ref('');
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
let debounceTimer = null;

// Ссылки на модальные окна
const userModalRef = ref(null);
const legalEntityModalRef = ref(null);
const ourCompanyModalRef = ref(null);
const cityModalRef = ref(null);
const insuranceCompanyModalRef = ref(null);
const groupModalRef = ref(null);

const setTab = (tab) => {
    currentTab.value = tab;
};

const getLegalEntityName = (comp) => {
    return comp?.legal_entity?.name || 'Не привязано';
};

// Текст для кнопки добавления в шапке
const createButtonText = computed(() => {
    switch (currentTab.value) {
        case 'users': return 'Добавить пользователя';
        case 'legal-entities': return 'Добавить юр. лицо';
        case 'our-companies': return 'Добавить филиал';
        case 'cities': return 'Добавить город';
        case 'companies': return 'Добавить страховую';
        case 'services': return 'Добавить автосервис'; // <-- добавлено
        case 'statuses': return 'Добавить статус';
        default: return 'Создать';
    }
});

// Клик по кнопке создания
const handleCreateNew = () => {
    if (currentTab.value === 'users') {
        userModalRef.value?.open();
    } else if (currentTab.value === 'legal-entities') {
        legalEntityModalRef.value?.open();
    } else if (currentTab.value === 'our-companies') {
        ourCompanyModalRef.value?.open();
    } else if (currentTab.value === 'cities') {
        cityModalRef.value?.open();
    } else if (currentTab.value === 'companies') {
        insuranceCompanyModalRef.value?.open();
    } else if (currentTab.value === 'services') {
        serviceModalRef.value?.open(); // <-- добавлено
    } else if (currentTab.value === 'statuses') {
        statusModalRef.value?.open();
    }
};

const openUserModal = (u) => {
    userModalRef.value?.open(u);
};

// Загрузка пользователей
const fetchUsers = async (page = 1) => {
    try {
        const params = { page };

        if (userSearchQuery.value && userSearchQuery.value.trim() !== '') {
            params.search = userSearchQuery.value.trim();
        }

        if (selectedGroupFilter.value && selectedGroupFilter.value !== '') {
            params.group_id = selectedGroupFilter.value;
        }

        const res = await axios.get('/api/admin/users', { params });
        const payload = res.data;

        if (payload && Array.isArray(payload.data)) {
            users.value = payload.data;
            pagination.value = {
                current_page: payload.current_page || 1,
                last_page: payload.last_page || 1,
                total: payload.total !== undefined ? payload.total : payload.data.length,
            };
        } else if (Array.isArray(payload)) {
            users.value = payload;
            pagination.value = {
                current_page: 1,
                last_page: 1,
                total: payload.length,
            };
        } else {
            users.value = [];
        }
    } catch (e) {
        console.error('Ошибка загрузки пользователей:', e);
        users.value = [];
    }
};

const fetchLegalEntities = async () => {
    try {
        const res = await axios.get('/api/admin/legal-entities');
        legalEntities.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки юр. лиц:', e);
    }
};

const fetchOurCompanies = async () => {
    try {
        const res = await axios.get('/api/admin/our-companies');
        ourCompanies.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки филиалов:', e);
    }
};

const fetchCities = async () => {
    try {
        const res = await axios.get('/api/admin/cities');
        cities.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки городов:', e);
    }
};

const fetchInsuranceCompanies = async () => {
    try {
        const res = await axios.get('/api/admin/insurance-companies');
        companies.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки СК:', e);
    }
};

const fetchGroups = async () => {
    try {
        const res = await axios.get('/api/admin/user-groups');
        groups.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Ошибка загрузки групп:', e);
    }
};

const fetchStatuses = async () => {
    try {
        const res = await axios.get('/api/admin/application-statuses');
        const result = res.data?.data ?? res.data;
        statuses.value = Array.isArray(result) ? result : [];
    } catch (e) {
        console.error('Ошибка загрузки статусов:', e);
        statuses.value = [];
    }
};

// Удаление сущностей
const deleteUser = async (u) => {
    if (confirm(`Удалить пользователя «${u.full_name || u.name}»?`)) {
        try {
            await axios.delete(`/api/admin/users/${u.id}`);
            fetchUsers(pagination.value.current_page);
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить пользователя.');
        }
    }
};

const deleteStatus = async (st) => {
    if (confirm(`Удалить статус «${st.name}»?`)) {
        try {
            await axios.delete(`/api/admin/application-statuses/${st.id}`);
            fetchStatuses();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить статус.');
        }
    }
};

const deleteLegalEntity = async (item) => {
    if (confirm(`Удалить юр. лицо «${item.name}»?`)) {
        try {
            await axios.delete(`/api/admin/legal-entities/${item.id}`);
            fetchLegalEntities();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить юр. лицо.');
        }
    }
};

const deleteOurCompany = async (item) => {
    if (confirm(`Удалить филиал «${item.name}»?`)) {
        try {
            await axios.delete(`/api/admin/our-companies/${item.id}`);
            fetchOurCompanies();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить филиал.');
        }
    }
};

const deleteCity = async (item) => {
    if (confirm(`Удалить город «${item.name}»?`)) {
        try {
            await axios.delete(`/api/admin/cities/${item.id}`);
            fetchCities();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить город.');
        }
    }
};

const deleteCompany = async (item) => {
    if (confirm(`Удалить страховую компанию «${item.name}»?`)) {
        try {
            await axios.delete(`/api/admin/insurance-companies/${item.id}`);
            fetchInsuranceCompanies();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить страховую компанию.');
        }
    }
};

const deleteGroup = async (g) => {
    if (g.code === 'sys_admin') {
        alert('Системную роль администратора нельзя удалить.');
        return;
    }
    if (confirm(`Удалить роль «${g.name}»?`)) {
        try {
            await axios.delete(`/api/admin/user-groups/${g.id}`);
            fetchGroups();
        } catch (err) {
            alert(err.response?.data?.message || 'Не удалось удалить роль.');
        }
    }
};

const debounceFetchUsers = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchUsers(1), 350);
};

const clearSearch = () => {
    userSearchQuery.value = '';
    fetchUsers(1);
};

onMounted(() => {
    fetchUsers();
    fetchLegalEntities();
    fetchOurCompanies();
    fetchCities();
    fetchInsuranceCompanies();
    fetchGroups();
    fetchStatuses();
    fetchFinanceStatuses();
    fetchCalendarExceptions();
    fetchServices();
});
</script>

<style scoped>
.admin-tabs-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.admin-tabs-wrapper::-webkit-scrollbar {
    display: none;
}

.tab-chip {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid rgba(222, 226, 230, 0.9);
    color: #495057;
    font-size: 0.82rem;
    font-weight: 500;
    padding: 0.45rem 0.85rem;
    border-radius: 20px;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.tab-chip:hover {
    background: #f8f9fa;
    color: #212529;
}

.tab-chip.active {
    background: linear-gradient(135deg, #f37021 0%, #e65100 100%);
    color: #ffffff;
    border-color: #e65100;
    box-shadow: 0 2px 8px rgba(243, 112, 33, 0.35);
}

.tab-chip.active i {
    color: #ffffff;
}

.chip-count {
    background: rgba(0, 0, 0, 0.08);
    font-size: 0.7rem;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 6px;
}

.tab-chip.active .chip-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Обертка навигации с кнопками-стрелками */
.admin-tabs-nav-container {
    position: relative;
    display: flex;
    align-items: center;
}

/* Лента вкладок */
.admin-tabs-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    cursor: grab;
    user-select: none;

    /* Тонкий аккуратный скроллбар для десктопа */
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.admin-tabs-wrapper:active {
    cursor: grabbing;
}

/* Стилизация полосы прокрутки для Webkit (Chrome, Edge, Safari, Opera) */
.admin-tabs-wrapper::-webkit-scrollbar {
    height: 4px;
}

.admin-tabs-wrapper::-webkit-scrollbar-track {
    background: transparent;
}

.admin-tabs-wrapper::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}

.admin-tabs-wrapper::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}

/* Плавающие кнопки-стрелки прокрутки на десктопе */
.scroll-arrow-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    z-index: 5;
    cursor: pointer;
    transition: all 0.15s ease;
}

.scroll-arrow-btn:hover {
    background: #f8fafc;
    color: #ea580c;
    border-color: #ea580c;
    transform: translateY(-50%) scale(1.08);
}

.scroll-arrow-btn.left {
    left: -10px;
}

.scroll-arrow-btn.right {
    right: -10px;
}

/* Кнопки-чипсы */
.tab-chip {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid rgba(222, 226, 230, 0.9);
    color: #495057;
    font-size: 0.82rem;
    font-weight: 500;
    padding: 0.45rem 0.85rem;
    border-radius: 20px;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.tab-chip:hover {
    background: #f8f9fa;
    color: #212529;
}

.tab-chip.active {
    background: linear-gradient(135deg, #f37021 0%, #e65100 100%);
    color: #ffffff;
    border-color: #e65100;
    box-shadow: 0 2px 8px rgba(243, 112, 33, 0.35);
}

.tab-chip.active i {
    color: #ffffff;
}

.chip-count {
    background: rgba(0, 0, 0, 0.08);
    font-size: 0.7rem;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 6px;
}

.tab-chip.active .chip-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}
</style>