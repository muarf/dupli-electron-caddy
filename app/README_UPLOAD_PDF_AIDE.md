# Fonctionnalité d'Upload de PDFs pour les Aides

## Description
Cette fonctionnalité permet d'uploader des fichiers PDF qui peuvent ensuite être intégrés dans les aides des machines via l'éditeur Quill.js.

## Fonctionnalités

### 1. Upload de PDFs
- **Répertoire de stockage** : `/uploads/aide_pdfs/` (permanent, pas dans `/tmp/`)
- **Taille maximale** : 10MB par fichier
- **Format accepté** : PDF uniquement
- **Nommage automatique** : `nom_original_YYYY-MM-DD_HH-mm-ss.pdf`

### 2. Interface d'Upload
- Section dédiée sur la page d'administration des aides
- Sélection de fichier avec validation côté client
- Barre de progression lors de l'upload
- Messages de confirmation/erreur

### 3. Gestion des PDFs
- Liste des PDFs uploadés avec informations (nom, date, taille)
- Boutons pour insérer ou supprimer chaque PDF
- Modal pour l'insertion dans l'éditeur Quill.js

### 4. Intégration Quill.js
- Bouton "PDF" ajouté à la toolbar de l'éditeur
- Insertion de liens vers les PDFs uploadés
- Style visuel spécial pour les liens PDF (icône 📄)

## Fichiers créés/modifiés

### Nouveaux fichiers
- `upload_aide_pdf.php` - Contrôleur pour la gestion des PDFs
- `uploads/aide_pdfs/.htaccess` - Sécurité du répertoire
- `uploads/.htaccess` - Sécurité générale du répertoire uploads

### Fichiers modifiés
- `view/admin.aide.html.php` - Interface utilisateur avec upload et intégration Quill.js
- `translations/fr.json` - Nouvelles traductions françaises

## Utilisation

### Pour l'administrateur
1. Aller dans Administration > Gestion des aides par machine
2. Dans la section "Upload de PDFs d'aide" :
   - Sélectionner un fichier PDF
   - Cliquer sur "Télécharger le PDF"
3. Dans l'éditeur d'aide :
   - Cliquer sur le bouton "PDF" dans la toolbar
   - Sélectionner un PDF dans la modal qui s'ouvre
   - Le PDF sera inséré comme lien dans l'aide

### Pour l'utilisateur final
- Les liens PDF dans les aides s'ouvrent dans un nouvel onglet
- Affichage visuel avec icône 📄 pour identifier les liens PDF

## Sécurité
- Validation du type MIME côté serveur
- Limitation de taille (10MB max)
- Noms de fichiers sécurisés (caractères spéciaux remplacés)
- Répertoire protégé par `.htaccess`
- Seuls les fichiers PDF sont autorisés

## API Endpoints

### POST `upload_aide_pdf.php?action=upload`
Upload d'un nouveau PDF
- **Paramètres** : `pdf_file` (fichier), `action=upload`
- **Retour** : JSON avec `success`, `message`, `filename`, `url`

### GET `upload_aide_pdf.php?action=list`
Récupérer la liste des PDFs
- **Retour** : JSON avec `success`, `pdfs[]` (array d'objets PDF)

### POST `upload_aide_pdf.php?action=delete`
Supprimer un PDF
- **Paramètres** : `filename`, `action=delete`
- **Retour** : JSON avec `success`, `message`

## Traductions ajoutées
```json
{
    "pdf_upload": "Upload de PDFs d'aide",
    "pdf_upload_desc": "Téléchargez des PDFs qui seront disponibles pour insertion dans vos aides",
    "select_pdf": "Sélectionner un fichier PDF",
    "upload_pdf": "Télécharger le PDF",
    "upload_success": "PDF téléchargé avec succès",
    "upload_error": "Erreur lors du téléchargement",
    "uploaded_pdfs": "PDFs disponibles",
    "insert_pdf": "Insérer PDF",
    "pdf_name": "Nom du PDF",
    "upload_date": "Date d'upload",
    "pdf_size": "Taille",
    "no_pdfs": "Aucun PDF téléchargé",
    "delete_pdf": "Supprimer PDF",
    "confirm_delete_pdf": "Êtes-vous sûr de vouloir supprimer ce PDF ?",
    "pdf_inserted": "PDF inséré dans l'aide"
}
```
