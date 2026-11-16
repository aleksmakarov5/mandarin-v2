<template>
    <div class="admin-dashboard-page">
        <!-- 1. КОМПАКТНАЯ ШАПКА -->
        <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
            <div class="min-w-0">
                <h5 class="mb-0 fw-bold text-dark d-flex align-items-center text-truncate">
                    <i class="bi bi-shield-lock-fill text-primary me-2 flex-shrink-0"></i>
                    <span>Администрирование</span>
                </h5>
                <small class="text-muted d-none d-sm-inline">Управление пользователями, правами и справочниками</small>
            </div>

            <!-- Кнопка добавления: на смартфонах компактная с иконкой, на десктопе полная -->
            <button class="btn btn-primary btn-sm px-3 d-flex align-items-center flex-shrink-0 shadow-sm"
                @click="handleCreateNew">
                <i class="bi bi-plus-lg me-1"></i>
                <span class="d-none d-sm-inline">{{ createButtonText }}</span>
                <span class="d-inline d-sm-none">Добавить</span>
            </button>
        </div>

        <!-- 2. СЕГМЕНТНЫЕ ВКЛАДКИ (ГОРИЗОНТАЛЬНЫЕ ЧИПСЫ ДЛЯ МОБИЛОК) -->
        <div class="admin-tabs-wrapper mb-3">
            <div class="d-flex gap-2 flex-nowrap py-1">
                <button class="tab-chip" :class="{ active: currentTab === 'users' }" @click="currentTab = 'users'">
                    <i class="bi bi-people-fill me-1"></i>
                    <span>Пользователи</span>
                    <span class="chip-count">{{ users.length }}</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'groups' }" @click="currentTab = 'groups'">
                    <i class="bi bi-person-badge-fill me-1"></i>
                    <span>Роли</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'companies' }"
                    @click="currentTab = 'companies'">
                    <i class="bi bi-building me-1"></i>
                    <span>Страховые</span>
                </button>

                <button class="tab-chip" :class="{ active: currentTab === 'statuses' }"
                    @click="currentTab = 'statuses'">
                    <i class="bi bi-tags-fill me-1"></i>
                    <span>Статусы</span>
                </button>
            </div>
        </div>

        <!-- 3. ВКЛАДКА: ПОЛЬЗОВАТЕЛИ -->
        <div v-if="currentTab === 'users'" class="content-block">
            <!-- Поисковая строка -->
            <div class="mb-2">
                <div class="input-group input-group-sm search-input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted ps-3">
                        <i class="bi bi-search"></i>
                    </span>
                    <input v-model="userSearchQuery" @input="fetchUsers" type="text"
                        class="form-control border-start-0 py-2" placeholder="Поиск по имени, email, телефону..." />
                    <button v-if="userSearchQuery" class="btn btn-white bg-white border border-start-0 text-muted"
                        @click="userSearchQuery = ''; fetchUsers()">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            </div>

            <!-- ДЕСКТОП И ПЛАНШЕТ: ТАБЛИЦА (>= 768px) -->
            <div class="card border-0 shadow-sm d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Роль</th>
                                <th>Город</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :key="user.id">
                                <td class="text-muted">#{{ user.id }}</td>
                                <td class="fw-semibold text-dark">{{ user.name }}</td>
                                <td>{{ user.email }}</td>
                                <td>{{ user.phone || '—' }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ user.group?.name || 'Без группы' }}</span>
                                </td>
                                <td>{{ user.city?.name || '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="openUserModal(user)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteUser(user.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">Пользователи не найдены</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- МОБИЛЬНЫЙ ВИД: НАЗЕМНЫЕ СЕНСОРНЫЕ КАРТОЧКИ (< 768px) -->
            <div class="d-md-none">
                <div v-for="user in users" :key="'mob-u-' + user.id"
                    class="card border-0 shadow-sm mb-2 p-3 user-mobile-card">
                    <!-- Верх карточки: Аватар/Имя + Бейдж роли -->
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 pb-2 border-bottom">
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate fs-6">{{ user.name }}</div>
                            <span class="text-muted" style="font-size: 0.72rem;">ID: #{{ user.id }}</span>
                        </div>
                        <span class="badge bg-secondary text-wrap text-end flex-shrink-0"
                            style="max-width: 140px; font-size: 0.7rem;">
                            {{ user.group?.name || 'Без группы' }}
                        </span>
                    </div>

                    <!-- Контакты с аккуратными иконками -->
                    <div class="user-meta-list small mb-3">
                        <div class="meta-row">
                            <i class="bi bi-envelope text-muted me-2"></i>
                            <span class="text-dark text-break fw-medium">{{ user.email }}</span>
                        </div>
                        <div class="meta-row" v-if="user.phone">
                            <i class="bi bi-telephone text-muted me-2"></i>
                            <a :href="'tel:' + user.phone" class="text-dark text-decoration-none">{{ user.phone }}</a>
                        </div>
                        <div class="meta-row" v-if="user.city?.name">
                            <i class="bi bi-geo-alt text-muted me-2"></i>
                            <span class="text-muted">{{ user.city.name }}</span>
                        </div>
                    </div>

                    <!-- Нижняя панель действий -->
                    <div class="d-flex gap-2 pt-1">
                        <button
                            class="btn btn-light border btn-sm flex-grow-1 d-flex align-items-center justify-content-center py-2"
                            @click="openUserModal(user)">
                            <i class="bi bi-pencil me-1"></i>
                            <span>Изменить</span>
                        </button>
                        <button
                            class="btn btn-outline-danger btn-sm flex-grow-1 d-flex align-items-center justify-content-center py-2"
                            @click="deleteUser(user.id)">
                            <i class="bi bi-trash me-1"></i>
                            <span>Удалить</span>
                        </button>
                    </div>
                </div>

                <div v-if="users.length === 0" class="text-center py-4 text-muted bg-white rounded shadow-sm">
                    <i class="bi bi-people fs-2 d-block mb-1 text-muted"></i>
                    Пользователи не найдены
                </div>
            </div>
        </div>

        <!-- 4. ВКЛАДКА: РОЛИ И ГРУППЫ -->
        <div v-if="currentTab === 'groups'" class="content-block">
            <!-- Десктоп / Планшет: Таблица -->
            <div class="card border-0 shadow-sm d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Наименование</th>
                                <th>Код</th>
                                <th>Описание</th>
                                <th>Пользователей</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groups" :key="group.id">
                                <td class="text-muted">#{{ group.id }}</td>
                                <td class="fw-semibold text-dark">{{ group.name }}</td>
                                <td><code>{{ group.code }}</code></td>
                                <td>{{ group.description || '—' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ group.users_count || 0 }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="openGroupModal(group)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteGroup(group.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Мобильный вид карточек -->
            <div class="d-md-none">
                <div v-for="group in groups" :key="'mob-g-' + group.id" class="card border-0 shadow-sm mb-2 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold text-dark fs-6">{{ group.name }}</div>
                            <small class="text-muted font-monospace"><code>{{ group.code }}</code></small>
                        </div>
                        <span class="badge bg-light text-dark border">{{ group.users_count || 0 }} польз.</span>
                    </div>
                    <p class="small text-muted mb-3" v-if="group.description">{{ group.description }}</p>

                    <div class="d-flex gap-2">
                        <button class="btn btn-light border btn-sm flex-grow-1 py-2" @click="openGroupModal(group)">
                            <i class="bi bi-pencil me-1"></i> Изменить
                        </button>
                        <button class="btn btn-outline-danger btn-sm flex-grow-1 py-2" @click="deleteGroup(group.id)">
                            <i class="bi bi-trash me-1"></i> Удалить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. ВКЛАДКА: СТРАХОВЫЕ КОМПАНИИ -->
        <div v-if="currentTab === 'companies'" class="content-block">
            <!-- Десктоп / Планшет: Таблица -->
            <div class="card border-0 shadow-sm d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Наименование</th>
                                <th>Куратор</th>
                                <th>Email / Телефон</th>
                                <th>Заявок</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="comp in companies" :key="comp.id">
                                <td class="text-muted">#{{ comp.id }}</td>
                                <td class="fw-semibold text-dark">{{ comp.name }}</td>
                                <td>{{ comp.manager || '—' }}</td>
                                <td>
                                    <div>{{ comp.email || '—' }}</div>
                                    <small class="text-muted">{{ comp.phone || '' }}</small>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ comp.applications_count || 0
                                }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="openCompanyModal(comp)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteCompany(comp.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Мобильный вид карточек -->
            <div class="d-md-none">
                <div v-for="comp in companies" :key="'mob-c-' + comp.id" class="card border-0 shadow-sm mb-2 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate fs-6">{{ comp.name }}</div>
                            <small class="text-muted" v-if="comp.manager">Куратор: {{ comp.manager }}</small>
                        </div>
                        <span class="badge bg-light text-dark border flex-shrink-0">{{ comp.applications_count || 0 }}
                            дел</span>
                    </div>

                    <div class="user-meta-list small mb-3">
                        <div class="meta-row" v-if="comp.email">
                            <i class="bi bi-envelope text-muted me-2"></i>
                            <span class="text-dark text-break">{{ comp.email }}</span>
                        </div>
                        <div class="meta-row" v-if="comp.phone">
                            <i class="bi bi-telephone text-muted me-2"></i>
                            <span class="text-dark">{{ comp.phone }}</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-light border btn-sm flex-grow-1 py-2" @click="openCompanyModal(comp)">
                            <i class="bi bi-pencil me-1"></i> Изменить
                        </button>
                        <button class="btn btn-outline-danger btn-sm flex-grow-1 py-2" @click="deleteCompany(comp.id)">
                            <i class="bi bi-trash me-1"></i> Удалить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. ВКЛАДКА: СТАТУСЫ -->
        <div v-if="currentTab === 'statuses'" class="content-block">
            <!-- Десктоп / Планшет: Таблица -->
            <div class="card border-0 shadow-sm d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Статус</th>
                                <th>Ответственная группа</th>
                                <th>Цвет</th>
                                <th>Заявок</th>
                                <th class="text-end" style="width: 110px;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="st in statuses" :key="st.id">
                                <td class="text-muted">#{{ st.id }}</td>
                                <td><span :class="`badge bg-${st.color || 'secondary'}`">{{ st.name }}</span></td>
                                <td>{{ st.group?.name || 'Общий статус' }}</td>
                                <td><code>{{ st.color }}</code></td>
                                <td><span class="badge bg-light text-dark border">{{ st.applications_count || 0
                                }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-light border" title="Редактировать"
                                            @click="openStatusModal(st)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" title="Удалить"
                                            @click="deleteStatus(st.id)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Мобильный вид карточек -->
            <div class="d-md-none">
                <div v-for="st in statuses" :key="'mob-s-' + st.id" class="card border-0 shadow-sm mb-2 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span :class="`badge bg-${st.color || 'secondary'} fs-6`">{{ st.name }}</span>
                        <span class="badge bg-light text-dark border">{{ st.applications_count || 0 }} дел</span>
                    </div>
                    <div class="small text-muted mb-3">
                        Группа: <strong class="text-dark">{{ st.group?.name || 'Общий статус' }}</strong>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-light border btn-sm flex-grow-1 py-2" @click="openStatusModal(st)">
                            <i class="bi bi-pencil me-1"></i> Изменить
                        </button>
                        <button class="btn btn-outline-danger btn-sm flex-grow-1 py-2" @click="deleteStatus(st.id)">
                            <i class="bi bi-trash me-1"></i> Удалить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const currentTab = ref('users');
const users = ref([]);
const groups = ref([]);
const companies = ref([]);
const statuses = ref([]);
const userSearchQuery = ref('');

const createButtonText = computed(() => {
    switch (currentTab.value) {
        case 'users': return 'Добавить пользователя';
        case 'groups': return 'Добавить группу';
        case 'companies': return 'Добавить компанию';
        case 'statuses': return 'Добавить статус';
        default: return 'Создать';
    }
});

const handleCreateNew = () => {
    if (currentTab.value === 'users') openUserModal();
    else if (currentTab.value === 'groups') openGroupModal();
    else if (currentTab.value === 'companies') openCompanyModal();
    else if (currentTab.value === 'statuses') openStatusModal();
};

const fetchUsers = async () => {
    try {
        const res = await axios.get('/api/admin/users', { params: { search: userSearchQuery.value } });
        users.value = res.data.data || res.data;
    } catch (e) {
        console.error('Ошибка загрузки пользователей:', e);
    }
};

const fetchGroups = async () => {
    try {
        const res = await axios.get('/api/admin/user-groups');
        groups.value = res.data;
    } catch (e) {
        console.error('Ошибка загрузки групп:', e);
    }
};

const fetchCompanies = async () => {
    try {
        const res = await axios.get('/api/admin/insurance-companies');
        companies.value = res.data;
    } catch (e) {
        console.error('Ошибка загрузки компаний:', e);
    }
};

const fetchStatuses = async () => {
    try {
        const res = await axios.get('/api/admin/application-statuses');
        statuses.value = res.data;
    } catch (e) {
        console.error('Ошибка загрузки статусов:', e);
    }
};

const deleteUser = async (id) => {
    if (confirm('Удалить пользователя?')) {
        await axios.delete(`/api/admin/users/${id}`);
        fetchUsers();
    }
};

const deleteGroup = async (id) => {
    if (confirm('Удалить группу?')) {
        await axios.delete(`/api/admin/user-groups/${id}`);
        fetchGroups();
    }
};

const deleteCompany = async (id) => {
    if (confirm('Удалить страховую компанию?')) {
        await axios.delete(`/api/admin/insurance-companies/${id}`);
        fetchCompanies();
    }
};

const deleteStatus = async (id) => {
    if (confirm('Удалить статус?')) {
        await axios.delete(`/api/admin/application-statuses/${id}`);
        fetchStatuses();
    }
};

const openUserModal = (user = null) => { };
const openGroupModal = (group = null) => { };
const openCompanyModal = (comp = null) => { };
const openStatusModal = (st = null) => { };

onMounted(() => {
    fetchUsers();
    fetchGroups();
    fetchCompanies();
    fetchStatuses();
});
</script>

<style scoped>
/* Горизонтальный плавный скролл вкладок */
.admin-tabs-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.admin-tabs-wrapper::-webkit-scrollbar {
    display: none;
}

/* Стили вкладок-чипсов */
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

/* Поиск */
.search-input-group .form-control {
    font-size: 0.85rem;
}

/* Мобильная карточка */
.user-mobile-card {
    border-radius: 8px;
}

.user-meta-list .meta-row {
    display: flex;
    align-items: center;
    margin-bottom: 0.35rem;
    line-height: 1.3;
}

.user-meta-list .meta-row:last-child {
    margin-bottom: 0;
}
</style>