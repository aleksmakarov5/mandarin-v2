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

        userRole: (state) => {
            return state.user?.role || state.user?.group?.code || null;
        },
        isAdmin: (state) => !!state.user?.is_system_admin,

        userRoleName: (state) => {
            return (
                state.user?.role_name ||
                state.user?.group?.name ||
                state.user?.role ||
                'Сотрудник'
            );
        },

        userName: (state) => {
            const u = state.user;
            if (!u) return 'Пользователь';

            if (u.full_name && u.full_name.trim()) {
                return u.full_name.trim();
            }

            if (u.last_name || u.name) {
                return [u.last_name, u.name, u.patronymic].filter(Boolean).join(' ').trim();
            }

            return u.name || u.email || 'Пользователь';
        },

        // Форматирование: "Фамилия И. О."
        userShortName: (state) => {
            const u = state.user;
            if (!u) return 'Пользователь';

            // 1. Готовое поле с бэкенда (short_name)
            if (u.short_name && String(u.short_name).trim()) {
                return String(u.short_name).trim();
            }

            // 2. Если есть раздельные поля last_name / name / patronymic
            const lastName = u.last_name ? String(u.last_name).trim() : '';
            const firstName = u.name ? String(u.name).trim() : '';
            const patronymic = u.patronymic ? String(u.patronymic).trim() : '';

            if (lastName || firstName) {
                const firstInitial = firstName ? `${firstName.charAt(0)}.` : '';
                const patrInitial = patronymic ? ` ${patronymic.charAt(0)}.` : '';
                const formatted = `${lastName} ${firstInitial}${patrInitial}`.trim();

                if (formatted) {
                    return formatted;
                }
            }

            // 3. Если полное ФИО пришло единой строкой в поле name
            if (firstName) {
                const parts = firstName.split(/\s+/).filter(Boolean);
                if (parts.length >= 3) {
                    return `${parts[0]} ${parts[1].charAt(0)}. ${parts[2].charAt(0)}.`;
                }
                if (parts.length === 2) {
                    return `${parts[0]} ${parts[1].charAt(0)}.`;
                }
                return parts[0];
            }

            // 4. Запасной вариант (email до знака @)
            if (u.email) {
                return u.email.split('@')[0];
            }

            return 'Пользователь';
        },

        isAdmin: (state) => {
            const role = state.user?.role || state.user?.group?.code;
            return ['sys_admin', 'company_admin'].includes(role);
        },
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
                this.error =
                    err.response?.data?.message ||
                    err.response?.data?.errors?.email?.[0] ||
                    'Ошибка авторизации. Проверьте логин и пароль.';
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
                await this.logout();
            }
        },

        async logout() {
            try {
                if (this.token) {
                    await axios.post('/api/auth/logout');
                }
            } catch (e) {
                // Игнорируем сетевые ошибки при выходе
            } finally {
                this.token = null;
                this.user = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_data');
                delete axios.defaults.headers.common['Authorization'];
            }
        },
        async updateProfile(payload) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.put('/api/auth/profile', payload);
                this.user = { ...this.user, ...response.data.user };
                localStorage.setItem('user_data', JSON.stringify(this.user));
                return { success: true, message: response.data.message };
            } catch (err) {
                const message = err.response?.data?.message || 'Ошибка при обновлении профиля';
                const errors = err.response?.data?.errors || {};
                return { success: false, message, errors };
            } finally {
                this.loading = false;
            }
        },
    },
});