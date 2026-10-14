import axios, { isAxiosError } from 'axios';
import Cookies from 'js-cookie';

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api';

const api = axios.create({
    baseURL: API_URL,
    headers: { Accept: 'application/json' },
});

// Automatically attach the token to every request
api.interceptors.request.use((config) => {
    const token = Cookies.get('ories_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// An expired or revoked token sends the user back to the login page
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (isAxiosError(error) && error.response?.status === 401 && typeof window !== 'undefined' && window.location.pathname !== '/') {
            clearSession();
            window.location.replace(`${window.location.origin}/`);
        }
        return Promise.reject(error);
    },
);

export function saveSession(token: string, role: string) {
    Cookies.set('ories_token', token, { expires: 1, sameSite: 'lax' });
    Cookies.set('ories_role', role, { expires: 1, sameSite: 'lax' });
}

export function clearSession() {
    Cookies.remove('ories_token');
    Cookies.remove('ories_role');
}

/**
 * Extracts a readable message from an API error, including the first validation error.
 */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    if (isAxiosError(error)) {
        const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
        const firstValidation = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
        return firstValidation ?? data?.message ?? fallback;
    }
    return fallback;
}

/**
 * Downloads a protected file (MOV or CSV) with the bearer token and opens or saves it.
 */
export async function openProtectedFile(url: string, filename?: string, params?: Record<string, unknown>) {
    const response = await api.get(url, { params, responseType: 'blob' });
    const blobUrl = URL.createObjectURL(response.data as Blob);
    if (filename) {
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        link.click();
    } else {
        window.open(blobUrl, '_blank', 'noopener');
    }
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000);
}

export default api;
