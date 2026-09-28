import { Check, Clock3 } from 'lucide-react';
import type { DocumentStatus } from '../types';

type Props = {
  status: DocumentStatus;
};

export default function StatusBadge({ status }: Props) {
  const isPosted = status === 'posted';

  return (
    <span className={`status-badge ${isPosted ? 'status-badge--posted' : 'status-badge--review'}`}>
      {isPosted ? <Check size={13} strokeWidth={2.5} /> : <Clock3 size={13} strokeWidth={2.2} />}
      {isPosted ? 'Comptabilisé' : 'À vérifier'}
    </span>
  );
}
