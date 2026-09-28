import { createRoot } from 'react-dom/client';
import PreviewApp from './PreviewApp';
import '../css/app.css';

createRoot(document.getElementById('app')!).render(<PreviewApp />);
