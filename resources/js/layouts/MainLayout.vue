<template>
    <div class="app-root-layout">
        <!-- Затемнение фона на смартфонах при открытой шторке меню -->
        <div v-if="isMobileOpen" class="sidebar-backdrop" @click="closeMobileSidebar"></div>

        <!-- 1. САЙДБАР -->
        <aside class="app-sidebar" :class="{
            'collapsed': isCollapsed && !isMobileOpen,
            'mobile-open': isMobileOpen
        }">
            <!-- Шапка сайдбара с логотипом -->
            <div class="sidebar-brand d-flex align-items-center justify-content-between px-3">
                <router-link to="/dashboard" class="d-flex align-items-center text-decoration-none"
                    @click="closeMobileSidebar" title="Перейти к дашборду">
                    <MandarinLogo :size="30" :show-text="!isCollapsed || isMobileOpen" text-class="text-white"
                        :clickable="true" />
                </router-link>
                <button class="btn btn-sm btn-link text-white d-md-none p-0" @click="closeMobileSidebar"
                    aria-label="Закрыть меню">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            <!-- Скроллируемый список меню -->
            <div class="flex-grow-1 py-2 overflow-auto sidebar-nav-scroll">
                <div class="nav-section-title">Документооборот</div>
                <ul class="nav flex-column mb-2">
                    <!-- 1. Запросы -->
                    <li class="nav-item">
                        <router-link to="/requests" class="nav-link d-flex align-items-center justify-content-between"
                            :class="{ active: isTabActive('requests') }" title="Запросы (Заявки)"
                            @click="closeMobileSidebar">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-inbox-fill me-2 fs-6"></i>
                                <span v-show="!isCollapsed" class="nav-text">Запросы</span>
                            </div>
                            <span v-if="counts.requests > 0"
                                class="badge rounded-pill bg-warning text-dark badge-count">
                                {{ counts.requests }}
                            </span>
                        </router-link>
                    </li>

                    <!-- 2. Направления -->
                    <li class="nav-item">
                        <router-link to="/applications"
                            class="nav-link d-flex align-items-center justify-content-between"
                            :class="{ active: isTabActive('applications') }" title="Направления в работе"
                            @click="closeMobileSidebar">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-signpost-2-fill me-2 fs-6"></i>
                                <span v-show="!isCollapsed" class="nav-text">Направления</span>
                            </div>
                            <span v-if="counts.applications > 0" class="badge rounded-pill bg-success badge-count">
                                {{ counts.applications }}
                            </span>
                        </router-link>
                    </li>

                    <!-- 3. Приемка -->
                    <li class="nav-item">
                        <router-link to="/acceptance" class="nav-link d-flex align-items-center justify-content-between"
                            :class="{ active: isTabActive('acceptance') }" title="Приемка" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-clipboard-check-fill me-2 fs-6"></i>
                                <span v-show="!isCollapsed" class="nav-text">Приемка</span>
                            </div>
                            <span v-if="counts.acceptance > 0" class="badge rounded-pill bg-info text-dark badge-count">
                                {{ counts.acceptance }}
                            </span>
                        </router-link>
                    </li>

                    <!-- 4. Архив -->
                    <li class="nav-item">
                        <router-link to="/archive" class="nav-link d-flex align-items-center justify-content-between"
                            :class="{ active: isTabActive('archive') }" title="Архив" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-archive-fill me-2 fs-6"></i>
                                <span v-show="!isCollapsed" class="nav-text">Архив</span>
                            </div>
                            <span v-if="counts.archive > 0" class="badge rounded-pill bg-secondary badge-count">
                                {{ counts.archive }}
                            </span>
                        </router-link>
                    </li>

                    <!-- 5. Корзина -->
                    <li class="nav-item">
                        <router-link to="/trash" class="nav-link d-flex align-items-center justify-content-between"
                            :class="{ active: isTabActive('trash') }" title="Корзина" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center text-truncate">
                                <i class="bi bi-trash3-fill me-2 fs-6"></i>
                                <span v-show="!isCollapsed" class="nav-text">Корзина</span>
                            </div>
                            <span v-if="counts.trash > 0" class="badge rounded-pill bg-danger badge-count">
                                {{ counts.trash }}
                            </span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/mail" class="nav-link" active-class="active" title="Почта"
                            @click="closeMobileSidebar">
                            <i class="bi bi-envelope-fill"></i>
                            <span class="nav-text">Почта</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/chat" class="nav-link justify-content-between" active-class="active"
                            title="Чат" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-chat-dots-fill"></i>
                                <span class="nav-text">Чат</span>
                            </div>
                            <span v-if="!isCollapsed || isMobileOpen" class="badge rounded-pill badge-count-yellow px-2"
                                style="font-size: 0.7rem;">3</span>
                        </router-link>
                    </li>
                </ul>

                <div class="nav-section-title">Учет и справочники</div>
                <ul class="nav flex-column mb-1">
                    <li class="nav-item">
                        <router-link to="/warehouse" class="nav-link" active-class="active" title="Склад"
                            @click="closeMobileSidebar">
                            <i class="bi bi-box-seam-fill"></i>
                            <span class="nav-text">Склад</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/finance" class="nav-link" active-class="active" title="Финансы"
                            @click="closeMobileSidebar">
                            <i class="bi bi-cash-coin"></i>
                            <span class="nav-text">Финансы</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/books" class="nav-link"
                            :class="{ 'active': $route.path.startsWith('/books') }" title="Справочники"
                            @click="closeMobileSidebar">
                            <i class="bi bi-journal-bookmark-fill"></i>
                            <span class="nav-text">Справочники</span>
                        </router-link>
                    </li>
                </ul>

                <template v-if="authStore.isAdmin">
                    <div class="nav-section-title">Система</div>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <router-link to="/admin" class="nav-link" active-class="active" title="Админ панель"
                                @click="closeMobileSidebar">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span class="nav-text">Админ панель</span>
                            </router-link>
                        </li>
                    </ul>
                </template>
            </div>

            <!-- Профиль внизу сайдбара -->
            <div class="sidebar-footer d-flex align-items-center flex-shrink-0">
                <div class="d-flex align-items-center flex-grow-1 overflow-hidden" v-if="!isCollapsed || isMobileOpen">
                    <router-link to="/profile"
                        class="user-info-link text-decoration-none text-truncate d-block flex-grow-1 me-2"
                        title="Перейти в личный профиль" @click="closeMobileSidebar">
                        <div class="user-short-name text-white small fw-bold text-truncate">
                            {{ authStore.userShortName }}
                            <i class="bi bi-chevron-right ms-1 text-muted" style="font-size: 0.65rem;"></i>
                        </div>
                        <div class="user-role-label text-truncate">
                            {{ authStore.userRoleName }}
                        </div>
                    </router-link>
                </div>
                <button @click="handleLogout" class="btn btn-sm btn-link text-secondary p-0 ms-auto"
                    title="Выйти из системы">
                    <i class="bi bi-box-arrow-right fs-5"></i>
                </button>
            </div>
        </aside>

        <!-- 2. ОСНОВНАЯ КОЛОНКА (GRID-СТРУКТУРА) -->
        <div class="app-main-column">
            <!-- 2.1 ВЕРХНЯЯ ШАПКА -->
            <header class="top-header d-flex align-items-center justify-content-between px-2 px-md-3">
                <div class="d-flex align-items-center min-w-0 gap-1 gap-md-2">
                    <button @click="handleToggleMenu" class="btn btn-sm btn-menu-toggle flex-shrink-0 p-1 px-2"
                        title="Меню">
                        <i class="bi bi-list fs-5"></i>
                    </button>

                    <router-link to="/dashboard" class="d-flex align-items-center text-decoration-none d-md-none"
                        title="Перейти к дашборду">
                        <MandarinLogo :size="24" :show-text="false" :clickable="true" />
                    </router-link>

                    <div class="header-search input-group input-group-sm d-none d-md-flex" style="max-width: 240px;">
                        <input type="text" class="form-control" placeholder="VIN, номер дела..." />
                        <button class="btn btn-outline-secondary" type="button">Найти</button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-1 gap-sm-2 flex-shrink-0">
                    <button
                        class="btn btn-warning btn-sm fw-bold d-flex align-items-center justify-content-center header-add-btn text-dark shadow-sm"
                        @click="$router.push('/applications/create')" title="Добавить заявку">
                        <i class="bi bi-plus-lg fs-6"></i>
                        <span class="d-none d-sm-inline ms-1">Добавить заявку</span>
                    </button>

                    <div class="header-clock-container flex-shrink-0">
                        <header-clock />
                    </div>
                </div>
            </header>

            <!-- 2.2 РАБОЧАЯ ОБЛАСТЬ (ЕДИНСТВЕННЫЙ ВНУТРЕННИЙ СКРОЛЛ) -->
            <main class="main-content-area">
                <router-view />
            </main>

            <!-- 2.3 НИЖНЯЯ ПАНЕЛЬ СМАРТФОНА -->
            <nav class="mobile-bottom-nav d-md-none">
                <router-link to="/requests" class="bottom-nav-item" active-class="active">
                    <div class="nav-icon-wrap">
                        <i class="bi bi-inbox-fill"></i>
                        <span class="mobile-badge-pill badge-count-yellow">12</span>
                    </div>
                    <span class="nav-label">Запросы</span>
                </router-link>

                <router-link to="/applications" class="bottom-nav-item" active-class="active">
                    <div class="nav-icon-wrap">
                        <i class="bi bi-signpost-2-fill"></i>
                        <span class="mobile-badge-pill badge-count-green">17</span>
                    </div>
                    <span class="nav-label">Направл.</span>
                </router-link>

                <router-link to="/mail" class="bottom-nav-item" active-class="active">
                    <div class="nav-icon-wrap">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <span class="nav-label">Почта</span>
                </router-link>

                <router-link to="/chat" class="bottom-nav-item" active-class="active">
                    <div class="nav-icon-wrap">
                        <i class="bi bi-chat-dots-fill"></i>
                        <span class="mobile-badge-pill badge-count-yellow">3</span>
                    </div>
                    <span class="nav-label">Чат</span>
                </router-link>

                <router-link v-if="authStore.isAdmin" to="/admin" class="bottom-nav-item" active-class="active">
                    <div class="nav-icon-wrap">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <span class="nav-label">Админ</span>
                </router-link>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import MandarinLogo from '../components/MandarinLogo.vue';
