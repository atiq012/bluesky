// Agent upload paths are served from app origin (/uploads/agents route), not /api.
export function resolveAgentImageUrl(path, apiOrigin = window.location.origin) {
    if (!path) {
        return '';
    }

    const cleanPath = String(path).trim();
    if (cleanPath.startsWith('http://') || cleanPath.startsWith('https://')) {
        return cleanPath;
    }

    const normalized = cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`;
    if (normalized.startsWith('/uploads/')) {
        return `${window.location.origin}${normalized}`;
    }

    return `${apiOrigin}${normalized}`;
}
