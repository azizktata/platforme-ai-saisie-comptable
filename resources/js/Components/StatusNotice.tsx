import { AlertCircle, CheckCircle2 } from 'lucide-react';

type Props = {
  message: string;
  tone?: 'success' | 'error';
};

export default function StatusNotice({ message, tone = 'success' }: Props) {
  const Icon = tone === 'success' ? CheckCircle2 : AlertCircle;

  return (
    <div className={`status-notice status-notice--${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <Icon size={16} />
      <span>{message}</span>
    </div>
  );
}
