<template>
    <div class="d-flex vh-100 vw-100 overflow-hidden app-root-layout">
        <!-- Затемнение фона на смартфонах при открытой шторке меню -->
        <div v-if="isMobileOpen" class="sidebar-backdrop" @click="closeMobileSidebar"></div>

        <!-- 1. САЙДБАР (на мобилке шторка, на десктопе — фиксированный) -->
        <aside class="app-sidebar d-flex flex-column flex-shrink-0" :class="{
            'collapsed': isCollapsed && !isMobileOpen,
            'mobile-open': isMobileOpen
        }">
            <!-- Шапка сайдбара с логотипом (десктоп и шторка) -->
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
                <ul class="nav flex-column mb-1">
                    <li class="nav-item">
                        <router-link to="/requests" class="nav-link justify-content-between" active-class="active"
                            title="Запросы" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-inbox-fill"></i>
                                <span class="nav-text">Запросы</span>
                            </div>
                            <span v-if="!isCollapsed || isMobileOpen" class="badge rounded-pill badge-count-yellow px-2"
                                style="font-size: 0.7rem;">12</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/applications" class="nav-link justify-content-between" active-class="active"
                            title="Направления" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-signpost-2-fill"></i>
                                <span class="nav-text">Направления</span>
                            </div>
                            <div v-if="!isCollapsed || isMobileOpen" class="d-flex gap-1">
                                <span class="badge rounded-pill badge-count-green px-1"
                                    style="font-size: 0.65rem;">17</span>
                                <span class="badge rounded-pill badge-count-yellow px-1"
                                    style="font-size: 0.65rem;">57</span>
                            </div>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/acceptance" class="nav-link" active-class="active" title="Приемка"
                            @click="closeMobileSidebar">
                            <i class="bi bi-clipboard-check-fill"></i>
                            <span class="nav-text">Приемка</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/archive" class="nav-link justify-content-between" active-class="active"
                            title="Архив" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-archive-fill"></i>
                                <span class="nav-text">Архив</span>
                            </div>
                            <span v-if="!isCollapsed || isMobileOpen" class="badge rounded-pill badge-count-green px-2"
                                style="font-size: 0.7rem;">10</span>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link to="/trash" class="nav-link justify-content-between" active-class="active"
                            title="Корзина" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-trash3-fill"></i>
                                <span class="nav-text">Корзина</span>
                            </div>
                            <span v-if="!isCollapsed || isMobileOpen" class="badge rounded-pill badge-count-green px-2"
                                style="font-size: 0.7rem;">21</span>
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
                        <router-link to="/books" class="nav-link" active-class="active" title="Справочники"
                            @click="closeMobileSidebar">
                            <i class="bi bi-journal-bookmark-fill"></i>
                            <span class="nav-text">Справочники</span>
                        </router-link>
                    </li>
                </ul>

                <!-- Раздел сисадмина -->
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

        <!-- 2. ОСНОВНАЯ КОЛОНКА (ШАПКА + РАБОЧАЯ ОБЛАСТЬ) -->
        <div class="d-flex flex-column flex-grow-1 min-w-0 h-100 overflow-hidden">
            <!-- Верхняя шапка (всегда зафиксирована на месте) -->
            <!-- ВЕРХНЯЯ ШАПКА -->
            <!-- ВЕРХНЯЯ ШАПКА (На мобильных — тёмная, компактная) -->
            <header class="top-header flex-shrink-0 d-flex align-items-center justify-content-between px-2 px-md-3">
                <!-- Левая часть: Меню + Логотип -->
                <div class="d-flex align-items-center min-w-0 gap-1 gap-md-2">
                    <button @click="handleToggleMenu" class="btn btn-sm btn-menu-toggle flex-shrink-0 p-1 px-2"
                        title="Меню">
                        <i class="bi bi-list fs-5"></i>
                    </button>

                    <!-- МОБИЛЬНЫЙ ЛОГОТИП (на темном фоне) -->
                    <router-link to="/dashboard" class="d-flex align-items-center text-decoration-none d-md-none"
                        title="Перейти к дашборду">
                        <MandarinLogo :size="24" :show-text="false" :clickable="true" />
                    </router-link>

                    <!-- Поиск для десктопа -->
                    <div class="header-search input-group input-group-sm d-none d-md-flex" style="max-width: 240px;">
                        <input type="text" class="form-control" placeholder="VIN, номер дела..." />
                        <button class="btn btn-outline-secondary" type="button">Найти</button>
                    </div>
                </div>

                <!-- Правая часть: кнопка создания + часы -->
                <div class="d-flex align-items-center gap-1 gap-sm-2 flex-shrink-0">
                    <!-- Ультракомпактная кнопка создания: на мобилке только иконка "+" в круге/квадрате -->
                    <button
                        class="btn btn-warning btn-sm fw-bold d-flex align-items-center justify-content-center header-add-btn text-dark shadow-sm"
                        @click="$router.push('/applications/create')" title="Добавить заявку">
                        <i class="bi bi-plus-lg fs-6"></i>
                        <span class="d-none d-sm-inline ms-1">Добавить заявку</span>
                    </button>

                    <!-- Виджет часов -->
                    <div class="header-clock-container flex-shrink-0">
                        <header-clock />
                    </div>
                </div>
            </header>

            <!-- 3. ГЛАВНАЯ РАБОЧАЯ ОБЛАСТЬ (ЕДИНСТВЕННОЕ МЕСТО СКРОЛЛА) -->
            <main class="main-content-area flex-grow-1">
                <router-view />
            </main>

            <!-- 4. НИЖНЯЯ ПАНЕЛЬ ДЛЯ СМАРТФОНОВ -->
            <nav class="mobile-bottom-nav flex-shrink-0 d-md-none">
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
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import MandarinLogo from '../components/MandarinLogo.vue';
import HeaderClock from '../components/HeaderClock.vue';

