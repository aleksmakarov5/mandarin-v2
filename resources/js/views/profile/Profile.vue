<template>
    <div class="profile-page">
        <!-- Заголовок страницы -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                    <i class="bi bi-person-gear text-primary me-2"></i>
                    <span>Мой профиль</span>
                </h4>
                <small class="text-muted">Управление учетной записью и персональными данными</small>
            </div>
        </div>

        <!-- Вкладки настроек профиля -->
        <ul class="nav nav-pills gap-1 p-1 bg-white border rounded shadow-sm mb-3">
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'general' }" @click="activeTab = 'general'">
                    <i class="bi bi-person-vcard me-1"></i> Основные данные
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'security' }" @click="activeTab = 'security'">
                    <i class="bi bi-shield-lock me-1"></i> Безопасность и пароль
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" :class="{ active: activeTab === 'appearance' }"
                    @click="activeTab = 'appearance'">
                    <i class="bi bi-palette me-1"></i> Оформление
                </button>
            </li>
        </ul>

        <!-- Уведомления об успехе/ошибке -->
        <div v-if="successMessage"
            class="alert alert-success d-flex align-items-center py-2 px-3 shadow-sm border-0 mb-3">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span>{{ successMessage }}</span>
        </div>

        <div v-if="errorMessage" class="alert alert-danger d-flex align-items-center py-2 px-3 shadow-sm border-0 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <span>{{ errorMessage }}</span>
        </div>

        <!-- ВКЛАДКА 1: ОСНОВНЫЕ ДАННЫЕ -->
        <div v-if="activeTab === 'general'" class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Персональная информация</h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <form @submit.prevent="handleSaveGeneral">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold text-muted">Фамилия <span
                                    class="text-danger">*</span></label>
                            <input v-model="form.last_name" type="text" class="form-control"
                                :class="{ 'is-invalid': serverErrors.last_name }" required />
                            <div v-if="serverErrors.last_name" class="invalid-feedback">{{ serverErrors.last_name[0] }}
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold text-muted">Имя <span
                                    class="text-danger">*</span></label>
                            <input v-model="form.name" type="text" class="form-control"
                                :class="{ 'is-invalid': serverErrors.name }" required />
                            <div v-if="serverErrors.name" class="invalid-feedback">{{ serverErrors.name[0] }}</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold text-muted">Отчество (необязательно)</label>
                            <input v-model="form.patronymic" type="text" class="form-control" placeholder="Если есть" />
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-muted">Email / Логин <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input v-model="form.email" @blur="onEmailBlur" @input="emailError = ''" type="email"
                                    class="form-control" :class="{ 'is-invalid': emailError || serverErrors.email }"
                                    placeholder="name@mandarin.local" required />
                            </div>
                            <div v-if="emailError" class="text-danger small mt-1">
                                {{ emailError }}
                            </div>
                            <div v-else-if="serverErrors.email" class="text-danger small mt-1">
                                {{ serverErrors.email[0] }}
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-muted">Номер телефона</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                <input v-model="form.phone" v-mask-phone type="tel" class="form-control"
                                    placeholder="+7 (___) ___-__-__" />
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-muted">Роль в системе</label>
                            <input type="text" class="form-control bg-light" :value="authStore.userRoleName" disabled />
                            <small class="text-muted">Роль назначается администратором системы</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4" :disabled="authStore.loading">
                            <span v-if="authStore.loading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-check2 me-1"></i>
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ВКЛАДКА 2: БЕЗОПАСНОСТЬ И ПАРОЛЬ -->
        <div v-if="activeTab === 'security'" class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Смена пароля учетной записи</h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <form @submit.prevent="handleSavePassword">
                    <div class="row g-3" style="max-width: 520px;">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Текущий пароль <span
                                    class="text-danger">*</span></label>
                            <input v-model="passwordForm.current_password" type="password" class="form-control"
                                :class="{ 'is-invalid': serverErrors.current_password }" required />
                            <div v-if="serverErrors.current_password" class="invalid-feedback">{{
                                serverErrors.current_password[0] }}</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Новый пароль <span
                                    class="text-danger">*</span></label>
                            <input v-model="passwordForm.new_password" type="password" class="form-control"
                                :class="{ 'is-invalid': serverErrors.new_password }" required />
                            <div v-if="serverErrors.new_password" class="invalid-feedback">{{
                                serverErrors.new_password[0] }}</div>
                            <small class="text-muted d-block mt-1">
                                Минимум 8 символов, заглавные и строчные буквы, цифра и специальный символ.
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted">Подтверждение нового пароля <span
                                    class="text-danger">*</span></label>
                            <input v-model="passwordForm.new_password_confirmation" type="password" class="form-control"
                                required />
                        </div>
                    </div>

                    <div class="d-flex justify-content-start mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary px-4" :disabled="authStore.loading">
                            <span v-if="authStore.loading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="bi bi-key me-1"></i>
                            Обновить пароль
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ВКЛАДКА 3: ОФОРМЛЕНИЕ (ЗАГОТОВКА) -->
        <div v-if="activeTab === 'appearance'" class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Персонализация интерфейса</h6>
            </div>
            <div class="card-body p-4 text-center py-5">
                <div class="p-3 d-inline-flex bg-primary bg-opacity-10 text-primary rounded-circle mb-3">
                    <i class="bi bi-palette-fill fs-2"></i>
                </div>
                <h5 class="fw-bold">Настройки оформления</h5>
                <p class="text-muted mb-0" style="max-width: 480px; margin: 0 auto;">
                    Выбор цветовых акцентов, темы (светлая/темная) и компактного режима таблицы будут доступны в
                    следующем обновлении.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { validateEmail } from '../../utils/emailValidator';

