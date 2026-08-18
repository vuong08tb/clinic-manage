const TOKEN_KEY = 'clinic.auth.token';
const PERMISSIONS_KEY = 'clinic.auth.permissions';

export function getToken() {
    return window.localStorage.getItem(TOKEN_KEY);
}

export function saveAuthSession(token, permissions = []) {
    window.localStorage.setItem(TOKEN_KEY, token);
    window.localStorage.setItem(PERMISSIONS_KEY, JSON.stringify(permissions));
}

export function getStoredPermissions() {
    try {
        return JSON.parse(window.localStorage.getItem(PERMISSIONS_KEY) ?? '[]');
    } catch {
        return [];
    }
}

export function clearAuthSession() {
    window.localStorage.removeItem(TOKEN_KEY);
    window.localStorage.removeItem(PERMISSIONS_KEY);
}

export function hasAuthToken() {
    return Boolean(getToken());
}
