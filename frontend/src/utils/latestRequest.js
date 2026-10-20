export function createLatestRequest() {
    let active = null;
    return {
        start() {
            active?.abort();
            active = new AbortController();
            return active;
        },
        isCurrent(request) {
            return active === request && !request.signal.aborted;
        },
        abort() {
            active?.abort();
            active = null;
        },
    };
}