import HeaderClock from '../components/HeaderClock.vue';
const counts = ref({
    requests: 0,
    applications: 0,
    acceptance: 0,
    archive: 0,
    trash: 0,
});
// 1. Инициализируем route и router в самом начале:
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const isCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const isMobileOpen = ref(false);

// 2. Логика подсветки активной вкладки:
const activeRegistryTab = computed(() => {
    if (!route) return null;

    const directTabs = ['requests', 'applications', 'acceptance', 'archive', 'trash'];
    if (directTabs.includes(route.name)) {
        return route.name;
    }

    if (route.path && route.path.startsWith('/applications/') && route.params?.id) {
        if (route.query?.from_tab) {
            return String(route.query.from_tab);
        }
        const currentAppStatusId = Number(route.query?.status_id || 0);
        if (currentAppStatusId === 25) return 'archive';
        if (currentAppStatusId === 27) return 'trash';
        return 'applications';
    }

    return null;
});

const isTabActive = (tabName) => {
    return activeRegistryTab.value === tabName;
};

const handleToggleMenu = () => {
    if (window.innerWidth < 768) {
        isMobileOpen.value = !isMobileOpen.value;
    } else {
        isCollapsed.value = !isCollapsed.value;
        localStorage.setItem('sidebar_collapsed', isCollapsed.value);
    }
};

