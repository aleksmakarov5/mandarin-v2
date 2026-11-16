<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
        <div class="card shadow border" style="max-width: 380px; width: 100%;">
            <div
                class="card-header bg-dark text-white py-3 text-center d-flex justify-content-center align-items-center">
                <!-- Наш логотип -->
                <MandarinLogo :size="32" :show-text="true" />
            </div>
            <div class="card-body p-4">
                <div v-if="authStore.error" class="alert alert-danger py-2 small" role="alert">
                    {{ authStore.error }}
                </div>

                <form @submit.prevent="handleLogin">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email / Логин</label>
                        <input v-model="form.email" type="email" class="form-control" placeholder="admin@mandarin.local"
                            required autofocus />
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Пароль</label>
                        <input v-model="form.password" type="password" class="form-control" placeholder="Пароль"
                            required />
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-2" :disabled="authStore.loading">
                        <span v-if="authStore.loading" class="spinner-border spinner-border-sm me-1"
                            role="status"></span>
                        Войти в систему
                    </button>
                </form>
            </div>
            <div class="card-footer bg-white text-center py-2 text-muted small">
                Mandarin Portal 2.0 &copy; 2026
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import MandarinLogo from '../../components/MandarinLogo.vue';

const router = useRouter();
const authStore = useAuthStore();

const form = reactive({
    email: '',
    password: '',
});

const handleLogin = async () => {
    const success = await authStore.login(form);
    if (success) {
        router.push({ name: 'dashboard' });
    }
};
</script>