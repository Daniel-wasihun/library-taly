import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

// Helper to reliably extract auth token from either standalone storage or Pinia persisted state
export const getStoredToken = (): string | null => {
    if (typeof window === 'undefined') return null;

    const directToken = localStorage.getItem('auth_token');
    if (directToken) return directToken;

    try {
        const persisted = localStorage.getItem('univ-library-auth');
        if (persisted) {
            const parsed = JSON.parse(persisted);
            if (parsed && typeof parsed.token === 'string') {
                return parsed.token;
            }
        }
    } catch {}

    return null;
};

// Helper to thoroughly purge all auth tokens from client storage
export const purgeStoredAuth = () => {
    if (typeof window === 'undefined') return;
    localStorage.removeItem('auth_token');
    localStorage.removeItem('univ-library-auth');
};

// Inject Bearer token from localStorage or Pinia store on each request
api.interceptors.request.use((config) => {
    const token = getStoredToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Global response error handler
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            purgeStoredAuth();

            // Prevent infinite reload loops if already on login page
            const currentPath = window.location.pathname;
            if (currentPath !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;
