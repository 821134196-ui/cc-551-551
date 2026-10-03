import axios from 'axios';

// 轻量 HTTP 客户端：Sanctum token 放在 localStorage，随同源 /api 请求发送。
const client = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

client.interceptors.response.use(
    (res) => res,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            if (!location.pathname.endsWith('/login') && !location.pathname.endsWith('/')) {
                location.href = '/';
            }
        }
        return Promise.reject(error);
    },
);

export default client;
