/* Made with AI */

/**
 * Coordinates cancellable read requests for collection views and filters.
 * Lets callers check which request is current before updating results,
 * errors, or loading indicators, preventing older responses from taking over.
 */
/**
 * Owns one cancellable read request at a time.
 * Call isCurrent() before committing results, errors, or loading state; abort
 * alone cannot prevent a response that has already arrived from being used.
 * @returns {{start: function(): AbortController, isCurrent: function(AbortController): boolean, abort: function(): void}} Request lifecycle helpers.
 */
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
