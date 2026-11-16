<template>
    <div class="dashboard-page">
        <!-- 1. ВЕРХНИЙ БЛОК ДЕЙСТВИЙ И ЗАГОЛОВКА -->
        <div
            class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
            <div>
                <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-speedometer2 text-primary me-2"></i>
                    <span>Рабочий стол</span>
                </h4>
                <small class="text-muted">Оперативная сводка по направлениям и запросам</small>
            </div>

            <!-- Кнопки действий: на смартфонах растягиваются на всю ширину -->
            <div class="d-flex gap-2 w-100 w-sm-auto justify-content-end">
                <button
                    class="btn btn-secondary flex-grow-1 flex-sm-grow-0 d-flex align-items-center justify-content-center"
                    @click="fetchData">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    <span>Обновить</span>
                </button>
                <button
                    class="btn btn-primary flex-grow-1 flex-sm-grow-0 d-flex align-items-center justify-content-center">
                    <i class="bi bi-plus-lg me-1"></i>
                    <span>Новое дело</span>
                </button>
            </div>
        </div>

        <!-- 2. МЕТРИКИ (Mobile: 2x2, Tablet: 2x2, Desktop: 4 в ряд) -->
        <div class="row g-2 g-md-3 mb-3 mb-md-4">
            <!-- Всего заявок -->
            <div class="col-6 col-xl-3">
                <div class="card p-2 p-md-3 h-100 border-0 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-medium">Всего в работе</div>
                            <div class="fs-4 fs-md-3 fw-bold mt-1 text-dark">128</div>
                        </div>
                        <div
                            class="stat-icon bg-secondary bg-opacity-10 text-secondary rounded p-2 p-md-3 d-none d-sm-flex align-items-center justify-content-center">
                            <i class="bi bi-folder2-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- В работе МРК -->
            <div class="col-6 col-xl-3">
                <div class="card p-2 p-md-3 h-100 border-0 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-medium">Расчет МРК</div>
                            <div class="fs-4 fs-md-3 fw-bold mt-1 text-primary">14</div>
                        </div>
                        <div
                            class="stat-icon bg-primary bg-opacity-10 text-primary rounded p-2 p-md-3 d-none d-sm-flex align-items-center justify-content-center">
                            <i class="bi bi-gear-wide-connected fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- На согласовании -->
            <div class="col-6 col-xl-3">
                <div class="card p-2 p-md-3 h-100 border-0 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-medium">Согласование</div>
                            <div class="fs-4 fs-md-3 fw-bold mt-1" style="color: #e65100;">23</div>
                        </div>
                        <div
                            class="stat-icon bg-warning bg-opacity-10 text-warning rounded p-2 p-md-3 d-none d-sm-flex align-items-center justify-content-center">
                            <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Завершено -->
            <div class="col-6 col-xl-3">
                <div class="card p-2 p-md-3 h-100 border-0 stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-medium">Выдано / Финал</div>
                            <div class="fs-4 fs-md-3 fw-bold mt-1 text-success">45</div>
                        </div>
                        <div
                            class="stat-icon bg-success bg-opacity-10 text-success rounded p-2 p-md-3 d-none d-sm-flex align-items-center justify-content-center">
                            <i class="bi bi-check2-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ПАНЕЛЬ БЫСТРЫХ ФИЛЬТРОВ И ПОИСКА -->
        <div class="card border-0 mb-3 p-2 p-md-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5 col-xl-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i
                                class="bi bi-search"></i></span>
                        <input v-model="searchQuery" type="text" class="form-control border-start-0"
                            placeholder="Поиск по VIN, номеру дела, ТС..." />
                        <button v-if="searchQuery" class="btn btn-outline-secondary" @click="searchQuery = ''">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-3">
                    <select v-model="filterStatus" class="form-select form-select-sm">
                        <option value="">Все статусы</option>
                        <option value="mrk">Расчет МРК</option>
                        <option value="approval">На согласовании</option>
                        <option value="service">Ремонт на СТО</option>
                        <option value="done">Выдано</option>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-xl-3">
                    <select v-model="filterCompany" class="form-select form-select-sm">
                        <option value="">Все страховые</option>
                        <option value="vsk">Страховой Дом ВСК</option>
                        <option value="soglasie">Согласие</option>
                        <option value="reso">РЕСО-Гарантия</option>
                    </select>
                </div>

                <div class="col-12 col-xl-2 text-xl-end d-none d-xl-block">
                    <span class="text-muted small">Найдено: <strong>{{ filteredApplications.length }}</strong></span>
                </div>
            </div>
        </div>

        <!-- 4. ОСНОВНОЙ БЛОК ДЕЛ / ЗАЯВОК (АДАПТИВНЫЙ) -->
        <div class="card border-0 responsive-table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    <i class="bi bi-card-checklist text-primary me-1"></i>
                    Активные направления и запросы
                </span>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-light border d-none d-sm-inline-flex align-items-center">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Экспорт
                    </button>
                    <router-link to="/applications" class="btn btn-sm btn-secondary">
                        Все дела &rarr;
                    </router-link>
                </div>
            </div>

            <!-- ВАРИАНТ А: ТАБЛИЦА ДЛЯ DESKTOP И TABLET (>= 768px) -->
            <div class="desktop-table-view table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 130px;">№ Дела</th>
                            <th>Марка, модель</th>
                            <th>Госномер / VIN</th>
                            <th>Страховая</th>
                            <th>Ответственный</th>
                            <th>Статус</th>
                            <th class="text-center" style="width: 140px;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="app in filteredApplications" :key="app.id">
                            <td class="fw-bold text-primary">{{ app.number }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ app.car }}</div>
                                <small class="text-muted">{{ app.year }} г.в.</small>
                            </td>
                            <td>
                                <div><span class="badge bg-light text-dark border">{{ app.gnz }}</span></div>
                                <small class="text-muted font-monospace">{{ app.vin }}</small>
                            </td>
                            <td>{{ app.insurance }}</td>
                            <td>
                                <div class="small fw-medium">{{ app.manager }}</div>
                                <small class="text-muted">{{ app.role }}</small>
                            </td>
                            <td>
                                <span class="badge shadow-sm" :class="app.statusClass">{{ app.statusText }}</span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light border" title="Просмотр карточки">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-primary" title="Взять в работу">
                                        В работу
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredApplications.length === 0">
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                По заданным критериям ничего не найдено
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ВАРИАНТ Б: КАРТОЧНЫЙ ВИД ДЛЯ СМАРТФОНОВ (< 768px) -->
            <div class="mobile-cards-view p-2">
                <div v-for="app in filteredApplications" :key="'mob-' + app.id"
                    class="card p-3 mb-2 border shadow-sm mobile-app-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="fw-bold text-primary fs-6">#{{ app.number }}</span>
                            <div class="fw-bold text-dark mt-1">{{ app.car }} <span
                                    class="text-muted fw-normal small">({{ app.year }})</span></div>
                        </div>
                        <span class="badge shadow-sm" :class="app.statusClass">{{ app.statusText }}</span>
                    </div>

                    <div class="bg-light rounded p-2 mb-2 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Госномер:</span>
                            <span class="fw-bold text-dark">{{ app.gnz }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Страховая:</span>
                            <span class="text-dark">{{ app.insurance }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Менеджер:</span>
                            <span class="text-dark">{{ app.manager }} ({{ app.role }})</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-sm btn-light border flex-grow-1 py-2">
                            <i class="bi bi-eye me-1"></i> Карточка
                        </button>
                        <button class="btn btn-sm btn-primary flex-grow-1 py-2">
                            <i class="bi bi-play-fill me-1"></i> В работу
                        </button>
                    </div>
                </div>

                <div v-if="filteredApplications.length === 0" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                    Дел не найдено
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const searchQuery = ref('');
const filterStatus = ref('');
const filterCompany = ref('');

// Демо-данные дел
const applications = ref([
    {
        id: 1,
        number: '09412/2026',
        car: 'Hyundai Solaris',
        year: 2021,
        gnz: 'А123АА 777',
        vin: 'Z94CT41DBLR014921',
        insurance: 'ВСК',
        manager: 'Иванов А.С.',
        role: 'МРК',
        statusCode: 'mrk',
        statusText: 'Расчет МРК',
        statusClass: 'bg-warning text-dark',
    },
    {
        id: 2,
        number: '09413/2026',
        car: 'Kia Sportage',
        year: 2020,
        gnz: 'В456ВВ 777',
        vin: 'U5YPC811DGL029144',
        insurance: 'ВСК',
        manager: 'Смирнов К.И.',
        role: 'Эксперт',
        statusCode: 'approval',
        statusText: 'Согласование',
        statusClass: 'bg-info text-dark',
    },
    {
        id: 3,
        number: '09414/2026',
        car: 'Toyota Camry',
        year: 2022,
        gnz: 'Е789КХ 777',
        vin: 'XW7BF4FK30S091241',
        insurance: 'Согласие',
        manager: 'Петров С.В.',
        role: 'СПМ',
        statusCode: 'service',
        statusText: 'Ремонт на СТО',
        statusClass: 'bg-primary text-white',
    },
    {
        id: 4,
        number: '09415/2026',
        car: 'Lada Vesta',
        year: 2023,
        gnz: 'М321УУ 777',
        vin: 'XTAGFL110PY012984',
        insurance: 'РЕСО-Гарантия',
        manager: 'Иванов А.С.',
        role: 'МРК',
        statusCode: 'done',
        statusText: 'Готово к выдаче',
        statusClass: 'bg-success text-white',
    },
]);

// Фильтрация
const filteredApplications = computed(() => {
    return applications.value.filter((app) => {
        const matchesSearch =
            !searchQuery.value ||
            app.number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            app.car.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            app.gnz.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            app.vin.toLowerCase().includes(searchQuery.value.toLowerCase());

        const matchesStatus = !filterStatus.value || app.statusCode === filterStatus.value;
        const matchesCompany =
            !filterCompany.value ||
            (filterCompany.value === 'vsk' && app.insurance.includes('ВСК')) ||
            (filterCompany.value === 'soglasie' && app.insurance.includes('Согласие')) ||
            (filterCompany.value === 'reso' && app.insurance.includes('РЕСО'));

        return matchesSearch && matchesStatus && matchesCompany;
    });
});

const fetchData = () => {
    // Заглушка для обновления данных
};
</script>

<style scoped>
.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 48px;
    height: 48px;
}

.mobile-app-card {
    border-radius: 6px;
}
</style>