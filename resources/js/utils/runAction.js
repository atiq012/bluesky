// Spinner on first; always off in finally; no modal open until async done
export async function runAction(fn, { setLoading } = {}) {
    setLoading?.(true);
    try {
        return await fn();
    } finally {
        setLoading?.(false);
    }
}
