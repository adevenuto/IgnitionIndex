/*
 * The one sentence a failed capture request should show: the server's own
 * message when it sent one (a 429's allowance, a 503's "type it in"), or the
 * caller's fallback when the body is anything else.
 */
export function responseMessage(
    data: string | Record<string, unknown>,
    fallback: string,
): string {
    try {
        const body = typeof data === 'string' ? JSON.parse(data) : data;

        return typeof body?.message === 'string' ? body.message : fallback;
    } catch {
        return fallback;
    }
}
