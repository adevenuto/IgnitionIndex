import { onBeforeUnmount, ref } from 'vue';

/*
 * One utterance from the device's own speech recognition.
 *
 * Recognition runs in the browser, so a spoken number costs nothing and never
 * leaves the phone unless it turns out to need the server. Support is uneven —
 * Chrome and Safari carry it, Firefox does not, and every browser requires a
 * secure context — so `supported` decides whether the button exists at all.
 * The keyboard's own dictation key is always there as the fallback.
 *
 * The DOM typings no longer ship SpeechRecognition, hence the small local
 * declaration of just the parts used here.
 */
type Recognition = {
    lang: string;
    interimResults: boolean;
    maxAlternatives: number;
    continuous: boolean;
    start(): void;
    stop(): void;
    abort(): void;
    onresult:
        | ((event: {
              results: ArrayLike<ArrayLike<{ transcript: string }>>;
          }) => void)
        | null;
    onerror: ((event: { error: string }) => void) | null;
    onend: (() => void) | null;
};

type RecognitionConstructor = new () => Recognition;

function recognitionConstructor(): RecognitionConstructor | null {
    if (typeof window === 'undefined' || !window.isSecureContext) {
        return null;
    }

    const candidate = window as unknown as {
        SpeechRecognition?: RecognitionConstructor;
        webkitSpeechRecognition?: RecognitionConstructor;
    };

    return (
        candidate.SpeechRecognition ?? candidate.webkitSpeechRecognition ?? null
    );
}

const MESSAGES: Record<string, string> = {
    'not-allowed': 'Microphone access is off for this site.',
    'service-not-allowed': 'Microphone access is off for this site.',
    'no-speech': 'Did not catch that. Try again, or type it.',
    'audio-capture': 'No microphone found.',
    network: 'Listening needs a connection.',
};

export function useSpeechInput() {
    const Constructor = recognitionConstructor();
    const supported = Constructor !== null;
    const listening = ref(false);
    const error = ref<string | null>(null);
    let active: Recognition | null = null;

    /** Resolves with what was heard, or null if nothing usable was. */
    function listen(): Promise<string | null> {
        if (Constructor === null || active !== null) {
            return Promise.resolve(null);
        }

        return new Promise((resolve) => {
            const recognition = new Constructor();
            let heard: string | null = null;

            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            recognition.continuous = false;

            recognition.onresult = (event) => {
                heard = event.results[0]?.[0]?.transcript.trim() || null;
            };

            recognition.onerror = (event) => {
                // An abort is ours (closing the sheet), not something to report.
                if (event.error !== 'aborted') {
                    error.value =
                        MESSAGES[event.error] ?? 'Could not listen just now.';
                }
            };

            recognition.onend = () => {
                listening.value = false;
                active = null;
                resolve(heard);
            };

            error.value = null;
            active = recognition;
            listening.value = true;
            recognition.start();
        });
    }

    /** Stop listening and use what has been heard so far. */
    function stop(): void {
        active?.stop();
    }

    onBeforeUnmount(() => active?.abort());

    return { supported, listening, error, listen, stop };
}
