import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export default function RequestToastEvents() {
  useEffect(() => {
    const removeExceptionListener = router.on('exception', () => {
      toast.error('Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.');
    });
    const removeInvalidResponseListener = router.on('invalid', () => {
      toast.error('Le serveur a renvoyé une réponse inattendue. Réessayez plus tard.');
    });

    return () => {
      removeExceptionListener();
      removeInvalidResponseListener();
    };
  }, []);

  return null;
}
