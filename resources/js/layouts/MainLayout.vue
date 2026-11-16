<template>
    <div class="d-flex min-vh-100">
        <!-- Затемнение фона на мобильных устройствах при открытом меню -->
        <div v-if="isMobileOpen" class="sidebar-backdrop" @click="closeMobileSidebar"></div>

        <!-- Сайдбар -->
        <aside class="app-sidebar d-flex flex-column flex-shrink-0" :class="{
            collapsed: isCollapsed,
            'mobile-open': isMobileOpen
        }">
            <!-- Шапка сайдбара с логотипом -->
            <div class="sidebar-brand justify-content-between">
                <router-link to="/requests" class="d-flex align-items-center text-decoration-none"
                    @click="closeMobileSidebar">
                    <MandarinLogo :size="30" :show-text="!isCollapsed" />
                </router-link>
                <button class="btn btn-sm btn-link text-white d-md-none p-0" @click="closeMobileSidebar">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>

            <!-- Меню -->
            <div class="flex-grow-1 py-2 overflow-auto">
                <div class="nav-section-title">Документооборот</div>
                <ul class="nav flex-column mb-1">
                    <li class="nav-item">
                        <router-link to="/requests" class="nav-link justify-content-between" active-class="active"
                            title="Запросы" @click="closeMobileSidebar">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-inbox-fill"></i>
                                <span class="nav-text">Запросы</span>
                            </div>
                            <span v-if="!isCollapsed" class="badge rounded-pill badge-count-yellow px-2"
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
                            <div v-if="!isCollapsed" class="d-flex gap-1">
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
                            <span v-if="!isCollapsed" class="badge rounded-pill badge-count-green px-2"
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
                            <span v-if="!isCollapsed" class="badge rounded-pill badge-count-green px-2"
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
                            <span v-if="!isCollapsed" class="badge rounded-pill badge-count-yellow px-2"
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

                <!-- Блок админки в сайдбаре -->
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

            <!-- Футер сайдбара с профилем -->
            <div class="sidebar-footer d-flex align-items-center">
                <div class="d-flex align-items-center flex-grow-1 overflow-hidden" v-if="!isCollapsed">
                    <!-- Кликабельный блок пользователя с переходом в /profile -->
                    <router-link to="/profile"
                        class="user-info-link text-decoration-none text-truncate d-block flex-grow-1 me-2"
                        title="Перейти в личный профиль">
                        <!-- Вывод: Фамилия И. О. -->
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

        <!-- Основная рабочая зона -->
        <div class="flex-grow-1 d-flex flex-column min-w-0">
            <!-- ВЕРХНЯЯ ШАПКА: слева поиск, справа часы -->
            <header class="top-header d-flex align-items-center justify-content-between px-3">
                <!-- Левая часть: кнопка меню и поиск -->
                <div class="d-flex align-items-center">
                    <button @click="handleToggleMenu" class="btn btn-sm btn-outline-secondary me-2 me-md-3"
                        title="Свернуть / Развернуть меню">
                        <i class="bi bi-list fs-6"></i>
                    </button>

                    <div class="header-search input-group input-group-sm" style="width: 280px;">
                        <input type="text" class="form-control" placeholder="Поиск по VIN, номеру дела..." />
                        <button class="btn btn-outline-secondary" type="button">Найти</button>
                    </div>
                </div>

                <!-- Правая часть: ЧАСЫ И ДАТА В ФИРМЕННОМ СТИЛЕ -->
                <div class="d-flex align-items-center gap-2">
                    <HeaderClock />
                </div>
            </header>

            <!-- Контент страниц -->
            <main class="flex-grow-1 p-2 p-md-3 p-xl-4 overflow-auto">
                <router-view />
            </main>

            <!-- Нижняя панель для смартфонов -->
            <nav class="mobile-bottom-nav">
                <router-link to="/requests" class="bottom-nav-item" active-class="active">
                    <i class="bi bi-inbox-fill"></i>
                    <span class="mobile-badge-dot badge-count-yellow">12</span>
                    <span>Запросы</span>
                </router-link>

                <router-link to="/applications" class="bottom-nav-item" active-class="active">
                    <i class="bi bi-signpost-2-fill"></i>
                    <span class="mobile-badge-dot badge-count-green">17</span>
                    <span>Направления</span>
                </router-link>

                <router-link to="/mail" class="bottom-nav-item" active-class="active">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Почта</span>
                </router-link>

                <router-link to="/chat" class="bottom-nav-item" active-class="active">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span class="mobile-badge-dot badge-count-yellow">3</span>
                    <span>Чат</span>
                </router-link>

                <router-link v-if="authStore.isAdmin" to="/admin" class="bottom-nav-item" active-class="active">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Админ</span>
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
    await authStore.logout();
    router.push({ name: 'login' });
};
</script>