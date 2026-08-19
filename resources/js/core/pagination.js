export function emptyPaginationMeta() {
    return {
        current_page: 1,
        from: null,
        last_page: 1,
        per_page: 15,
        to: null,
        total: 0,
    };
}

export function calculateVisiblePages(meta) {
    const current = Number(meta.current_page);
    const last = Number(meta.last_page);
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);

    return Array.from(
        { length: end - start + 1 },
        (_, index) => start + index,
    );
}
