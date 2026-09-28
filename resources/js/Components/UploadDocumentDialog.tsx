import { FileUp, FileText, Image, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { formatFileSize } from '../utils/format';
import StatusNotice from './StatusNotice';

type Props = {
  open: boolean;
  onClose: () => void;
  onSubmit: (file: File) => void;
  isSubmitting?: boolean;
  error?: string | null;
};

const MAX_FILE_SIZE = 20 * 1024 * 1024;
const ACCEPTED_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

export default function UploadDocumentDialog({
  open,
  onClose,
  onSubmit,
  isSubmitting = false,
  error,
}: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [localError, setLocalError] = useState<string | null>(null);
  const [dragging, setDragging] = useState(false);

  useEffect(() => {
    if (!open) {
      setFile(null);
      setLocalError(null);
      setDragging(false);
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !isSubmitting) onClose();
    };

    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, isSubmitting, onClose]);

  if (!open) return null;

  const chooseFile = (nextFile?: File) => {
    if (!nextFile) return;

    const extensionAllowed = /\.(pdf|png|jpe?g)$/i.test(nextFile.name);
    if (!ACCEPTED_TYPES.includes(nextFile.type) && !extensionAllowed) {
      setLocalError('Choisissez un fichier PDF, JPG ou PNG.');
      setFile(null);
      return;
    }
    if (nextFile.size > MAX_FILE_SIZE) {
      setLocalError('Le fichier dépasse la taille maximale de 20 Mo.');
      setFile(null);
      return;
    }

    setLocalError(null);
    setFile(nextFile);
  };

  const submit = () => {
    if (!file) {
      setLocalError('Sélectionnez un document avant de continuer.');
      return;
    }
    onSubmit(file);
  };

  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => {
      if (event.target === event.currentTarget && !isSubmitting) onClose();
    }}>
      <section className="upload-modal" role="dialog" aria-modal="true" aria-labelledby="upload-title">
        <div className="modal-heading">
          <div className="modal-icon"><FileUp size={20} /></div>
          <button className="icon-button modal-close" onClick={onClose} disabled={isSubmitting} aria-label="Fermer">
            <X size={19} />
          </button>
          <div className="modal-kicker">NOUVEAU DOCUMENT</div>
          <h2 id="upload-title">Importer une facture</h2>
          <p>Ajoutez un document pour le vérifier et préparer son écriture comptable.</p>
        </div>

        <button
          type="button"
          className={`dropzone ${dragging ? 'dropzone--active' : ''} ${file ? 'dropzone--selected' : ''}`}
          onClick={() => inputRef.current?.click()}
          onDragOver={(event) => { event.preventDefault(); setDragging(true); }}
          onDragLeave={() => setDragging(false)}
          onDrop={(event) => {
            event.preventDefault();
            setDragging(false);
            chooseFile(event.dataTransfer.files[0]);
          }}
        >
          <input
            ref={inputRef}
            type="file"
            accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
            className="sr-only"
            onChange={(event) => chooseFile(event.target.files?.[0])}
          />
          {file ? (
            <>
              <div className="dropzone__icon dropzone__icon--selected">{file.type === 'application/pdf' ? <FileText size={21} /> : <Image size={21} />}</div>
              <strong>{file.name}</strong>
              <span>{formatFileSize(file.size)} · Cliquez pour remplacer</span>
            </>
          ) : (
            <>
              <div className="dropzone__icon"><FileUp size={21} /></div>
              <strong>Déposez votre fichier ici</strong>
              <span>ou <span className="dropzone__browse">parcourez vos fichiers</span></span>
              <small>PDF, JPG ou PNG · 20 Mo maximum</small>
            </>
          )}
        </button>

        {(localError || error) && <StatusNotice message={localError || error || ''} tone="error" />}

        <div className="modal-footnote">
          <span className="privacy-dot" /> Votre document reste dans votre espace de travail.
        </div>
        <div className="modal-actions">
          <button className="button button--secondary" onClick={onClose} disabled={isSubmitting}>Annuler</button>
          <button className="button button--primary" onClick={submit} disabled={isSubmitting}>
            {isSubmitting ? <span className="button-spinner" /> : <FileUp size={16} />}
            {isSubmitting ? 'Import en cours…' : 'Importer le document'}
          </button>
        </div>
      </section>
    </div>
  );
}
