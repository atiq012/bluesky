import { defineStore } from 'pinia';

const STORAGE_PREFIX = 'datatable-columns-';

function storageKey(tableId) {
    return `${STORAGE_PREFIX}${tableId}`;
}

function loadFromStorage(tableId) {
    try {
        const raw = localStorage.getItem(storageKey(tableId));
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

function saveToStorage(tableId, fields) {
    localStorage.setItem(storageKey(tableId), JSON.stringify(fields));
}

export const useDatatableColumnsStore = defineStore('datatableColumns', {
    state: () => ({
        columnsByTableId: {},
    }),

    getters: {
        getVisibleFields: (state) => (tableId, fallbackFields) => {
            const stored = state.columnsByTableId[tableId] ?? loadFromStorage(tableId);
            if (stored && stored.length) {
                const filtered = stored.filter((f) => fallbackFields.includes(f));
                return filtered.length ? filtered : [...fallbackFields];
            }
            return [...fallbackFields];
        },
    },

    actions: {
        setVisibleFields(tableId, fields) {
            this.columnsByTableId[tableId] = fields;
            saveToStorage(tableId, fields);
        },

        initTable(tableId, fallbackFields) {
            if (!fallbackFields || !fallbackFields.length) return [];
            const stored = loadFromStorage(tableId);
            if (stored && stored.length) {
                const filtered = stored.filter((f) => fallbackFields.includes(f));
                const visible = filtered.length ? filtered : [...fallbackFields];
                this.columnsByTableId[tableId] = visible;
                return visible;
            }
            this.columnsByTableId[tableId] = [...fallbackFields];
            return this.columnsByTableId[tableId];
        },

        resetToAll(tableId, allFields) {
            this.setVisibleFields(tableId, allFields);
        },

        clearTable(tableId) {
            delete this.columnsByTableId[tableId];
            localStorage.removeItem(storageKey(tableId));
        },
    },
});
