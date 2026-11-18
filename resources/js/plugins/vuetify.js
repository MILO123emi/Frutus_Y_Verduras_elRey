import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { es } from 'vuetify/locale';
import { aliases, mdi } from 'vuetify/iconsets/mdi';

// Tema "pulpería ceibeña": verde hoja, turquesa de pared pintada, mango y azul tinta.
// Alto contraste y recursos locales.
export default createVuetify({
    locale: { locale: 'es', fallback: 'es', messages: { es } },
    icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
    theme: {
        defaultTheme: 'elrey',
        themes: {
            elrey: {
                dark: false,
                colors: {
                    primary: '#2D7A3E',
                    'on-primary': '#FFFFFF',
                    'on-secondary': '#FFFFFF',
                    'on-accent': '#1D3557',
                    'on-tinta': '#FFFFFF',
                    secondary: '#0E7C86',
                    accent: '#F5B700',
                    tinta: '#1D3557',
                    background: '#EEF3F0',
                    surface: '#FFFFFF',
                    'surface-variant': '#E3ECE7',
                    'on-surface-variant': '#33413A',
                    'on-background': '#1B2320',
                    'on-surface': '#1B2320',
                    success: '#2D7A3E',
                    warning: '#B25E00',
                    error: '#B42318',
                    info: '#0E7C86',
                },
                variables: { 'border-color': '#1B2320', 'border-opacity': 0.16, 'medium-emphasis-opacity': 0.72 },
            },
        },
    },
    defaults: {
        VBtn: { rounded: 'md', size: 'large' },
        VCard: { rounded: false, elevation: 0, border: true },
        VTextField: { variant: 'outlined', color: 'primary' },
        VTextarea: { variant: 'outlined', color: 'primary', rows: 2, autoGrow: true },
        VSelect: { variant: 'outlined', color: 'primary' },
        VAutocomplete: { variant: 'outlined', color: 'primary' },
        VCombobox: { variant: 'outlined', color: 'primary' },
        VCheckbox: { color: 'primary', hideDetails: 'auto' },
        VSwitch: { color: 'primary', inset: true, hideDetails: 'auto' },
        VChip: { variant: 'tonal', label: true },
        VAlert: { variant: 'tonal', rounded: 'lg' },
        VDialog: { maxWidth: 640 },
        VSnackbar: { timeout: 6000, rounded: 'lg', location: 'bottom' },
    },
});
