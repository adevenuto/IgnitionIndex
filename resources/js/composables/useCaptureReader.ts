import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CaptureController from '@/actions/App/Http/Controllers/CaptureController';
import type { CaptureLaneId, CaptureReading } from '@/lib/capture/lanes';

/*
 * Asks the server to read a photo or a sentence for one lane.
 *
 * Every failure becomes one plain sentence in `error` and a null result — a
 * refused photo, the daily allowance, the reader being down — because in each
 * case the next move is the same: type it. Nothing here throws at the caller.
 */
export function useCaptureReader(lane: CaptureLaneId, vehicleId: () => number) {
    const http = useHttp<
        { image: File | null; transcript: string | null },
        CaptureReading
    >({ image: null, transcript: null });

    // Send only the input given: the endpoint takes a photo or words, not both.
    http.transform((data) =>
        data.image !== null
            ? { image: data.image }
            : { transcript: data.transcript },
    );

    const error = ref<string | null>(null);
    const reading = computed(() => http.processing);

    async function read(input: File | string): Promise<CaptureReading | null> {
        http.image = input instanceof File ? input : null;
        http.transcript = typeof input === 'string' ? input : null;
        error.value = null;

        try {
            return await http.submit(
                CaptureController({ vehicle: vehicleId(), lane }),
                {
                    onError: (errors) => {
                        error.value =
                            Object.values(errors)[0] ??
                            'That could not be read. Type it in instead.';
                    },
                    onHttpException: (response) => {
                        error.value = messageFrom(response.data);

                        return false;
                    },
                    onNetworkError: () => {
                        error.value = 'No connection. Type it in instead.';

                        return false;
                    },
                },
            );
        } catch {
            error.value ??= 'That could not be read. Type it in instead.';

            return null;
        }
    }

    return { read, reading, error };
}

function messageFrom(data: string | Record<string, unknown>): string {
    const fallback = 'Could not read that just now. Type it in instead.';

    try {
        const body = typeof data === 'string' ? JSON.parse(data) : data;

        return typeof body?.message === 'string' ? body.message : fallback;
    } catch {
        return fallback;
    }
}
