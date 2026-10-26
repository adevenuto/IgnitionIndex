import { createInertiaApp } from '@inertiajs/vue3';
import { createApp } from 'vue';
import FontPicker from '@/components/FontPicker.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { fontPickerEnabled } from '@/lib/fonts';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();

// The type-face trial panel, for whoever opened the site with ?fonts.
if (fontPickerEnabled()) {
    const host = document.createElement('div');
    document.body.append(host);
    createApp(FontPicker).mount(host);
}
