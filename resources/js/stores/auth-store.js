import { apiRequest } from '../core/api-client';
import {
    clearAuthSession,
    getStoredPermissions,
    getToken,
    saveAuthSession,
} from '../core/auth-storage';

export function createAuthStore() {
    return {
        user: null,
        permissions: getStoredPermissions(),
        ready: false,
        _bootstrapPromise: null,

        init() {
            window.addEventListener('clinic:unauthorized', () => {
                this.clear();

                if (window.location.pathname !== '/login') {
                    window.location.replace('/login');
                }
            });
        },

        get roleName() {
            return this.user?.role?.display_name ?? this.user?.role?.name ?? 'Nhân viên';
        },

        get initials() {
            const words = this.user?.name?.trim().split(/\s+/).filter(Boolean) ?? [];

            return words.slice(-2).map((word) => word[0]).join('').toUpperCase() || 'NV';
        },

        can(permission) {
            return this.permissions.includes(permission);
        },

        canAny(permissions = []) {
            return permissions.some((permission) => this.can(permission));
        },

        async bootstrap({ redirectIfGuest = true } = {}) {
            if (this.ready) {
                return this.user;
            }

            if (this._bootstrapPromise) {
                return this._bootstrapPromise;
            }

            this._bootstrapPromise = this.restore(redirectIfGuest).finally(() => {
                this._bootstrapPromise = null;
            });

            return this._bootstrapPromise;
        },

        async restore(redirectIfGuest) {
            if (!getToken()) {
                this.ready = true;

                if (redirectIfGuest && window.location.pathname !== '/login') {
                    window.location.replace('/login');
                }

                return null;
            }

            try {
                const response = await apiRequest('/me');
                this.setUser(response.data);

                return this.user;
            } catch {
                this.clear();

                if (redirectIfGuest && window.location.pathname !== '/login') {
                    window.location.replace('/login');
                }

                return null;
            } finally {
                this.ready = true;
            }
        },

        async login(credentials) {
            const response = await apiRequest('/login', {
                method: 'POST',
                auth: false,
                body: JSON.stringify(credentials),
            });

            saveAuthSession(response.data.token, response.data.user.permissions ?? []);
            this.setUser(response.data.user);
            this.ready = true;

            return this.user;
        },

        async logout() {
            try {
                if (getToken()) {
                    await apiRequest('/logout', { method: 'POST' });
                }
            } finally {
                this.clear();
                window.location.replace('/login');
            }
        },

        setUser(user) {
            this.user = user;
            this.permissions = user?.permissions ?? [];

            if (getToken()) {
                saveAuthSession(getToken(), this.permissions);
            }
        },

        clear() {
            clearAuthSession();
            this.user = null;
            this.permissions = [];
            this.ready = true;
        },
    };
}
