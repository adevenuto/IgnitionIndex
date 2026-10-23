import { useHttp, usePage } from '@inertiajs/vue3';
import type { InjectionKey } from 'vue';
import { inject, onBeforeUnmount, provide, ref } from 'vue';
import CaptureTranscriptionController from '@/actions/App/Http/Controllers/CaptureTranscriptionController';
import { responseMessage } from '@/lib/capture/responseMessage';

/*
 * One thing said into the sheet: recorded on the phone, heard on the server.
 *
 * The browser's own speech recognition was the first try, and on iOS — where
 * every browser is Safari underneath — it asked for the microphone on every
 * tap and often heard nothing at all. Recording is the part every current
 * browser does reliably, so the phone records a short clip and the server
 * turns it into words. What the words mean is still decided by the flow.
 *
 * The microphone is opened on the first tap and held until the sheet closes,
 * so a visit asks for it once, not once per step. Closing the sheet releases
 * it, which also turns off the phone's recording indicator.
 */

// Mirrors capture.audio.max_seconds: long enough for a sentence, short enough
// that a forgotten tap does not record the drive home.
const MAX_SECONDS = 8;
// Shorter than this is a mis-tap, not something said.
const MIN_MILLISECONDS = 400;
// iOS records mp4; Chrome and Firefox record webm.
const FORMATS = ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm'];

const NOT_HEARD = 'Did not catch that. Try again, or type it.';

const MESSAGES: Record<string, string> = {
    NotAllowedError: 'Microphone access is off for this site.',
    SecurityError: 'Microphone access is off for this site.',
    NotFoundError: 'No microphone found.',
    NotReadableError: 'The microphone is busy with another app.',
};

function recordingFormat(): string | undefined {
    return FORMATS.find((format) => MediaRecorder.isTypeSupported(format));
}

function extension(type: string): string {
    if (type.includes('webm')) {
        return 'webm';
    }

    return type.includes('ogg') ? 'ogg' : 'm4a';
}

export function useVoiceInput(vehicleId: () => number) {
    const page = usePage();
    const supported =
        typeof window !== 'undefined' &&
        window.isSecureContext &&
        typeof navigator.mediaDevices?.getUserMedia === 'function' &&
        typeof window.MediaRecorder === 'function' &&
        page.props.can.voiceCapture;

    const listening = ref(false);
    const transcribing = ref(false);
    const error = ref<string | null>(null);

    const http = useHttp<{ audio: File | null }, { transcript: string }>({
        audio: null,
    });

    let stream: MediaStream | null = null;
    let recorder: MediaRecorder | null = null;
    let closed = false;

    async function microphone(): Promise<MediaStream> {
        const live = stream
            ?.getAudioTracks()
            .some((track) => track.readyState === 'live');

        if (!stream || !live) {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        }

        return stream;
    }

    function record(source: MediaStream): Promise<Blob | null> {
        return new Promise((resolve) => {
            const format = recordingFormat();
            const active = new MediaRecorder(
                source,
                format ? { mimeType: format } : undefined,
            );
            const chunks: Blob[] = [];
            const startedAt = performance.now();
            const cap = window.setTimeout(() => stop(), MAX_SECONDS * 1000);

            active.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    chunks.push(event.data);
                }
            };

            active.onstop = () => {
                window.clearTimeout(cap);
                recorder = null;
                listening.value = false;

                const long = performance.now() - startedAt >= MIN_MILLISECONDS;

                resolve(
                    !closed && long && chunks.length > 0
                        ? new Blob(chunks, {
                              type: active.mimeType || format || 'audio/mp4',
                          })
                        : null,
                );
            };

            recorder = active;
            listening.value = true;
            active.start();
        });
    }

    async function transcribe(clip: Blob): Promise<string | null> {
        http.audio = new File([clip], `clip.${extension(clip.type)}`, {
            type: clip.type,
        });
        transcribing.value = true;

        try {
            const response = await http.submit(
                CaptureTranscriptionController({ vehicle: vehicleId() }),
                {
                    onError: (errors) => {
                        error.value = Object.values(errors)[0] ?? NOT_HEARD;
                    },
                    onHttpException: (response) => {
                        error.value = responseMessage(
                            response.data,
                            'Could not hear that just now. Type it in instead.',
                        );

                        return false;
                    },
                    onNetworkError: () => {
                        error.value = 'No connection. Type it in instead.';

                        return false;
                    },
                },
            );

            return response.transcript.trim() || null;
        } catch {
            error.value ??= NOT_HEARD;

            return null;
        } finally {
            transcribing.value = false;
        }
    }

    /** Records until stop() or the cap, then resolves with what was said. */
    async function listen(): Promise<string | null> {
        if (!supported || recorder !== null || transcribing.value) {
            return null;
        }

        error.value = null;

        let source: MediaStream;

        try {
            source = await microphone();
        } catch (reason) {
            const name = reason instanceof DOMException ? reason.name : '';

            error.value = MESSAGES[name] ?? 'Could not use the microphone.';

            return null;
        }

        const clip = await record(source);

        if (clip === null) {
            if (!closed) {
                error.value = NOT_HEARD;
            }

            return null;
        }

        return transcribe(clip);
    }

    function stop(): void {
        if (recorder?.state === 'recording') {
            recorder.stop();
        }
    }

    onBeforeUnmount(() => {
        closed = true;
        stop();
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    });

    return { supported, listening, transcribing, error, listen, stop };
}

export type VoiceInput = ReturnType<typeof useVoiceInput>;

const voiceInputKey: InjectionKey<VoiceInput> = Symbol('voiceInput');

/**
 * Opens voice input for a whole capture flow, so its steps — each mounted
 * fresh — share one microphone rather than asking for it again.
 */
export function provideVoiceInput(vehicleId: () => number): VoiceInput {
    const voice = useVoiceInput(vehicleId);

    provide(voiceInputKey, voice);

    return voice;
}

export function injectVoiceInput(): VoiceInput {
    const voice = inject(voiceInputKey);

    if (voice === undefined) {
        throw new Error('Voice input is provided by the capture flow.');
    }

    return voice;
}
