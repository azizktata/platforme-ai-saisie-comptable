import { createRoot } from 'react-dom/client';
import PreviewApp from './PreviewApp';
import ToastHost from './Components/ToastHost';
import '../css/app.css';

createRoot(document.getElementById('app')!).render(
  <>
    <PreviewApp />
    <ToastHost />
  </>,
);
