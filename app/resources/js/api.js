const TOKEN_KEY = 'thesis_token';
const USER_KEY = 'thesis_user';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}
export function getStoredUser() {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
}
export function saveSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}
export function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
}

export async function api(path, { method = 'GET', body, isForm = false } = {}) {
    const headers = { Accept: 'application/json' };
    if (!isForm) headers['Content-Type'] = 'application/json';
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;

    const resp = await fetch(`/api${path}`, {
        method,
        headers,
        body: isForm ? body : body ? JSON.stringify(body) : undefined,
    });

    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
        const message = data.message || `请求失败（${resp.status}）`;
        const error = new Error(message);
        error.status = resp.status;
        error.data = data;
        throw error;
    }
    return data;
}

export function fmt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
