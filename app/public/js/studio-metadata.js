document.addEventListener('DOMContentLoaded', () => {
  const $id = id => document.getElementById(id);
  const btnApplyMetadata = $id('btnApplyMetadata');
  const btnClearMetadata = $id('btnClearMetadata');

  // Au changement d'outil global (géré dans studio.html.php ou studio-modification.js), 
  // on affiche le panelMetadata si l'outil 'metadata' est cliqué.
  // La logique de bascule est dans studio.html.php (tool-btn click event).
  
  // Intercepter le clic sur le bouton 'metadata' pour charger les métadonnées actuelles
  const metadataBtns = document.querySelectorAll('.tool-btn[data-tool="metadata"]');
  metadataBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      loadCurrentMetadata();
    });
  });

  async function loadCurrentMetadata() {
    if (!window.state) {
      if ($id('metaRawInfo')) $id('metaRawInfo').textContent = "Erreur: window.state est indéfini.";
      return;
    }
    if (!window.state.file) {
      if ($id('metaRawInfo')) $id('metaRawInfo').textContent = "Aucun fichier sélectionné (window.state.file est vide). Si vous êtes dans Montage Libre, exportez le PDF d'abord.";
      return;
    }

    const file = window.state.file;
    if (!file) {
      if ($id('metaRawInfo')) $id('metaRawInfo').textContent = "Aucun fichier sélectionné (window.state.file est vide). Si vous êtes dans Montage Libre, exportez le PDF d'abord.";
      return;
    }

    try {
      if (window.showSpinner) window.showSpinner('Lecture des métadonnées...');
      
      const formData = new FormData();
      formData.append('action', 'read_metadata');
      formData.append('file', file);

      const res = await fetch('?studio_process', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      
      if (window.hideSpinner) window.hideSpinner();

      if (data.success && data.metadata) {
        $id('metaTitle').value = data.metadata.Title || '';
        $id('metaAuthor').value = data.metadata.Author || '';
        $id('metaSubject').value = data.metadata.Subject || '';
        $id('metaKeywords').value = data.metadata.Keywords || '';
        $id('metaCreator').value = data.metadata.Creator || '';
        $id('metaProducer').value = data.metadata.Producer || '';
        $id('metaCreationDate').value = data.metadata.CreateDate || '';
        $id('metaModDate').value = data.metadata.ModifyDate || '';
        
        let rawText = '';
        for (const [key, value] of Object.entries(data.metadata)) {
            // Ignore technical directories or large binary objects if any
            if (typeof value !== 'object') {
                // pad key to 32 chars for alignment
                const paddedKey = (key + '                                ').slice(0, 32);
                rawText += `${paddedKey}: ${value}\n`;
            }
        }
        if ($id('metaRawInfo')) {
            $id('metaRawInfo').textContent = rawText;
        }
      } else {
        console.error("Erreur lecture metadata. Données reçues:", data);
        const errStr = data.error ? data.error : JSON.stringify(data);
        if ($id('metaRawInfo')) $id('metaRawInfo').textContent = "Erreur: " + errStr;
        if (window.showToast) window.showToast("Erreur lors de la lecture des métadonnées", "error");
      }
    } catch (e) {
      if (window.hideSpinner) window.hideSpinner();
      console.error(e);
      if (window.showToast) window.showToast("Erreur réseau (Lecture métadonnées)", "error");
    }
  }

  if (btnApplyMetadata) {
    btnApplyMetadata.addEventListener('click', async () => {
      if (!window.state || !window.state.file) {
        alert("Veuillez charger un fichier PDF d'abord. Si vous êtes dans Montage Libre, exportez d'abord le PDF.");
        return;
      }
      
      const file = window.state.file;
      if (!file) {
        alert("Veuillez charger un fichier d'abord.");
        return;
      }

      try {
        if (window.showSpinner) window.showSpinner('Application des métadonnées...');
        
        const formData = new FormData();
        formData.append('action', 'update_metadata');
        formData.append('file', file);
        
        formData.append('Title', $id('metaTitle').value);
        formData.append('Author', $id('metaAuthor').value);
        formData.append('Subject', $id('metaSubject').value);
        formData.append('Keywords', $id('metaKeywords').value);
        formData.append('Creator', $id('metaCreator').value);
        formData.append('Producer', $id('metaProducer').value);
        formData.append('CreateDate', $id('metaCreationDate').value);
        formData.append('ModifyDate', $id('metaModDate').value);

        const res = await fetch('?studio_process', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        
        if (window.hideSpinner) window.hideSpinner();

        if (data.success) {
          if (window.showToast) window.showToast("Métadonnées mises à jour avec succès !", "success");
          
          if (data.download_url && window.showResultToast) {
              const nameParts = file.name.split('.');
              const ext = nameParts.pop();
              const baseName = nameParts.join('.');
              window.showResultToast(data.download_url, `${baseName}_metadata.${ext}`);
          } else {
              loadCurrentMetadata();
          }
        } else {
          console.error("Erreur MAJ metadata:", data.error);
          if (window.showToast) window.showToast("Erreur lors de la mise à jour : " + (data.error || "Inconnue"), "error");
        }
      } catch (e) {
        if (window.hideSpinner) window.hideSpinner();
        console.error(e);
        if (window.showToast) window.showToast("Erreur réseau (MAJ métadonnées)", "error");
      }
    });
  }

  if (btnClearMetadata) {
    btnClearMetadata.addEventListener('click', async () => {
      if (!window.state || !window.state.file) {
        alert("Veuillez charger un fichier PDF d'abord. Si vous êtes dans Montage Libre, exportez d'abord le PDF.");
        return;
      }
      
      const file = window.state.file;
      if (!file) {
        alert("Veuillez charger un fichier d'abord.");
        return;
      }

      if (!confirm("Voulez-vous vraiment effacer TOUTES les métadonnées de ce fichier de manière définitive ?")) {
        return;
      }

      try {
        if (window.showSpinner) window.showSpinner('Effacement des métadonnées...');
        
        const formData = new FormData();
        formData.append('action', 'update_metadata');
        formData.append('file', file);
        formData.append('clear_all', '1');

        const res = await fetch('?studio_process', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        
        if (window.hideSpinner) window.hideSpinner();

        if (data.success) {
          if (window.showToast) window.showToast("Toutes les métadonnées ont été effacées !", "success");
          
          if (data.download_url && window.showResultToast) {
              const nameParts = file.name.split('.');
              const ext = nameParts.pop();
              const baseName = nameParts.join('.');
              window.showResultToast(data.download_url, `${baseName}_cleared.${ext}`);
          } else {
              loadCurrentMetadata();
          }
        } else {
          console.error("Erreur MAJ metadata:", data.error);
          if (window.showToast) window.showToast("Erreur lors de l'effacement : " + (data.error || "Inconnue"), "error");
        }
      } catch (e) {
        if (window.hideSpinner) window.hideSpinner();
        console.error(e);
        if (window.showToast) window.showToast("Erreur réseau (Effacement métadonnées)", "error");
      }
    });
  }
});
