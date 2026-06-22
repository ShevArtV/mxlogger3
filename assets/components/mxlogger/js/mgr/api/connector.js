const cfg = () => window.MxLoggerConfig || {};

async function request(action, params = {}) {
    const config = cfg();
    const form = new FormData();
    form.append('action', action);
    if (config.token) {
        form.append('HTTP_MODAUTH', config.token);
    }
    for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== undefined) {
            form.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
        }
    }
    try {
        const res = await fetch(config.connector_url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: form,
        });
        if (!res.ok) {
            return { success: false, message: `HTTP ${res.status}` };
        }
        return await res.json();
    } catch (e) {
        return { success: false, message: e.message || 'Network error' };
    }
}

const P = 'MxLogger\\Processors\\Mgr\\Log\\';

// Экспорт — не fetch, а навигация на потоковый эндпоинт export.php
// (отдаёт файл-attachment). Авторизация — по сессии менеджера.
function exportUrl(params = {}, format = 'md') {
    const config = cfg();
    const qs = new URLSearchParams();
    qs.set('format', format === 'txt' ? 'txt' : 'md');
    for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== undefined && v !== '') {
            qs.set(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
        }
    }
    return (config.assets_url || '') + 'export.php?' + qs.toString();
}

export const LogApi = {
    getList: (params) => request(P + 'GetList', params),
    get: (id) => request(P + 'Get', { id }),
    getTags: (query) => request(P + 'GetTags', { query: query || '' }),
    remove: (id) => request(P + 'Remove', { id }),
    clear: (params) => request(P + 'Clear', params),
    exportUrl,
};
