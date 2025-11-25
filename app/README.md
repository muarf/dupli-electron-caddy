# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 🚀 Fonctionnalités

### 📊 Gestion Comptable
- Calcul des prix de revient pour les différentes machines
- Gestion des coûts d'impression (papier, encre, masters, tambours, devellopeurs)
- Suivi des volumes d'impression 
- Statistiques d'utilisation, prévision des temps de changement de consommables
- Rapports de rentabilité

### 📄 Traitement de Documents
- **Imposition de PDF** (8/16 pages A5/A6 sur un A3 rectoverso)
- **Unimposition de PDF** (séparation des pages pour un pdf déjà imposé en livret)
- **Imposition Tracts** (duplication intelligente A4/A5/A6 vers A3 avec orientation optimisée)
  - Détection automatique du format PDF (A4, A5, A6)
  - Duplication automatique (2x A4, 4x A5, 8x A6 sur A3)
  - Gestion recto/verso avec pages séparées
  - Prévisualisation intégrée et téléchargement
  - Fallback Ghostscript pour PDF incompatibles
- **PDF vers PNG** (conversion de PDF en images PNG pour traitement)
- **PNG vers PDF** (assemblage d'images PNG en documents PDF)
- **Séparateur de couleurs Riso** (séparation RGB/CMYK pour impression multi-tambours)
  - Modes RGB, CMYK, et 2 couleurs
  - Outil pipette pour isolation de couleurs
  - Effets de postérisation et halftone (trames)
  - Export individuel ou ZIP de toutes les couches
- Interface web moderne avec drag & drop

### 🔧 Technique
- Interface utilisateur intuitive ( on essaie ;))
