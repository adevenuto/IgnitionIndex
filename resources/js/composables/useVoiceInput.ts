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
 *
 * While recording it also reports how loud the microphone is (`level`, 0–1)
 * and how long is left (`remaining`), so the sheet can show that it is
 * actually hearing something rather than merely waiting.
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
    const level = ref(0);
    const remaining = ref(MAX_SECONDS);

    const http = useHttp<{ audio: File | null }, { transcript: string }>({
        audio: null,
    });

    let stream: MediaStream | null = null;
    let recorder: MediaRecorder | null = null;
    let closed = false;
    let audio: AudioContext | null = null;
    let tap: MediaStreamAudioSourceNode | null = null;
    let frame = 0;

    /**
     * Opened inside the tap itself: iOS only lets audio start from a gesture,
     * and by the time the microphone has been granted, the gesture is gone.
     */
    function wakeAudio(): void {
        try {
            audio ??= new AudioContext();
            void audio.resume();
        } catch {
            audio = null;
        }
    }

    /**
     * Follows the microphone's loudness until recording stops. The level is
     * the RMS of the waveform, lifted so ordinary speech fills most of the
     * scale; a meter that barely moves for a normal voice reads as deaf.
     */
    function meter(source: MediaStream, startedAt: number): void {
        if (audio === null) {
            return;
        }

        try {
            const analyser = audio.createAnalyser();
            analyser.fftSize = 512;
            tap = audio.createMediaStreamSource(source);
            tap.connect(analyser);

            const samples = new Uint8Array(analyser.fftSize);

            const tick = () => {
                if (recorder === null) {
                    level.value = 0;

                    return;
                }

                analyser.getByteTimeDomainData(samples);

                let sum = 0;

                for (const sample of samples) {
                    const centred = (sample - 128) / 128;
                    sum += centred * centred;
                }

                level.value = Math.min(1, Math.sqrt(sum / samples.length) * 4);
                remaining.value = Math.max(
                    0,
                    Math.ceil(
                        MAX_SECONDS - (performance.now() - startedAt) / 1000,
                    ),
                );
                frame = requestAnimationFrame(tick);
            };

            frame = requestAnimationFrame(tick);
        } catch {
            // No Web Audio: recording still works, the meter just stays flat.
        }
    }

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
                cancelAnimationFrame(frame);
                tap?.disconnect();
                tap = null;
                level.value = 0;
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
            remaining.value = MAX_SECONDS;
            active.start();
            meter(source, startedAt);
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
        wakeAudio();

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
        cancelAnimationFrame(frame);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        void audio?.close();
        audio = null;
    });

    return {
        supported,
        listening,
        transcribing,
        error,
        level,
        remaining,
        listen,
        stop,
    };
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
