# Note interne — Ce dossier est géré automatiquement par le CI

> Ce dossier (`bin/win-x64/python/`) est **peuplé automatiquement** lors du build
> Windows par le script [`scripts/setup-win-studio-deps.js`](../../../scripts/setup-win-studio-deps.js).
> **Aucune action manuelle requise.**

Le script télécharge et installe :

| Contenu | Source |
|---|---|
| `python.exe` + runtime | Python 3.11 embeddable (python.org) |
| `pdf2docx`, `python-docx`, `ocrmypdf` | pip install |

Les autres dépendances (`tesseract.exe`, `pdftotext.exe`, `exiftool.exe`)
sont installées directement dans `bin/win-x64/` par le même script (via Chocolatey + GitHub downloads).

## Pour les développeurs Windows locaux

Si vous souhaitez tester localement sur Windows sans passer par le CI :

```powershell
node scripts/setup-win-studio-deps.js
```

Ce script détecte automatiquement qu'il tourne sur Windows et installe tout dans `bin/win-x64/`.