const router = useRouter();
const authStore = useAuthStore();

const isCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const isMobileOpen = ref(false);

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
/* Корневая блокировка окна: страница не скроллится целиком */
.app-root-layout {
    height: 100vh;
    width: 100vw;
    overflow: hidden;
    background-color: #f8fafc;
}

/* ------------------------------------------------------------- */
/* САЙДБАР                                                       */
/* ------------------------------------------------------------- */
.app-sidebar {
    width: 250px;
    height: 100vh;
    background-color: #1e222d;
    color: #cbd5e1;
    z-index: 1040;
    transition: width 0.2s ease, transform 0.25s ease;
}

.sidebar-brand {
    height: 52px;
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
}

.user-role-label {
    font-size: 0.68rem;
    color: #94a3b8;
}

/* ------------------------------------------------------------- */
/* ДЕСКТОП: СВЕРНУТОЕ СОСТОЯНИЕ (>= 768px)                       */
/* ------------------------------------------------------------- */
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

/* ------------------------------------------------------------- */
/* МОБИЛЬНЫЕ УСТРОЙСТВА (< 768px)                                */
/* ------------------------------------------------------------- */
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

    /* На смартфонах надписи и блоки разделов ВСЕГДА видны */
    .app-sidebar .nav-text {
        display: inline-block !important;
    }

    .app-sidebar .nav-section-title {
        display: block !important;
    }

    .app-sidebar .nav-link {
        justify-content: flex-start !important;
        padding: 0.55rem 0.85rem !important;
    }

    .app-sidebar .nav-link i {
        min-width: 24px;
        margin-right: 6px !important;
    }

    .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 1035;
        backdrop-filter: blur(2px);
    }

    .main-content-area {
        height: calc(100vh - 52px - 60px - env(safe-area-inset-bottom, 0px)) !important;
    }
}

/* ------------------------------------------------------------- */
/* ВЕРХНЯЯ ШАПКА                                                 */
/* ------------------------------------------------------------- */
.top-header {
    height: 52px;
    background-color: #ffffff;
    border-bottom: 1px solid #dee2e6;
    z-index: 10;
}