const emailError = ref('');

const onEmailBlur = () => {
    if (form.email) {
        form.email = form.email.trim();
        const res = validateEmail(form.email);
        emailError.value = res.isValid ? '' : res.error;
    } else {
        emailError.value = 'Email обязателен для заполнения';
    }
};

const authStore = useAuthStore();
const activeTab = ref('general');
const successMessage = ref('');
const errorMessage = ref('');
const serverErrors = ref({});

const form = reactive({
    last_name: authStore.user?.last_name || '',
    name: authStore.user?.name || '',
    patronymic: authStore.user?.patronymic || '',
    email: authStore.user?.email || '',
    phone: authStore.user?.phone || '',
});

const passwordForm = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

// Синхронизация формы при обновлении пользователя в store
watch(
    () => authStore.user,
    (newUser) => {
        if (newUser) {
            form.last_name = newUser.last_name || '';
            form.name = newUser.name || '';
            form.patronymic = newUser.patronymic || '';
            form.email = newUser.email || '';
            form.phone = newUser.phone || '';
        }
    },
    { immediate: true, deep: true }
);

const clearAlerts = () => {
    successMessage.value = '';
    errorMessage.value = '';
    serverErrors.value = {};
};

const handleSaveGeneral = async () => {
    clearAlerts();

    onEmailBlur();
    if (emailError.value) {
        return;
    }

    const res = await authStore.updateProfile({
        last_name: form.last_name,
        name: form.name,
        patronymic: form.patronymic,
        email: form.email.trim(),
        phone: form.phone,
    });


    if (res.success) {
        successMessage.value = 'Личные данные успешно обновлены!';
    } else {
        errorMessage.value = res.message;
        serverErrors.value = res.errors || {};
    }
};

const handleSavePassword = async () => {
    clearAlerts();
    const res = await authStore.updateProfile({
        last_name: form.last_name,
        name: form.name,
        patronymic: form.patronymic,
        email: form.email,
        phone: form.phone,
        current_password: passwordForm.current_password,
        new_password: passwordForm.new_password,
        new_password_confirmation: passwordForm.new_password_confirmation,
    });

    if (res.success) {
        successMessage.value = 'Пароль успешно изменен!';
        passwordForm.current_password = '';
        passwordForm.new_password = '';
        passwordForm.new_password_confirmation = '';
    } else {
        errorMessage.value = res.message;
        serverErrors.value = res.errors || {};
    }
};
</script>