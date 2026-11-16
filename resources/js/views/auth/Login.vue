<template>
    <div class="login-wrapper d-flex align-items-center justify-content-center min-vh-100 bg-light p-3">
        <div class="card border-0 shadow-lg login-card bg-white rounded-4 overflow-hidden">
            <!-- ТЕМНАЯ ШАПКА КАРТОЧКИ ВХОДА -->
            <div class="login-card-header text-center py-4 px-3">
                <router-link to="/requests" class="text-decoration-none d-inline-block" title="Перейти к дашборду">
                    <MandarinLogo :size="46" :show-text="true" text-class="text-white" :clickable="true" />
                </router-link>
                <div class="text-secondary-subtle small mt-2" style="font-size: 0.76rem; letter-spacing: 0.3px;">
                    Единая информационная система учета дел
                </div>
            </div>

            <!-- СВЕТЛОЕ ТЕЛО С ФОРМОЙ АВТОРИЗАЦИИ -->
            <div class="p-4 p-md-5">
                <h5 class="fw-bold text-dark text-center mb-4" style="letter-spacing: -0.01em;">
                    Вход в систему
                </h5>

                <form @submit.prevent="submitLogin">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Логин или Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-person"></i>
                            </span>
                            <input v-model="form.login" type="text" class="form-control border-start-0 ps-0"
                                placeholder="Введите логин" required autofocus />
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Пароль</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'"
                                class="form-control border-start-0 border-end-0 ps-0" placeholder="Введите пароль"
                                required />
                            <button class="btn btn-outline-light border border-start-0 text-muted" type="button"
                                @click="showPassword = !showPassword">
                                <i :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="errorMessage" class="alert alert-danger py-2 small mb-3">
                        {{ errorMessage }}
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold text-uppercase mt-2 shadow-sm"
                        style="letter-spacing: 0.5px;" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                        Войти в систему
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import MandarinLogo from '../../components/MandarinLogo.vue';

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
    login: '',
    password: '',
});

const showPassword = ref(false);
const loading = ref(false);
const errorMessage = ref('');

const submitLogin = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        // Передаём объект, поддерживающий и login, и email
        await authStore.login({
            login: form.login.trim(),
            email: form.login.trim(),
            password: form.password,
        });
        router.push('/requests');
    } catch (err) {
        console.error('Ошибка авторизации:', err);

        const res = err?.response;
        if (res?.data?.errors) {
            // Собираем ошибки валидации Laravel (422)
            const firstKey = Object.keys(res.data.errors)[0];
            errorMessage.value = res.data.errors[firstKey][0];
        } else if (res?.data?.message) {
            errorMessage.value = res.data.message;
        } else if (res?.status === 401 || res?.status === 403) {
            errorMessage.value = 'Неверный логин или пароль';
        } else if (res?.status === 419) {
            errorMessage.value = 'Сессия истекла (CSRF). Обновите страницу (F5).';
        } else if (res?.status >= 500) {
            errorMessage.value = 'Ошибка сервера (500). Проверьте laravel.log.';
        } else if (err?.message) {
            errorMessage.value = `Сетевая ошибка: ${err.message}`;
        } else {
            errorMessage.value = 'Не удалось войти в систему. Проверьте соединение.';
        }
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.login-card {
    width: 100%;
    max-width: 420px;
    border-radius: 14px !important;
}

/* ТЕМНАЯ ШАПКА В ЕДИНОМ СТИЛЕ САЙДБАРА */
.login-card-header {
    background: linear-gradient(180deg, #1e222d 0%, #161922 100%);
    border-bottom: 2px solid #ea580c;
    /* Тонкий фирменный акцент */
}

.text-secondary-subtle {
    color: #94a3b8 !important;
}
</style>