const closeMobileSidebar = () => {
    isMobileOpen.value = false;
};

const handleLogout = async () => {
    if (confirm('Вы действительно хотите выйти из системы?')) {
        await authStore.logout();
        router.push({ name: 'login' });
    }
};
</script>

<style scoped>
/* 1. КОРНЕВАЯ СТРУКТУРА: ЖЕСТКАЯ ФИКСАЦИЯ НА ВЕСЬ ЭКРАН */
.app-root-layout {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
    display: flex;
    background-color: #f8fafc;
}

/* 2. САЙДБАР */
.app-sidebar {
    width: 250px;
    height: 100%;
    background-color: #1e222d;
    color: #cbd5e1;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transition: width 0.2s ease, transform 0.25s ease;
}

.sidebar-brand {
    height: 52px;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.sidebar-nav-scroll {
    scrollbar-width: none;
}

.sidebar-nav-scroll::-webkit-scrollbar {
    display: none;
}

.nav-section-title {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.5rem 1rem 0.25rem;
}

.nav-link {
    color: #94a3b8;
    display: flex;
    align-items: center;
    padding: 0.45rem 0.85rem;
    font-size: 0.84rem;
    font-weight: 500;
    border-radius: 6px;
    margin: 1px 8px;
    transition: all 0.15s ease;
    text-decoration: none;
}

.nav-link i {
    font-size: 1.05rem;
    min-width: 24px;
}

.nav-text {
    margin-left: 0.5rem;
    white-space: nowrap;
}

.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.06);
    color: #ffffff;
}

.nav-link.active {
    background-color: #ea580c;
    color: #ffffff;
    font-weight: 600;
}

