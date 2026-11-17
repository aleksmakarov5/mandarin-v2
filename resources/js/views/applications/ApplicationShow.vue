<template>
    <div class="application-page d-flex flex-column h-100 bg-light">
        <div v-if="loading" class="flex-grow-1 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-warning" role="status" />
        </div>

        <template v-else-if="form">
            <header class="app-header flex-shrink-0 bg-white border-bottom px-2 px-md-3 py-2
               d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="goBack">
                        <i class="bi bi-arrow-left" />
                        <span class="d-none d-sm-inline ms-1">Назад</span>
                    </button>

                    <span class="badge bg-dark font-monospace fs-6">
                        #{{ form.id }}
                    </span>

                    <select v-model.number="form.id_status" class="form-select form-select-sm status-select fw-bold"
                        :style="statusStyle" aria-label="Статус дела">
                        <option :value="null">Без статуса</option>
                        <option v-for="status in options.statuses" :key="status.id" :value="Number(status.id)">
                            {{ status.name }}
                        </option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <small v-if="autoSaveStatus === 'saving'" class="text-muted">
                        Сохранение…
                    </small>
                    <small v-else-if="autoSaveStatus === 'saved'" class="text-success">
                        <i class="bi bi-cloud-check" />
                        <span class="d-none d-md-inline ms-1">Сохранено</span>
                    </small>
                    <small v-else-if="autoSaveStatus === 'error'" class="text-danger">
                        Ошибка сохранения
                    </small>

                    <button type="button" class="btn btn-sm btn-primary fw-semibold" :disabled="saving"
                        @click="saveApplication">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" />
                        {{ saving ? 'Сохранение…' : 'Сохранить' }}
                    </button>
                </div>
            </header>

            <section class="summary-bar flex-shrink-0 bg-white border-bottom px-2 px-md-3 py-2">
                <div class="row g-2 align-items-center small">
                    <div class="col-12 col-md-4 text-truncate">
                        <i class="bi bi-car-front-fill text-secondary me-1" />
                        <strong>{{ form.CarModel || 'Автомобиль не указан' }}</strong>
                        <span v-if="form.gnz" class="plate ms-2">{{ form.gnz }}</span>
                    </div>

                    <div class="col-12 col-md-4 text-truncate">
                        <i class="bi bi-person-fill text-primary me-1" />
                        <strong>{{ form.ClientName || 'Клиент не указан' }}</strong>
                        <a v-if="form.ClientPhone" :href="`tel:+7${phoneDigits(form.ClientPhone).slice(-10)}`"
                            class="ms-2 text-decoration-none">
                            {{ form.ClientPhone }}
                        </a>
                    </div>

                    <div class="col-12 col-md-4 text-truncate">
                        <i class="bi bi-shield-check text-info me-1" />
                        {{ currentInsuranceName }}
                        <span class="text-muted ms-1">
                            № {{ form.numberInsuranceCompanyApplication || 'б/н' }}
                        </span>
                    </div>
                </div>
            </section>

            <nav class="tabs-bar flex-shrink-0 bg-white border-bottom">
                <div class="tabs-scroll">
                    <div class="tabs-wrapper">
                        <button v-for="tab in tabs" :key="tab.id" type="button" class="tab-button"
                            :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
                            <i :class="tab.icon" class="me-1" />
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </nav>

            <main class="flex-grow-1 overflow-auto p-2 p-md-3" @focusout="onFieldFocusOut" @change="onFieldChange">
                <!-- ОСНОВНОЕ -->
                <div v-show="activeTab === 'general'" class="row g-3">
                    <div class="col-12 col-xl-8">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <section class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-white fw-bold">Автомобиль</div>
                                    <div class="card-body row g-2">
                                        <div class="col-12">
                                            <label class="form-label">Марка и модель *</label>
                                            <input v-model.trim="form.CarModel" type="text"
                                                class="form-control form-control-sm text-uppercase">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Госномер</label>
                                            <input v-model.trim="form.gnz" type="text"
                                                class="form-control form-control-sm text-uppercase">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Год выпуска</label>
                                            <input v-model.trim="form.mnf_year" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">VIN</label>
                                            <input v-model.trim="form.vin" type="text" maxlength="17"
                                                class="form-control form-control-sm text-uppercase font-monospace">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">ПТС / свидетельство</label>
                                            <input v-model.trim="form.vehicle_passport" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Цвет</label>
                                            <input v-model.trim="form.color" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Пробег, км</label>
                                            <input v-model.number="form.mileage" type="number" min="0"
                                                class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="col-12 col-lg-6">
                                <section class="card border-0 shadow-sm h-100">
                                    <div class="card-header bg-white fw-bold">Клиент</div>
                                    <div class="card-body row g-2">
                                        <div class="col-12">
                                            <label class="form-label">ФИО</label>
                                            <input v-model.trim="form.ClientName" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Пол</label>
                                            <select v-model="form.gender" class="form-select form-select-sm">
                                                <option :value="null">Не указан</option>
                                                <option :value="1">Мужской</option>
                                                <option :value="0">Женский</option>
                                            </select>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Дата рождения</label>
                                            <input v-model="form.birthday" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Телефон клиента</label>
                                            <div class="input-group input-group-sm">
                                                <input :value="form.ClientPhone" type="tel" inputmode="tel"
                                                    maxlength="18" class="form-control font-monospace"
                                                    :class="{ 'is-invalid': phoneErrors.ClientPhone }"
                                                    placeholder="+7 (999) 123-45-67"
                                                    @input="onPhoneInput($event, 'ClientPhone')"
                                                    @blur="validatePhoneField('ClientPhone')">
                                                <a v-if="isPhoneValid(form.ClientPhone)" class="btn btn-outline-success"
                                                    :href="`tel:+7${phoneDigits(form.ClientPhone).slice(-10)}`">
                                                    <i class="bi bi-telephone-fill" />
                                                </a>
                                            </div>
                                            <div class="invalid-feedback d-block">
                                                {{ phoneErrors.ClientPhone }}
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Представитель</label>
                                            <input v-model.trim="form.pred" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Телефон представителя</label>
                                            <input :value="form.pred_phone" type="tel" inputmode="tel" maxlength="18"
                                                class="form-control form-control-sm font-monospace"
                                                :class="{ 'is-invalid': phoneErrors.pred_phone }"
                                                placeholder="+7 (999) 123-45-67"
                                                @input="onPhoneInput($event, 'pred_phone')"
                                                @blur="validatePhoneField('pred_phone')">
                                            <div class="invalid-feedback d-block">
                                                {{ phoneErrors.pred_phone }}
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="col-12">
                                <section class="card border-0 shadow-sm">
                                    <div class="card-header bg-white fw-bold">Паспорт клиента</div>
                                    <div class="card-body row g-2">
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Серия</label>
                                            <input v-model.trim="form.ClientPasport" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Номер</label>
                                            <input v-model.trim="form.ClientPasport_number" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Дата выдачи</label>
                                            <input v-model="form.ClientPasport_date" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12 col-md-5">
                                            <label class="form-label">Кем выдан</label>
                                            <input v-model.trim="form.ClientPasport_struct" type="text"
                                                class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="col-12 col-lg-6">
                                <section class="card border-0 shadow-sm">
                                    <div class="card-header bg-white fw-bold">
                                        Страховая компания и убыток
                                    </div>
                                    <div class="card-body row g-2">
                                        <div class="col-12">
                                            <label class="form-label">Страховая компания *</label>
                                            <select v-model.number="form.id_insuranceCompany"
                                                class="form-select form-select-sm">
                                                <option :value="null">Не выбрана</option>
                                                <option v-for="company in options.insurance_companies" :key="company.id"
                                                    :value="Number(company.id)">
                                                    {{ company.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-7">
                                            <label class="form-label">Номер дела в СК *</label>
                                            <input v-model.trim="form.numberInsuranceCompanyApplication" type="text"
                                                maxlength="40" class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12 col-md-5">
                                            <label class="form-label">Номер убытка</label>
                                            <input v-model.trim="form.ub_number" type="text"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Тип убытка</label>
                                            <select v-model.number="form.ub_type" class="form-select form-select-sm">
                                                <option :value="0">ОСАГО</option>
                                                <option :value="1">КАСКО</option>
                                            </select>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label">Номер договора</label>
                                            <input v-model.trim="form.dogovor" type="text"
                                                class="form-control form-control-sm">
                                        </div>


                                    </div>
                                </section>
                            </div>

                            <div class="col-12 col-lg-6">
                                <section class="card border-0 shadow-sm">
                                    <div class="card-header bg-white fw-bold">Филиал</div>
                                    <div class="card-body row g-2">
                                        <div class="col-12">
                                            <label class="form-label">Наш филиал *</label>
                                            <select v-model.number="form.our_company"
                                                class="form-select form-select-sm">
                                                <option :value="null">Не выбран</option>
                                                <option v-for="branch in options.branches" :key="branch.id"
                                                    :value="Number(branch.id)">
                                                    {{ branch.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Город</label>
                                            <select v-model.number="form.id_city" class="form-select form-select-sm">
                                                <option :value="0">Не выбран</option>
                                                <option v-for="city in options.cities" :key="city.id"
                                                    :value="Number(city.id)">
                                                    {{ city.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <div v-if="options.services.length" class="col-12">
                                            <label class="form-label">СТО</label>
                                            <select v-model.number="form.id_service" class="form-select form-select-sm">
                                                <option :value="null">Не выбрана</option>
                                                <option v-for="service in options.services" :key="service.id"
                                                    :value="Number(service.id)">
                                                    {{ service.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="col-12">
                                <section class="card border-0 shadow-sm">
                                    <div class="card-header bg-white fw-bold">Сроки</div>
                                    <div class="card-body row g-2">
                                        <div class="col-6 col-md-4 col-xxl-2">
                                            <label class="form-label">Дата ДТП</label>
                                            <input v-model="form.DTPdate" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6 col-md-4 col-xxl-2">
                                            <label class="form-label">Направление</label>
                                            <input v-model="form.napr_date" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-12 col-md-4 col-xxl-2">
                                            <label class="form-label">Осмотр</label>
                                            <input v-model="form.view_date" type="datetime-local"
                                                class="form-control form-control-sm">
                                        </div>



                                        <div class="col-6 col-md-4 col-xxl-2">
                                            <label class="form-label">Поставка запчастей</label>
                                            <input v-model="form.sp_date" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6 col-md-4 col-xxl-2">
                                            <label class="form-label">Начало ремонта</label>
                                            <input v-model="form.start_date" type="date"
                                                class="form-control form-control-sm">
                                        </div>

                                        <div class="col-6 col-md-4 col-xxl-2">
                                            <label class="form-label">Окончание ремонта</label>
                                            <input v-model="form.end_date" type="date"
                                                class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <section class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold text-uppercase mb-3">
                                    <i class="bi bi-people-fill text-primary me-2" />
                                    Ответственные лица
                                </h6>

                                <div class="mb-3">
                                    <label class="form-label">Менеджер (МРК)</label>
                                    <select v-model.number="form.id_MRK" class="form-select form-select-sm">
                                        <option :value="null">Не назначен</option>
                                        <option v-for="employee in branchStaff.mrk" :key="employee.id"
                                            :value="Number(employee.id)">
                                            {{ employee.name }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="mentorStorageAvailable" class="mb-3">
                                    <label class="form-label">Наставник</label>
                                    <select v-model.number="form.id_mentor" class="form-select form-select-sm">
                                        <option :value="null">Не назначен</option>
                                        <option v-for="employee in branchStaff.mentors" :key="employee.id"
                                            :value="Number(employee.id)">
                                            {{ employee.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Запчастист (СПМ)</label>
                                    <select v-model.number="form.id_spm" class="form-select form-select-sm">
                                        <option :value="null">Не назначен</option>
                                        <option v-for="employee in branchStaff.spm" :key="employee.id"
                                            :value="Number(employee.id)">
                                            {{ employee.name }}
                                        </option>
                                    </select>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">Юрист по делу</label>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                        @click="openLawyerModal">
                                        + Новый юрист
                                    </button>
                                </div>

                                <select v-model.number="form.id_lawyer" class="form-select form-select-sm fw-semibold"
                                    :style="lawyerSelectStyle">
                                    <option :value="null">Без юриста</option>
                                    <option v-for="lawyer in lawyersList" :key="lawyer.id" :value="Number(lawyer.id)"
                                        :style="lawyerOptionStyle(lawyer)">
                                        {{ lawyer.is_cooperating ? '●' : '●' }}
                                        {{ lawyer.name }}
                                        {{ lawyer.phone ? `(${lawyer.phone})` : '' }}
                                    </option>
                                </select>

                                <small v-if="currentLawyer" class="d-block mt-1 fw-semibold"
                                    :class="currentLawyer.is_cooperating ? 'text-success' : 'text-danger'">
                                    {{ currentLawyer.is_cooperating
                                        ? 'Сотрудничаем'
                                        : 'Не сотрудничаем' }}
                                </small>

                                <h6 class="fw-bold text-uppercase mt-4 mb-2">
                                    Заметка по делу
                                </h6>
                                <textarea v-model.trim="form.comment_true" rows="6" maxlength="255"
                                    class="form-control form-control-sm" placeholder="Внутренний комментарий" />
                            </div>
                        </section>
                    </div>
                </div>

                <!-- ДОКУМЕНТЫ -->
                <div v-show="activeTab === 'documents'">
                    <ApplicationDocumentsTab :application-id="form.id" :initial-documents="form.documents || []" />
                </div>

                <!-- ПЕРЕПИСКА -->
                <div v-show="activeTab === 'chat'" class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold">Переписка с СК</h6>
                    <p class="text-muted mb-0">
                        Здесь подключается существующий модуль переписки.
                    </p>
                </div>

                <!-- СМЕТА -->
                <div v-show="activeTab === 'smeta'" class="row g-3">
                    <div class="col-12 col-lg-6">
                        <section class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-bold">Даты оплат</div>
                            <div class="card-body row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Оплата СК</label>
                                    <input v-model="form.ic_pay_date" type="date" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Оплата СТО</label>
                                    <input v-model="form.sto_pay_date" type="date" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Дата зарплаты</label>
                                    <input v-model="form.zp_date" type="date" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Финальная оплата</label>
                                    <input v-model="form.finish_pay_date" type="date"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- ДЕФЕКТОВКА -->
                <div v-show="activeTab === 'defect'" class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold">Дефектовка</h6>
                    <p class="text-muted mb-0">
                        Здесь подключается существующий модуль дефектовки.
                    </p>
                </div>

                <!-- ИСТОРИЯ -->
                <div v-show="activeTab === 'history'" class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold">История дела #{{ form.id }}</h6>
                    <div class="small text-muted">
                        Создано: {{ formatDateTime(form.created_at) }}<br>
                        Изменено: {{ formatDateTime(form.updated_at) }}
                    </div>
                </div>
            </main>
        </template>

        <!-- НОВЫЙ ЮРИСТ -->
        <Teleport to="body">
            <div v-if="showLawyerModal" class="modal-backdrop fade show" style="z-index: 1055" />
            <div v-if="showLawyerModal" class="modal d-block" tabindex="-1" style="z-index: 1056"
                @click.self="showLawyerModal = false">
                <div class="modal-dialog modal-dialog-centered px-3">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <h6 class="modal-title fw-bold">Новый юрист</h6>
                            <button type="button" class="btn-close" @click="showLawyerModal = false" />
                        </div>

                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label">ФИО *</label>
                                <input v-model.trim="newLawyer.name" type="text" class="form-control form-control-sm">
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Телефон</label>
                                <input :value="newLawyer.phone" type="tel" inputmode="tel" maxlength="18"
                                    class="form-control form-control-sm font-monospace"
                                    :class="{ 'is-invalid': phoneErrors.lawyerPhone }" placeholder="+7 (999) 123-45-67"
                                    @input="onPhoneInput($event, 'phone', newLawyer, 'lawyerPhone')"
                                    @blur="validatePhoneField('phone', newLawyer, 'lawyerPhone')">
                                <div class="invalid-feedback d-block">
                                    {{ phoneErrors.lawyerPhone }}
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Email</label>
                                <input v-model.trim="newLawyer.email" type="email" class="form-control form-control-sm">
                            </div>

                            <div class="form-check mb-2">
                                <input id="lawyer-cooperating" v-model="newLawyer.is_cooperating" type="checkbox"
                                    class="form-check-input">
                                <label class="form-check-label" for="lawyer-cooperating">
                                    Сотрудничаем
                                </label>
                            </div>

                            <label class="form-label">Примечание</label>
                            <textarea v-model.trim="newLawyer.comment" rows="2" class="form-control form-control-sm" />
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                @click="showLawyerModal = false">
                                Отмена
                            </button>
                            <button type="button" class="btn btn-sm btn-primary"
                                :disabled="creatingLawyer || !newLawyer.name" @click="createLawyer">
                                <span v-if="creatingLawyer" class="spinner-border spinner-border-sm me-1" />
                                Создать и выбрать
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import ApplicationDocumentsTab
    from '../../components/applications/ApplicationDocumentsTab.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const saving = ref(false);
const activeTab = ref('general');
const autoSaveStatus = ref('saved');
const form = ref(null);
const lastSavedPayload = ref({});
const mentorStorageAvailable = ref(false);

const tabs = [
    { id: 'general', label: 'Основное', icon: 'bi bi-info-circle' },
    { id: 'documents', label: 'Документы', icon: 'bi bi-folder2-open' },
    { id: 'chat', label: 'Переписка с СК', icon: 'bi bi-chat-left-text' },
    { id: 'smeta', label: 'Смета', icon: 'bi bi-calculator' },
    { id: 'defect', label: 'Дефектовка', icon: 'bi bi-wrench-adjustable' },
    { id: 'history', label: 'История', icon: 'bi bi-clock-history' },
];

const options = reactive({
    statuses: [],
    cities: [],
    branches: [],
    insurance_companies: [],
    services: [],
});

const branchStaff = reactive({
    mrk: [],
    mentors: [],
    spm: [],
});

const lawyersList = ref([]);
const phoneErrors = reactive({});

const showLawyerModal = ref(false);
const creatingLawyer = ref(false);
const newLawyer = reactive({
    name: '',
    phone: '',
    email: '',
    comment: '',
    is_cooperating: true,
});

const editableFields = [
    'CarModel', 'gnz', 'vin', 'mnf_year', 'vehicle_passport',
    'color', 'mileage',
    'ClientName', 'ClientPhone', 'gender', 'birthday', 'pred', 'pred_phone',
    'client_passport', 'ClientPasport_number', 'ClientPasport_date',
    'ClientPasport_struct',
    'id_insuranceCompany', 'numberInsuranceCompanyApplication',
    'ub_number', 'ub_type', 'dogovor',
    'our_company', 'id_city', 'id_service',
    'id_status', 'id_MRK', 'id_spm', 'id_lawyer',
    'DTPdate', 'napr_date', 'view_date', 'check_date', 'sp_date',
    'start_date', 'end_date',
    'ic_pay_date', 'sto_pay_date', 'zp_date', 'finish_pay_date',
    'comment_true', 'comment_false',
];

const dateFields = [
    'DTPdate', 'ClientPasport_date', 'birthday', 'napr_date',
    'sp_date', 'start_date', 'end_date', 'ic_pay_date',
    'sto_pay_date', 'zp_date', 'finish_pay_date',
];

const dateTimeFields = ['view_date', 'check_date'];

const currentStatus = computed(() => options.statuses.find(
    (status) => Number(status.id) === Number(form.value?.id_status),
));

const statusStyle = computed(() => ({
    backgroundColor: currentStatus.value?.bg_color || '#e2e8f0',
    color: currentStatus.value?.text_color || '#0f172a',
    borderColor: currentStatus.value?.bg_color || '#cbd5e1',
}));

const currentInsuranceName = computed(() => {
    const company = options.insurance_companies.find(
        (item) => Number(item.id) === Number(form.value?.id_insuranceCompany),
    );

    return company?.name
        || form.value?.insurance_company?.name
        || 'Страховая компания не выбрана';
});

const currentLawyer = computed(() => {
    const selected = lawyersList.value.find(
        (lawyer) => Number(lawyer.id) === Number(form.value?.id_lawyer),
    );

    return selected || form.value?.lawyer || null;
});

const lawyerOptionStyle = (lawyer) => ({
    color: lawyer.is_cooperating ? '#15803d' : '#dc2626',
});

const lawyerSelectStyle = computed(() => {
    if (!currentLawyer.value) return {};

    return {
        color: currentLawyer.value.is_cooperating ? '#15803d' : '#dc2626',
    };
});

function phoneDigits(value) {
    return String(value || '').replace(/\D/g, '');
}

function formatPhoneDigits(value) {
    let digits = phoneDigits(value);

    if (!digits) return '';

    if (digits[0] === '8') {
        digits = `7${digits.slice(1)}`;
    } else if (digits[0] !== '7') {
        digits = `7${digits}`;
    }

    digits = digits.slice(0, 11);

    let result = '+7';

    if (digits.length > 1) {
        result += ` (${digits.slice(1, 4)}`;
    }
    if (digits.length >= 4) {
        result += ')';
    }
    if (digits.length > 4) {
        result += ` ${digits.slice(4, 7)}`;
    }
    if (digits.length > 7) {
        result += `-${digits.slice(7, 9)}`;
    }
    if (digits.length > 9) {
        result += `-${digits.slice(9, 11)}`;
    }

    return result;
}

function isPhoneValid(value) {
    if (!value) return true;

    const digits = phoneDigits(value);
    return digits.length === 11 && digits[0] === '7';
}

function onPhoneInput(event, field, target = form.value, errorKey = field) {
    target[field] = formatPhoneDigits(event.target.value);
    event.target.value = target[field];

    if (!target[field] || isPhoneValid(target[field])) {
        delete phoneErrors[errorKey];
    }
}

function validatePhoneField(field, target = form.value, errorKey = field) {
    const value = target?.[field];

    if (!value || isPhoneValid(value)) {
        delete phoneErrors[errorKey];
        return true;
    }

    phoneErrors[errorKey] = 'Введите телефон полностью: +7 (999) 123-45-67';
    return false;
}

function validatePhones() {
    const clientValid = validatePhoneField('ClientPhone');
    const representativeValid = validatePhoneField('pred_phone');

    return clientValid && representativeValid;
}

function normalizeDate(value) {
    return value ? String(value).slice(0, 10) : null;
}

function normalizeDateTime(value) {
    if (!value) return null;

    return String(value)
        .replace(' ', 'T')
        .slice(0, 16);
}

function normalizeApplication(data) {
    const result = { ...data };

    dateFields.forEach((field) => {
        result[field] = normalizeDate(result[field]);
    });

    dateTimeFields.forEach((field) => {
        result[field] = normalizeDateTime(result[field]);
    });

    result.gender = result.gender === null || result.gender === ''
        ? null
        : Number(result.gender);

    result.ub_type = Number(result.ub_type ?? 0);
    result.id_city = Number(result.id_city ?? 0);

    ['ClientPhone', 'pred_phone'].forEach((field) => {
        if (result[field]) {
            const digits = phoneDigits(result[field]);
            if (digits.length === 10 || digits.length === 11) {
                result[field] = formatPhoneDigits(result[field]);
            }
        }
    });

    return result;
}

function buildPayload() {
    const payload = {};

    editableFields.forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(form.value, field)) {
            const value = form.value[field];

            // Пустые необязательные значения отправляем как NULL
            payload[field] = value === '' || value === undefined
                ? null
                : value;
        }
    });

    if (mentorStorageAvailable.value) {
        payload.id_mentor = form.value.id_mentor || null;
    }

    return payload;
}

/**
 * Возвращает только поля, изменённые после последнего
 * успешного сохранения.
 */
function getChangedPayload() {
    const currentPayload = buildPayload();
    const changedPayload = {};

    Object.entries(currentPayload).forEach(([field, value]) => {
        const savedValue = lastSavedPayload.value[field];

        // undefined, пустая строка и null считаются одинаковым пустым значением
        const normalizedValue = value ?? null;
        const normalizedSavedValue = savedValue ?? null;

        if (normalizedValue !== normalizedSavedValue) {
            changedPayload[field] = value;
        }
    });

    return changedPayload;
}

async function fetchApplication() {
    loading.value = true;

    try {
        const [applicationResponse, filtersResponse, lawyersResponse] =
            await Promise.all([
                axios.get(`/api/applications/${route.params.id}`),
                axios.get('/api/applications/filters'),
                axios.get('/api/lawyers'),
            ]);

        const application = applicationResponse.data.data;
        form.value = normalizeApplication(application);

        // Колонка id_mentor отсутствует в старом дампе. Если она добавлена,
        // Laravel вернёт её среди атрибутов, и поле автоматически появится.
        mentorStorageAvailable.value = Object.prototype.hasOwnProperty.call(
            application,
            'id_mentor',
        );

        const filters = filtersResponse.data.data || filtersResponse.data || {};
        options.statuses = filters.statuses || [];
        options.cities = filters.cities || [];
        options.branches = filters.branches || [];
        options.insurance_companies = filters.insurance_companies || [];
        options.services = filters.services || [];

        lawyersList.value = lawyersResponse.data.data
            || lawyersResponse.data
            || [];

        if (form.value.our_company) {
            await fetchBranchEmployees(form.value.our_company);
        }

        lastSavedPayload.value = { ...buildPayload() };
        autoSaveStatus.value = 'saved';
    } catch (error) {
        console.error(error);
        alert('Не удалось загрузить дело');
    } finally {
        loading.value = false;
    }
}

async function fetchBranchEmployees(branchId) {
    if (!branchId) return;

    try {
        const response = await axios.get('/api/applications/branch-employees', {
            params: { branch_id: Number(branchId) },
        });

        const data = response.data.data || response.data || {};
        branchStaff.mrk = data.mrk || [];
        branchStaff.mentors = data.mentors || [];
        branchStaff.spm = data.spm || [];
    } catch (error) {
        console.error('Не удалось загрузить сотрудников филиала', error);
    }
}

async function saveCore(silent = false) {
    if (!form.value?.id) return false;

    // Если запрос уже выполняется, не запускаем параллельный.
    // После завершения текущего запроса новые изменения будут найдены
    // через getChangedPayload() и сохранены отдельным запросом.
    if (saving.value) return false;

    if (!validatePhones()) {
        activeTab.value = 'general';

        if (!silent) {
            alert('Проверьте телефонные номера');
        }

        return false;
    }

    const payload = getChangedPayload();

    // Нет изменений — запрос не нужен
    if (!Object.keys(payload).length) {
        autoSaveStatus.value = 'saved';
        return true;
    }

    saving.value = true;
    autoSaveStatus.value = 'saving';

    let savedSuccessfully = false;

    try {
        console.debug('Сохраняемые поля:', payload);

        await axios.put(
            `/api/applications/${form.value.id}`,
            payload,
        );

        /*
         * ВАЖНО:
         * 1. Не перезаписываем form.value ответом сервера.
         * 2. Отмечаем сохранёнными только реально отправленные поля.
         *
         * Если пользователь изменил другое поле во время запроса,
         * оно не попадёт в lastSavedPayload и будет сохранено следующим запросом.
         */
        lastSavedPayload.value = {
            ...lastSavedPayload.value,
            ...payload,
        };

        savedSuccessfully = true;
        autoSaveStatus.value = 'saved';

        return true;
    } catch (error) {
        const responseData = error.response?.data;
        const validationErrors = responseData?.errors || {};

        const messages = Object.entries(validationErrors).flatMap(
            ([field, fieldErrors]) => {
                const errors = Array.isArray(fieldErrors)
                    ? fieldErrors
                    : [fieldErrors];

                return errors.map((message) => `${field}: ${message}`);
            },
        );

        autoSaveStatus.value = 'error';

        console.error('Ошибка сохранения дела', {
            status: error.response?.status,
            payload,
            response: responseData,
            error,
        });

        const errorText = messages.length
            ? messages.join('\n')
            : responseData?.debug
            || responseData?.message
            || 'Не удалось сохранить дело';

        if (!silent) {
            alert(errorText);
        } else {
            console.warn(`Автосохранение не выполнено:\n${errorText}`);
        }

        return false;
    } finally {
        saving.value = false;

        /*
         * Если во время успешного запроса пользователь изменил другие поля,
         * сохраняем их следующим запросом.
         * После ошибки автоматически не повторяем запрос, чтобы избежать цикла.
         */
        if (savedSuccessfully) {
            const remainingChanges = getChangedPayload();

            if (Object.keys(remainingChanges).length) {
                nextTick(() => {
                    saveCore(true);
                });
            }
        }
    }
}

function saveApplication() {
    return saveCore(false);
}

function triggerAutoSave() {
    return saveCore(true);
}

function onFieldFocusOut(event) {
    if (showLawyerModal.value) return;

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName)) {
        nextTick(triggerAutoSave);
    }
}

function onFieldChange(event) {
    if (showLawyerModal.value) return;

    if (['INPUT', 'SELECT'].includes(event.target?.tagName)) {
        nextTick(triggerAutoSave);
    }
}

function openLawyerModal() {
    Object.assign(newLawyer, {
        name: '',
        phone: '',
        email: '',
        comment: '',
        is_cooperating: true,
    });

    delete phoneErrors.lawyerPhone;
    showLawyerModal.value = true;
}

async function createLawyer() {
    if (!newLawyer.name || creatingLawyer.value) return;

    if (!validatePhoneField('phone', newLawyer, 'lawyerPhone')) {
        return;
    }

    creatingLawyer.value = true;

    try {
        const response = await axios.post('/api/lawyers', {
            ...newLawyer,
            is_cooperating: newLawyer.is_cooperating ? 1 : 0,
        });

        const lawyer = response.data.data || response.data;
        lawyersList.value.push(lawyer);
        form.value.id_lawyer = Number(lawyer.id);
        showLawyerModal.value = false;

        await saveCore(true);
    } catch (error) {
        console.error(error);
        alert(
            error.response?.data?.message
            || 'Не удалось создать юриста',
        );
    } finally {
        creatingLawyer.value = false;
    }
}

watch(
    () => form.value?.our_company,
    (branchId, previousBranchId) => {
        if (branchId && Number(branchId) !== Number(previousBranchId)) {
            fetchBranchEmployees(branchId);
        }
    },
);

function goBack() {
    router.back();
}

function formatDateTime(value) {
    if (!value) return '—';

    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? String(value)
        : date.toLocaleString('ru-RU');
}

function onKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        saveApplication();
    }
}

onMounted(() => {
    fetchApplication();
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<style scoped>
.application-page {
    min-width: 0;
    color: #1e293b;
}

.app-header {
    min-height: 52px;
}

.status-select {
    width: 170px;
    max-width: 42vw;
}

.summary-bar {
    font-size: 0.78rem;
}

.plate {
    display: inline-block;
    padding: 1px 6px;
    border: 1px solid #1f2937;
    border-radius: 3px;
    background: #fff;
    color: #111827;
    font-family: monospace;
    font-weight: 800;
    text-transform: uppercase;
    white-space: nowrap;
}

.tabs-scroll {
    overflow-x: auto;
    padding: 7px 12px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.tabs-scroll::-webkit-scrollbar {
    display: none;
}

.tabs-wrapper {
    display: inline-flex;
    gap: 4px;
    min-width: max-content;
    padding: 3px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f1f5f9;
}

.tab-button {
    min-height: 34px;
    padding: 6px 14px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
}

.tab-button:hover {
    color: #1e293b;
    background: rgba(255, 255, 255, 0.7);
}

.tab-button.active {
    color: #fff;
    background: linear-gradient(135deg, #f97316, #ea580c);
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.3);
}

.form-label {
    margin-bottom: 3px;
    color: #64748b;
    font-size: 0.74rem;
    font-weight: 600;
}

.card-header {
    padding: 9px 14px;
    font-size: 0.88rem;
}

.invalid-feedback:empty {
    display: none !important;
}

@media (max-width: 767.98px) {
    .app-header {
        align-items: flex-start;
    }

    .status-select {
        width: 135px;
        font-size: 0.72rem;
    }

    .tabs-scroll {
        padding: 5px 8px;
    }

    .tab-button {
        min-height: 36px;
        padding: 7px 11px;
        font-size: 0.75rem;
    }

    .card-body {
        padding: 12px;
    }

    .form-control,
    .form-select {
        min-height: 34px;
    }
}
</style>