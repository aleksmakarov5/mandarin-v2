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
        <div class="admin-tabs-wrapper mb-3">
            <div class="d-flex gap-2 flex-nowrap py-1">
                <button class="tab-chip" :class="{ active: currentTab === 'users' }" @click="setTab('users')">
                    <i class="bi bi-people-fill me-1"></i>
                    <span>Пользователи</span>
                    <span class="chip-count">{{ pagination.total || users.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'legal-entities' }"
                    @click="setTab('legal-entities')">
                    <i class="bi bi-briefcase-fill me-1"></i>
                    <span>Юр. лица</span>
                    <span class="chip-count">{{ legalEntities.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'our-companies' }"
                    @click="setTab('our-companies')">
                    <i class="bi bi-shop me-1"></i>
                    <span>Наши филиалы</span>
                    <span class="chip-count">{{ ourCompanies.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'cities' }" @click="setTab('cities')">
                    <i class="bi bi-geo-alt-fill me-1"></i>
                    <span>Города</span>
                    <span class="chip-count">{{ cities.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'companies' }" @click="setTab('companies')">
                    <i class="bi bi-building me-1"></i>
                    <span>Страховые</span>
                    <span class="chip-count">{{ companies.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'groups' }" @click="setTab('groups')">
                    <i class="bi bi-person-badge-fill me-1"></i>
                    <span>Роли</span>
                    <span class="chip-count">{{ groups.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'statuses' }" @click="setTab('statuses')">
                    <i class="bi bi-tags-fill me-1"></i>
                    <span>Статусы</span>
                    <span class="chip-count">{{ statuses.length }}</span>
                </button>
            </div>
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

        <!-- 7. ВКЛАДКА: СТРАХОВЫЕ КОМПАНИИ -->
        <div v-if="currentTab === 'companies'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Страховая компания</th>
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
                                <td>{{ comp.curator || '—' }}</td>
                                <td>
                                    <div>{{ comp.phone || '—' }}</div>
                                    <small class="text-muted">{{ comp.email || '' }}</small>
                                </td>
                                <td>{{ comp.address || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
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
                                <td colspan="6" class="text-center py-5 text-muted">Страховые компании еще не добавлены
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
        <div v-if="currentTab === 'statuses'" class="content-block">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Статус</th>
                                <th>Цветовой код / Тема</th>
                                <th>Группа / Роль</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="st in statuses" :key="st.id">
                                <td class="text-muted fw-bold">#{{ st.id }}</td>
                                <td>
                                    <!-- Бейдж с индивидуальным цветом фона и текста -->
                                    <span class="badge px-2 py-1 fw-semibold" :style="{
                                        backgroundColor: st.bg_color || '#f1f5f9',
                                        color: st.text_color || '#334155',
                                        border: '1px solid ' + (st.text_color || '#334155') + '22'
                                    }">
                                        {{ st.name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        {{ st.group?.name || 'Общий статус' }}
                                    </span>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ВСЕ МОДАЛЬНЫЕ ОКНА CRUD -->
        <UserModal ref="userModalRef" :groups="groups" :cities="cities" :branches="ourCompanies"
            @saved="fetchUsers(pagination.current_page || 1)" />

        <LegalEntityModal ref="legalEntityModalRef" @saved="fetchLegalEntities" />

        <OurCompanyModal ref="ourCompanyModalRef" :legal-entities="legalEntities" @saved="fetchOurCompanies" />

        <CityModal ref="cityModalRef" @saved="fetchCities" />

        <InsuranceCompanyModal ref="insuranceCompanyModalRef" @saved="fetchInsuranceCompanies" />

        <GroupModal ref="groupModalRef" @saved="fetchGroups" />
        <StatusModal ref="statusModalRef" :groups="groups" @saved="fetchStatuses" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../../stores/auth';

import UserModal from './components/UserModal.vue';
import LegalEntityModal from './components/LegalEntityModal.vue';
import OurCompanyModal from './components/OurCompanyModal.vue';
import CityModal from './components/CityModal.vue';
import InsuranceCompanyModal from './components/InsuranceCompanyModal.vue';
import GroupModal from './components/GroupModal.vue';
import StatusModal from './components/StatusModal.vue';

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
        case 'groups': return 'Добавить роль';
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
    } else if (currentTab.value === 'groups') {
        groupModalRef.value?.open();
    } else if (currentTab.value === 'statuses') {
        statusModalRef.value?.open(); // <-- ВОТ ЭТА СТРОКА
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
        // Поддерживаем res.data.data (если пагинация) или чистый массив res.data
        statuses.value = res.data.data || res.data || [];
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
</style>