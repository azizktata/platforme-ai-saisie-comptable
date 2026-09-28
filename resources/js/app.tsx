import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import '../css/app.css';

type PageModule = { default: ComponentType<Record<string, unknown>> };
const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx');

createInertiaApp({
  resolve: async (name) => {
    const path = `./Pages/${name}.tsx`;
    const loadPage = pages[path];

    if (!loadPage) {
      throw new Error(`Inertia page not found: ${name}`);
    }

    return loadPage();
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: {
    color: '#187f70',
    showSpinner: false,
  },
});
