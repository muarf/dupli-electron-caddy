# Logique Métier - Duplicator

> **Portée :** Algorithmes, calculs, traitements de documents  
> **Fichiers analysés :** `app/controler/functions/`, `app/models/`

---

## Table des matières

1. [Calcul des prix et rentabilité](#1-calcul-des-prix-et-rentabilité)
2. [Gestion des consommables (suivi)](#2-gestion-des-consommables-suivi)
3. [Imposition PDF (N-up, brochure, livre)](#3-imposition-pdf-n-up-brochure-livre)
4. [Traitement images & PDF](#4-traitement-images--pdf)
5. [Désimposition (unimpose)](#5-désimposition-unimpose)
6. [Gestion spooler Windows](#6-gestion-spooler-windows)
7. [Bibliothèque de documents](#7-bibliothèque-de-documents)
8. [Séparation couleur Riso](#8-séparation-couleur-riso)

---

## 1. Calcul des prix et rentabilité

### 1.1 Structure des données prix

Les prix sont stockés en base dans la table `prix` avec le format :

| Champ | Type | Description |
|-------|------|-------------|
| `machine_type` | TEXT | `"dupli"` ou `"photocop"` |
| `machine_id` | INTEGER | ID de la machine (0 = générique) |
| `type` | TEXT | `"master"`, `"encre"`, `"tambour_noir"`, `"tambour_bleu"`, … |
| `pack` | REAL | Prix du pack/consommable complet |
| `unite` | REAL | Prix à l'unité (pour calculs) |

**Exemple de prix retournés par `get_price()` :**
```php
$prix = [
  'dupli_1' => [
    'master' => ['unite' => 0.05, 'pack' => 25.00],
    'tambour_noir' => ['unite' => 0.002, 'pack' => 120.00],
    'tambour_bleu' => ['unite' => 0.0025, 'pack' => 140.00],
  ],
  'photocop_1' => [
    'noire' => ['unite' => 0.005, 'pack' => 140.00],
    'couleur' => ['unite' => 0.04, 'pack' => 280.00],
  ],
  'papier' => ['A3' => 0.02, 'A4' => 0.01]
];
```

### 1.2 Algorithme `get_price_devis($machine, $nb_f, $nb_p, $nb_m, $couleur, $avec_papier)`

**Entrées :**
- `$machine` : `"pA4"`, `"pA3"`, `"comcolor"` ou nom duplicopieur (ex: `"Digital Box"`)
- `$nb_f` : nombre de feuilles
- `$nb_p` : nombre de passages (pages)
- `$nb_m` : nombre de masters
- `$couleur` : `"oui"` ou `"non"` (uniquement pour photocopieurs)
- `$avec_papier` : `"oui"` ou `"non"`

**Étapes :**

1. **Détermination type machine**
   - `pA4` / `pA3` → `photocop`, taille = lettre suffixe (A4 ou A3)
   - `comcolor` → `photocop`, taille = `A4`
   - Autres → `dupli` (duplicopieur)

2. **Calcul coût papier** (si `$avec_papier == 'oui'`)
   ```php
   $price['devis']['f'] = $nb_f × $prix['papier'][$taille];
   ```

3. **Calcul coût master (dupli uniquement)**
   ```php
   $price['devis']['m'] = $nb_m × $prix[$taille]['master']['unite'];
   ```

4. **Calcul coût encre**
   - **Duplicopieur** : 
     ```php
     $price['devis']['p'] = $nb_p × $prix[$taille]['encre']['unite'];
     ```
   - **Photocopieur** : 
     ```php
     $type = ($couleur == 'oui') ? 'couleur' : 'noire';
     $unite = $prix['photocop'][$type]['unite'];
     // Ajustement taille : A3 = 2× A4
     $unite = ($taille == 'A3') ? $unite : $unite / 2;
     $price['devis']['p'] = $nb_p × $unite;
     ```

5. **Total**
   ```php
   $price['devis']['t'] = f + p (+ m si dupli)
   ```

**Retour :** Tableau structuré `$price['devis']['machine|f|p|m|t']`

---

## 2. Gestion des consommables (suivi)

### 2.1 Fonction `get_cons($machine)`

**Objectif :** Calculer l'état actuel des consommables (master, encre) pour une machine donnée, en fonction de l'historique des changements.

**Données source :** Table `cons` (historique des remplacements)

| Champ | Description |
|-------|-------------|
| `date` | timestamp Unix du changement |
| `machine` | nom machine (ex: `"dx4545"`, `"photocop"`) |
| `type` | `"master"` ou `"encre"` |
| `nb_p` | nombre de passages au changement |
| `nb_m` | nombre de masters au changement |
| `tambour` | (optionnel) type de tambour pour encre Riso |

**Algorithme :**

1. **Récupérer tous les enregistrements** `cons` pour cette machine (ordre chronologique)
2. **Séparer master / encre** en deux sous-tableaux
3. **Pour chaque type**, calculer :
   - `nb_actuel` = valeur actuelle (dernier `nb_m` ou `nb_p` enregistré)
   - `temps_depuis` = `time() - date_dernier_changement`
   - `temps_moy` = moyenne des intervalles entre changements
   - `moyenne_totale['nb_m'|'nb_p']` = moyenne consommation par intervalle
   - `temps_jusqua` = `temps_moy - temps_depuis` (→ prévision épuisement)

4. **Calcul prix comparatif**
   ```php
   // Pour encre (dupli)
   $prix_pack = $prix[$machine_key][$tambour_utilise]['pack'] ?? $prix[$machine_key]['tambour_noir']['pack'];
   $prix_unite = $prix[$machine_key][$tambour_utilise]['unite'] ?? ...;
   $prix_calcule = $prix_pack / $moyenne_totale['nb_p'];
   $color = ($prix_calcule < $prix_unite) ? 'green' : 'red';
   ```

5. **Statut couleur** (classes CSS)
   - `danger` : épuisé > 30 jours
   - `warning` / `alert` : 0 à -30 jours
   - `info` : 0 à 30 jours
   - `success` : > 30 jours restants

### 2.2 Fonction `insert_cons()` et `insert_cons_photocop()`

Enregistre un changement de consommable :

```php
INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (time(), $machine, $type, $nb_p, $nb_m)
```

Pour photocopieur : `nb_m` est à `0` (seul `nb_p` compté).

---

## 3. Imposition PDF (N-up, brochure, livre)

### 3.1 Classes principales

| Classe | Rôle |
|--------|------|
| `Imposition` | Algorithme N-up générique (2, 4, 8 poses) |
| `ImpositionProcessor` | Helper pour A5/A6 avec traits de coupe |
| `CropMarks` | Dessin traits de coupe et numérotation |
| `resizeToA5()` / `resizeToA6()` | Redimensionnement pages |

### 3.2 Format N-up supportés

| Format | Pages/feuille | Orientation | Poses par côté (recto/verso) |
|--------|---------------|-------------|------------------------------|
| **A5** | 4 (2×2) | Portrait A3 | 2 poses recto + 2 poses verso (tête-bêche) |
| **A6** | 16 (4×4) | Paysage A3 | 8 poses recto + 8 poses verso |
| **N-up personnalisé** | N (1–32) | Auto | Selon `duplex` |

### 3.3 Algorithme Imposition (cut & stack)

**Entrées :**
- `sourceFile` : PDF source
- `n_up` : nombre de poses par face (2, 4, 8)
- `duplex` : `true` (recto-verso) ou `false` (simplex)
- `orientation` : `L` (paysage) ou `P` (portrait)
- `gutter_x/y` : espace entre poses (mm)
- `gutter_strategy` : `"reduce"` (réduire échelle) ou `"crop"` (rogner)
- `crop_marks` : `true/false`
- `tete_beche` : `true/false` (rotation 180° verso)

**Processus :**

1. **Compter pages source** (`$pageCount = $pdf->setSourceFile()`)
2. **Calcul profondeur pile** :
   ```php
   $pagesPerSheet = $n_up × ($duplex ? 2 : 1);
   $stackDepth = ceil($pageCount / $pagesPerSheet);
   ```
3. **Boucle sur chaque feuille physique** (`$i = 1 to $stackDepth`)
   
   **Recto :**
   - Ajouter page A3 (ou A4 selon format)
   - Pour chaque position `$pos = 0 to $n_up-1` :
     - Calculer `$pageNo` selon formule cut & stack
     - `placePage($pageNo, $col, $row, ...)`
   
   **Verso** (si duplex) :
   - Ajouter page miroir
   - Pour chaque position `$pos` :
     - `$pageNo = ($pos × $stackDepth × 2) + ($i × 2)`
     - Inversion colonne : `$mirrorCol = ($cols - 1) - $origCol`
     - `placePage($pageNo, $mirrorCol, $origRow, ...)`

4. **Placement page** (`placePage()`)
   - Importer template PDF (`importPage($pageNo)`)
   - Calcul échelle (`$scaleFactor`) selon `target_width/height` ou `scale %`
   - Calcul dimensions finales `$rawW × $rawH`
   - Appliquer stratégie gouttière :
     - **Reduce** : si contenu trop large, réduire échelle globale
     - **Crop** : garder échelle, réduire espacement physique
   - Centrer grille sur feuille
   - Positionner chaque page (`$x, $y`)
   - Appliquer rotation tête-bêche si configuré
   - `useTemplate($tplIdx, $x, $y, $finalW, $finalH)`
   - Ajouter traits de coupe (si activé)
   - Numérotation dans gouttière (si activé)

5. **Export PDF final** (`$pdf->Output($outputFile, 'F')`)

### 3.4 Traits de coupe (CropMarks)

**Types :**
- `normal` : 4 coins (traits de 10 mm vers l'extérieur)
- `central` : traits centraux à 21 cm (pour A3→A4)
- `both` : combinaison

**Dessin :**
```php
CropMarks::drawCropMarks($pdf, $x, $y, $width, $height, $bleed_size)
// Coin haut-gauche : Line($x, $y, $x-$len, $y)  // horizontal gauche
//                   Line($x, $y, $x, $y-$len)  // vertical haut
// … 3 autres coins
```

**Position numérotation dans zone de coupe :**
```php
computeTrimZonePosition($x, $y, $page_width, $page_height, $page_row, $total_rows, $sheet_width, $sheet_height, $offset)
// Retourne ($label_x, $label_y) centrés dans gouttière
```

---

## 4. Traitement images & PDF

### 4.1 PDF → PNG (`pdf_to_png.php`)

**Fonction `convert_pdf_to_png($pdf_file, $output_dir, $dpi, $base_filename)`**

- **Outil :** Ghostscript (binaire embarqué)
- **Commande :**
  ```bash
  gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 \
     -dTextAlphaBits=4 -dGraphicsAlphaBits=4 \
     -sOutputFile=output_dir/page_%03d.png input.pdf
  ```
- **DPI :** 150 par défaut (configurable 72–300)
- **Sortie :** un PNG par page, nommés `page_001.png`, `page_002.png`, …
- **Retour :** tableau chemins fichiers + dimensions mm par page (via FPDI)

**Particularité :** `convert_pdf_to_images_preserve_size()` lit d'abord les dimensions exactes de chaque page PDF via FPDI (`getTemplateSize()`), garantissant que les PNG générés ont la résolution correcte pour reconstitution fidèle.

### 4.2 PNG → PDF (`png_to_pdf.php`)

**Fonction `convert_png_to_pdf($image_files, $output_file, $format, $orientation)`**

- **Bibliothèque :** TCPDF
- **Processus :**
  1. Créer PDF (format A3/A4, orientation P/L)
  2. Pour chaque image :
     - `AddPage()` avec dimensions exactes
     - Calcul ratio pour ajuster image → page
     - Centrer image
     - `Image($file, $x, $y, $newW, $newH)`
  3. `Output($outputFile, 'F')`

### 4.3 Traitement image avancé (`image_processor.php`)

**Capacités :**

| Fonction | Description |
|----------|-------------|
| `adjust_contrast()` | Ajustement contraste (-100 à +100) |
| `adjust_brightness()` | Ajustement luminosité (-100 à +100) |
| `adjust_gamma()` | Correction gamma (0.1 à 3.0) |
| `adjust_saturation()` | Ajustement saturation (-100 à +100) |
| `convert_to_bitmap_threshold()` | Seuil fixe (0–255) |
| `convert_to_bitmap_dithering()` | Dithering Floyd-Steinberg (via Imagick ou PHP) |

**Processing pipeline (`process_image()`):**
```
Load image (GD)
   ↓
Contrast (imgfilter IMG_FILTER_CONTRAST)
   ↓
Brightness (IMG_FILTER_BRIGHTNESS)
   ↓
Gamma (imagegammacorrect)
   ↓
Saturation (boucle pixel par pixel HSL→RGB)
   ↓
Bitmap ? (threshold ou Floyd-Steinberg)
   ↓
Save (imagejpeg/imagepng/imagegif)
```

**Limites performance :**
- Images > 2000px : dithering PHP → fallback seuil simple
- Imagick recommandé pour dithering performant

**Asynchrone :**  
Le traitement utilise `fastcgi_finish_request()` pour renvoyer réponse immédiate (`progress_key`) puis continuer en arrière-plan. Frontend poll `?progress_key=…` pour récupérer JSON progression.

---

## 5. Désimposition (unimpose)

### 5.1 Modes

| Mode | Fichier | Description |
|------|---------|-------------|
| `booklet` | `unimpose_booklet()` | Désimpose un livret : pages 1,2,3,4 → 1,4 / 2,3 |
| `split_double_pages` | `unimpose_split_double_pages()` | Sépare pages doubles (couv + doubles) en simples |

### 5.2 Algorithme `unimpose_booklet()`

1. **Nettoyage Ghostscript** (obligatoire)
   ```bash
   gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite \
      -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer \
      -sOutputFile=cleaned.pdf input.pdf
   ```
   → Corp le PDF (supprime objets inutiles, linearise)

2. **Désimposition** via `UnimposeBooklet` classe (dans `unimpose_logic.php`)
   - Réarrangement pages selon ordre d'impression recto-verso
   - Gestion nombre de pages impair (page blanche)
   - Sortie : PDF avec pages individuelles dans ordre de lecture

### 5.3 Cas d'usage

- **Livret imprimé** (pages 1–8 sur 2 feuilles A4 recto-verso) → PDF 8 pages simples
- **Couv + doubles pages** → extraction couv + découpage spreads

---

## 6. Gestion spooler Windows

### 6.1 Surveillance jobs (`SpoolManager`)

**Fichier :** `app/models/SpoolManager.php`

**Objectif :** Localiser et supprimer les fichiers spool Windows (`.SPL` / `.SHD`) associés à un Job ID.

### 6.2 Localisation fichier SPL

**Stratégie (par ordre) :**

1. **Nom standard** : `C:\Windows\System32\spool\PRINTERS\XXXXX.SPL`
   - Job ID padded à 5 chiffres : `00105.SPL`
   
2. **Recherche dans SHD** (Shadow File) :
   - Scan `*.SHD` dans dossier spool
   - Lire en-tête (16 octets) → extraire Job ID (offset 12 ou 8)
   - Retourner `XXXXX.SPL` correspondant

3. **Fallback FPxxxxx** (File Pooling drivers) :
   - Si driver utilise noms aléatoires `FP0001.SPL`
   - Prendre le plus récent si non trouvé via SHD (riskant → pas de suppression par défaut)

### 6.3 Suppression sécurisée

```php
SpoolManager::deleteSpoolFiles($jobId)
```

- `secure_delete($file)` : overwrite avant suppression (si configuré)
- Supprime `.SPL` + `.SHD` associé
- Log action : `[SPOOL MANAGER] Suppression fichiers Job 12345 : 00105.SPL, 00105.SHD`

### 6.4 Réanalyse job (`reanalyzeJob()`)

**Depuis** `win32-printer.js` (module natif) :

- Re-génère thumbnail du PDF spool (via rendering)
- Calcule `fillRate` (pourcentage couverture noir)
- Retourne : `{ success, isGrayscale, fillRate, thumbnailUrl }`

Utile pour afficher aperçu job dans interface admin.

---

## 7. Bibliothèque de documents

### 7.1 Gestionnaire (`BibliothequeManager.php`)

**Fonctionnalités :**
- Upload PDF/PNG vers bibliothèque permanente
- Stockage : `app/public/uploads/library/` (ou équivalent portable)
- Métadonnées en base : `bibliotheque` table
  - `id`, `filename`, `filepath`, `file_type`, `uploaded_at`, `category`, `tags`

**Workflow :**
```
Formulaire upload → BibliothequeManager::saveFile($_FILES)
   ↓
Move vers uploads/library/ + hash filename
   ↓
Insert DB (id, filename, path, type, date)
   ↓
Fichier disponible dans dropdown "Bibliothèque" des outils
```

**Réutilisation :**
Dans chaque outil (pdf_to_png, image_processor, imposition), formulaire permet :
- Soit upload classique
- Soit sélection fichier bibliothèque → `lib_file_id` POST

Le code traite alors le fichier existant (pas d'upload) → économie espace/bande.

### 7.2 Prétraitement

- **Prévisualisation** : Génération thumb (via `pdf_to_png` ou `image_processor`)
- **Tags** : Catégorisation (factures, contrats, plans, …)
- **Recherche** : Par nom, date, type

---

## 8. Séparation couleur Riso

### 8.1 Concept

La séparation Riso permet d'imprimer en multicolore en utilisant des **tambours de couleur** (noir, bleu, rouge, vert, jaune, violet).

**Principe :**
1. Partir d'une image couleur (PNG/PDF)
2. Porter chaque canal couleur dans un **channel** séparé (CMJN + noir)
3. Générer un PDF séparé par tambour
4. Imprimer chaque PDF avec tambour correspondant (superposition manuelle)

### 8.2 Interface (`riso_separator.php`)

- Affichage upload / sélection bibliothèque
- Choix des tambours disponibles (depuis table `duplicopieurs` → champ JSON `tambours`)
- Prévisualisation canvas JavaScript
- Traitement **entièrement client-side** (JS WebWorker) → pas de charge serveur

**Sorties :**
- Téléchargement séparé par tambour (PDF ou PNG)
- ZIP contenant toutes les couches

### 8.3 Algorithmes JS (client)

- **Separation** : Itération pixels → détection couleur la plus proche dans palette Riso
- **Halftone** : Dithering Floyd-Steinberg ou seuil pour chaque canal
- **Export** : PNG 300 DPI prêt pour impression Riso

---

## 9. Prix et économie

### 9.1 Calcul `prix_du($machine)`

Récupère le montant total des **impressions non payées** pour une machine :

**Duplicopieur** (`dupli` table) :
```sql
SELECT SUM(prix) FROM dupli
WHERE duplicopieur_id = ? AND paye = 'non'
```

**Photocopieur** (`photocop` table) :
```sql
SELECT SUM(prix) FROM photocop
WHERE marque = ? AND paye = 'non'
```

**A3/A4 Générique** : somme globales (toutes machines confondues)

### 9.2 Coût master vs encre

- **Master** : coût fixe par feuille (usure cliché)
- **Encre** : coût variable par passage (consommation réelle)
- Le suivi `get_cons()` permet de détecter :
  - Master usé → `temps_jusqua < -30` → alerte remplacement
  - Encre presque vide → `temps_jusqua ≈ 0` → commande

---

## 10. Algorithmes spéciaux

### 10.1 Calcul moyenne mobile

`get_cons()` utilise moyenne glissante des intervalles :
```php
$temps_moy[$i] = $date[$i] - $date[$i-1];
$moyenne = array_sum($temps_moy) / count($temps_moy);
```

Élimine bruits des changements exceptionnels (petits tirages).

### 10.2 Dithering Floyd-Steinberg (image_processor)

Algorithme diffusion erreur :

```
Pour chaque pixel (x, y) :
  old = valeur gris pixel
  new = (old < 128) ? 0 : 255
  error = old - new
  pixel(x,y) = new
  // Répartir erreur sur 4 voisins
  pixel(x+1, y)   += error × 7/16
  pixel(x-1, y+1) += error × 3/16
  pixel(x,   y+1) += error × 5/16
  pixel(x+1, y+1) += error × 1/16
```

Implémentation PHP native (boucle double) → lent.  
Préfère Imagick si dispo (`quantizeImage(2, GRAY, 0, true)`).

### 10.3 Conversion dimensions PDF (image_processor)

FPDI lit **CropBox** (préférence) puis **MediaBox** fallback
→ `getTemplateSize()` retourne largeur/hauteur en mm
→ Ghostscript convertit avec `-dUseCropBox=true` pour correspondance exacte

---

## 11. Schémas données

### 11.1 Tables principales

```sql
-- Prix
prix (id, machine_type, machine_id, type, pack, unite)

-- Consommations
dupli (id, duplicopieur_id, date, nb_m, nb_f, prix, paye, mot, contact)
photocop (id, marque, date, nb_f, prix, paye, mot, contact)
cons (id, date, machine, type, nb_p, nb_m, tambour)

-- Machines
duplicopieurs (id, marque, modele, actif, tambours JSON)
photocopieurs (id, marque, modele, actif)

-- Papier
papier (id, prix)

-- Divers
news (id, time, titre, news)
email (email)
site_settings (setting_name, setting_value, updated_at)
bibliotheque (id, filename, filepath, file_type, uploaded_at, category, tags)
```

### 11.2 Relations

- `dupli.duplicopieur_id` → `duplicopieurs.id`
- `prix.machine_type + machine_id` détermine machine
- `cons.machine` = clé logique (nom machine, insensible à la casse)

---

*Fin de logique métier — Suite : 04-FLUX_ET_COMMUNICATIONS.md*
