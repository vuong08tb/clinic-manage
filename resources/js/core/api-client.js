import { ApiError } from './api-error';
import { getToken } from './auth-storage';

const API_BASE = '/api';

function createHeaders(headers, body, token) {
    const result = new Headers(headers);

    result.set('Accept', 'application/json');

    if (body !== undefined && !(body instanceof FormData)) {
        result.set('Content-Type', 'application/json');
    }

    if (token) {
        result.set('Authorization', `Bearer ${token}`);
    }

    return result;
}

async function parsePayload(response) {
    const contentType = response.headers.get('content-type') ?? '';

    if (response.status === 204 || !contentType.includes('application/json')) {
        return null;
    }

    return response.json();
}

export async function apiRequest(path, options = {}) {
    const {
        auth = true,
        headers = {},
        body,
        ...fetchOptions
    } = options;
    const token = auth ? getToken() : null;

    let response;

    try {
        response = await fetch(`${API_BASE}${path}`, {
            ...fetchOptions,
            body,
            headers: createHeaders(headers, body, token),
        });
    } catch (error) {
        throw new ApiError('Không thể kết nối đến máy chủ. Vui lòng thử lại.', {
            payload: error,
        });
    }

    let payload = null;

    try {
        payload = await parsePayload(response);
    } catch (error) {
        throw new ApiError('Máy chủ trả về dữ liệu không hợp lệ.', {
            status: response.status,
            payload: error,
        });
    }

    if (!response.ok || payload?.success === false) {
        if (response.status === 401 && token) {
            window.dispatchEvent(new CustomEvent('clinic:unauthorized'));
        }

        throw new ApiError(payload?.message ?? 'Yêu cầu không thành công.', {
            status: response.status,
            errors: payload?.errors ?? {},
            payload,
        });
    }

    return payload;
}
