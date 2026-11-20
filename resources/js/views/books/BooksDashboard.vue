<template>
    <div class="books-dashboard-container d-flex flex-column h-100">
        <!-- ВЕРХНЯЯ ПАНЕЛЬ С ТАБАМИ СПРАВОЧНИКОВ -->
        <header class="books-nav-bar flex-shrink-0 bg-white border-bottom">
            <div class="books-nav-scroll">
                <div class="books-tabs-segmented" role="tablist">
                    <!-- 1. Типовые работы -->
                    <router-link :to="{ name: 'books.standard-works' }" class="books-tab-btn"
                        :class="{ active: isTabActive('books.standard-works') }">
                        <i class="bi bi-tools tab-icon"></i>
                        <span class="tab-label">Типовые работы</span>
                    </router-link>

                    <!-- 2. Телефонный справочник -->
                    <router-link :to="{ name: 'books.phones' }" class="books-tab-btn"
                        :class="{ active: isTabActive('books.phones') }">
                        <i class="bi bi-telephone-fill tab-icon"></i>
                        <span class="tab-label">Телефонный справочник</span>
                    </router-link>

                    <!-- 3. Наши Юр. Лица -->
                    <router-link :to="{ name: 'books.legal-entities' }" class="books-tab-btn"
                        :class="{ active: isTabActive('books.legal-entities') }">
                        <i class="bi bi-building-fill tab-icon"></i>
                        <span class="tab-label">Наши Юр. Лица</span>
                    </router-link>

                    <!-- 4. Контрагенты -->
                    <router-link :to="{ name: 'books.counterparties' }" class="books-tab-btn"
                        :class="{ active: isTabActive('books.counterparties') }">
                        <i class="bi bi-people-fill tab-icon"></i>
                        <span class="tab-label">Контрагенты</span>
                    </router-link>

                    <!-- 5. Страховые компании -->
                    <router-link :to="{ name: 'books.insurance-companies' }" class="books-tab-btn"
                        :class="{ active: isTabActive('books.insurance-companies') }">
                        <i class="bi bi-shield-check tab-icon"></i>
                        <span class="tab-label">Страховые компании</span>
                    </router-link>
                </div>
            </div>
        </header>

        <!-- КОНТЕНТ ВЫБРАННОГО СПРАВОЧНИКА -->
        <main class="books-content-area flex-grow-1 p-2 p-md-3 overflow-auto">
            <router-view />
        </main>
    </div>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();

const isTabActive = (routeName) => {
    return route.name === routeName;
};
</script>

<style scoped>
.books-dashboard-container {
    min-height: 0;
    width: 100%;
}

/* Навбар */
.books-nav-bar {
    position: relative;
    z-index: 5;
    background-color: #ffffff;
}

/* Скролл-контейнер для мобильных экранов */
.books-nav-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 6px 10px;
}

.books-nav-scroll::-webkit-scrollbar {
    display: none;
}

/* Сегментированная подложка под кнопки */
.books-tabs-segmented {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background-color: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}

/* Кнопка таба */
.books-tab-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    min-height: 32px;
    font-size: 0.80rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    transition: all 0.15s ease-in-out;
    user-select: none;
}

.books-tab-btn .tab-icon {
    font-size: 0.90rem;
    line-height: 1;
    opacity: 0.85;
}

/* Ховер */
.books-tab-btn:hover {
    color: #1e293b;
    background-color: rgba(255, 255, 255, 0.7);
}

/* Активный пункт меню — фирменный оранжевый акцент */
.books-tab-btn.active {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #ffffff !important;
    box-shadow: 0 2px 5px rgba(234, 88, 12, 0.3);
}

.books-tab-btn.active .tab-icon {
    opacity: 1;
}

/* Рабочая область */
.books-content-area {
    min-height: 0;
    background-color: #f8fafc;
}

/* Адаптив под маленькие экраны */
@media (max-width: 767.98px) {
    .books-nav-scroll {
        padding: 5px 6px;
    }

    .books-tabs-segmented {
        gap: 3px;
        padding: 2px;
    }

    .books-tab-btn {
        font-size: 0.74rem;
        padding: 6px 10px;
        min-height: 32px;
    }

    .books-tab-btn .tab-icon {
        font-size: 0.82rem;
    }
}
</style>