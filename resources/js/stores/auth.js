import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('user_data') || 'null'),
        token: localStorage.getItem('auth_token') || null,
        loading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        userRole: (state) => state.user?.role || null,
        userName: (state) => state.user?.name || 'Пользователь',
        isAdmin: (state) => ['sys_admin', 'company_admin'].includes(state.user?.role),
    },

    actions: {
        async login(credentials) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.post('/api/auth/login', credentials);
                this.token = response.data.token;
                this.user = response.data.user;

                localStorage.setItem('auth_token', this.token);
                localStorage.setItem('user_data', JSON.stringify(this.user));

                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
                return true;
            } catch (err) {
                this.error = err.response?.data?.message || 'Ошибка авторизации. Проверьте логин и пароль.';
                return false;
            } finally {
                this.loading = false;
            }
        },

        async fetchMe() {
            if (!this.token) return;
            try {
                const response = await axios.get('/api/auth/me');
                this.user = response.data;
                localStorage.setItem('user_data', JSON.stringify(this.user));
            } catch (err) {
                this.logout();
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await axios.post('/api/auth/logout');
                }
            } catch (e) {
                // Игнорируем ошибку при выходе
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_data');
                delete axios.defaults.headers.common['Authorization'];
            }
        },
    },
});