.header-add-btn {
    background-color: #3f3f46;
    border-color: #27272a;
    color: #ffffff;
    height: 32px;
    font-size: 0.78rem;
    letter-spacing: 0.3px;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

/* Оранжевый цвет с белым текстом при наведении */
.header-add-btn:hover,
.header-add-btn:focus {
    background-color: #ea580c !important;
    /* Фирменный мандариновый оттенок */
    border-color: #c2410c !important;
    color: #ffffff !important;
}

.header-add-btn:hover i,
.header-add-btn:focus i {
    color: #ffffff !important;
}

/* ------------------------------------------------------------- */
/* ГЛАВНАЯ РАБОЧАЯ ОБЛАСТЬ                                       */
/* ------------------------------------------------------------- */
.main-content-area {
    min-width: 0;
    max-width: 100%;
    height: calc(100vh - 52px);
    overflow-x: auto !important;
    overflow-y: auto !important;
    padding: 6px;
    background-color: #f8fafc;
    -webkit-overflow-scrolling: touch;
}

/* ------------------------------------------------------------- */
/* НИЖНЯЯ ПАНЕЛЬ СМАРТФОНА                                       */
/* ------------------------------------------------------------- */
.mobile-bottom-nav {
    display: flex;
    align-items: center;
    justify-content: space-around;
    height: 60px;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #ffffff;
    border-top: 1px solid #dee2e6;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    z-index: 1030;
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

.bottom-nav-item:hover,
.bottom-nav-item.active {
    color: #ea580c;
}

.nav-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 24px;
}

.nav-icon-wrap i {
    font-size: 1.15rem;
    line-height: 1;
}

.mobile-badge-pill {
    position: absolute;
    top: -4px;
    right: -10px;
    font-size: 0.60rem;
    font-weight: 700;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 10px;
    border: 1.5px solid #ffffff;
}

.nav-label {
    font-size: 0.65rem;
    font-weight: 500;
    line-height: 1.1;
    margin-top: 2px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    padding: 0 2px;
}

.bottom-nav-item.active .nav-label {
    font-weight: 700;
    color: #ea580c;
}

/* Десктопная шапка (светлая) */
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

.btn-menu-toggle:hover {
    background-color: #f1f5f9;
}

.header-add-btn {
    height: 32px;
    font-size: 0.78rem;
    letter-spacing: 0.2px;
}

/* ========================================================= */
/* МОБИЛЬНАЯ ВЕРСИЯ (< 768px): ТЕМНАЯ ШАПКА И КОМПАКТНОСТЬ  */
/* ========================================================= */
@media (max-width: 767.98px) {
    .top-header {
        background-color: #3d3d3f40 !important;
        /* Фирменный темный графит */
        border-bottom: 1px solid rgba(220, 212, 212, 0.08) !important;
        padding-left: 8px !important;
        padding-right: 8px !important;
    }

    /* Кнопка меню на темном фоне */
    .btn-menu-toggle {
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.014) !important;
        background-color: rgba(223, 215, 215, 0.808);
    }

    /* Кнопка «+»: компактная квадратная кнопка 32x32px */
    .header-add-btn {
        width: 32px !important;
        height: 32px !important;
        padding: 0 !important;
        border-radius: 6px;
    }

    /* Уменьшение шрифта часов в компоненте header-clock, чтобы не распирало */
    :deep(.header-clock-widget),
    .header-clock-container :deep(.header-clock-widget) {
        height: 32px !important;
        padding: 2px 6px !important;
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    :deep(.header-clock-widget i) {
        font-size: 0.95rem !important;
        margin-right: 4px !important;
    }

    :deep(.header-clock-widget .font-monospace) {
        font-size: 0.78rem !important;
        color: #ffffff !important;
    }

    :deep(.header-clock-widget small) {
        font-size: 0.60rem !important;
        color: #fb923c !important;
        /* Мягкий оранжевый оттенок даты */
    }
}
</style>