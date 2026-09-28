import { Toaster } from 'sonner';

export default function ToastHost() {
  return <Toaster position="top-right" richColors closeButton visibleToasts={4} />;
}