.badge-count-yellow {
    background-color: #f59e0b;
    color: #1e222d;
    font-weight: 700;
}

.badge-count-green {
    background-color: #10b981;
    color: #ffffff;
    font-weight: 700;
}

.sidebar-footer {
    height: 52px;
    padding: 0.5rem 0.85rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background-color: #181b24;
    flex-shrink: 0;
}

.user-role-label {
    font-size: 0.68rem;
    color: #94a3b8;
}

/* 3. ДЕСКТОП СЕТКА (ШАПКА 52px + КОНТЕНТ 1fr) */
.app-main-column {
    flex: 1 1 auto;
    min-width: 0;
    height: 100%;
    display: grid;
    grid-template-rows: 52px 1fr;
    overflow: hidden;
}

/* 4. МОБИЛЬНАЯ СЕТКА (ШАПКА 52px + КОНТЕНТ 1fr + МЕНЮ 54px) */
@media (max-width: 767.98px) {
    .app-main-column {
        grid-template-rows: 52px 1fr calc(54px + env(safe-area-inset-bottom, 0px)) !important;
    }
}

/* ШАПКА */
.top-header {
    height: 52px;
    background-color: #ffffff;
    border-bottom: 1px solid #dee2e6;
    z-index: 1030;
}

.btn-menu-toggle {
    color: #475569;
    border: 1px solid #cbd5e1;
    background: transparent;
}

.header-add-btn {
    height: 32px;
    font-size: 0.78rem;
    letter-spacing: 0.2px;
}

/* ЕДИНСТВЕННЫЙ ВНУТРЕННИЙ СКРОЛЛ */
.main-content-area {
    min-height: 0;
    min-width: 0;
    width: 100%;
    height: 100%;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 6px;
    background-color: #f8fafc;
    -webkit-overflow-scrolling: touch;
}

/* МОБИЛЬНАЯ НИЖНЯЯ ПАНЕЛЬ */
.mobile-bottom-nav {
    height: calc(54px + env(safe-area-inset-bottom, 0px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #ffffff;
    border-top: 1px solid #dee2e6;
    box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
    z-index: 1030;
    display: flex;
    align-items: center;
    justify-content: space-around;
}

.bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #64748b;
    flex: 1;
    min-width: 0;
    padding: 2px 0;
    transition: color 0.15s ease;
}

.bottom-nav-item.active {
    color: #ea580c;
}

.nav-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 22px;
}

.nav-icon-wrap i {
    font-size: 1.1rem;
    line-height: 1;
}

.mobile-badge-pill {
    position: absolute;
    top: -4px;
    right: -9px;
    font-size: 0.58rem;
    font-weight: 700;
    line-height: 1;
    padding: 1px 3px;
    border-radius: 8px;
    border: 1px solid #ffffff;
}

.nav-label {
    font-size: 0.62rem;
    font-weight: 500;
    line-height: 1.1;
    margin-top: 2px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.bottom-nav-item.active .nav-label {
    font-weight: 700;
    color: #ea580c;
}

/* ========================================================= */
/* ДЕСКТОП: СВЕРНУТОЕ СОСТОЯНИЕ (>= 768px)                   */
/* ========================================================= */
@media (min-width: 768px) {
    .app-sidebar.collapsed {
        width: 64px;
    }

    .app-sidebar.collapsed .nav-text,
    .app-sidebar.collapsed .nav-section-title,
    .app-sidebar.collapsed .badge-count-yellow,
    .app-sidebar.collapsed .badge-count-green,
    .app-sidebar.collapsed .user-info-link {
        display: none !important;
    }

    .app-sidebar.collapsed .nav-link {
        justify-content: center !important;
        padding: 0.55rem 0 !important;
        margin: 2px 6px !important;
    }

    .app-sidebar.collapsed .nav-link i {
        min-width: auto;
        margin: 0 !important;
    }
}

/* ========================================================= */
/* МОБИЛЬНЫЕ УСТРОЙСТВА (< 768px)                            */
/* ========================================================= */
@media (max-width: 767.98px) {
    .app-sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 270px !important;
        transform: translateX(-100%);
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4);
    }

    .app-sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 1035;
        backdrop-filter: blur(2px);
    }

    .top-header {
        background-color: #212529 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        padding-left: 6px !important;
        padding-right: 6px !important;
    }

    .btn-menu-toggle {
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        background-color: rgba(255, 255, 255, 0.1) !important;
    }

    .header-add-btn {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        border-radius: 6px;
    }
}
</style>