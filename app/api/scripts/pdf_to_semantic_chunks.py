"""
pdf_to_semantic_chunks.py — Phase 2 du pipeline RAG Markdown

Usage:
    python pdf_to_semantic_chunks.py <pdf_path> <output_json_path>

Sortie JSON:
{
    "markdown_full": "...",
    "chunks": [
        {
            "content": "Texte brut nettoyé",
            "section_title": "Chapitre 1 > Section 2",
            "heading_level": 2,
            "word_count": 523
        }
    ],
    "stats": {
        "total_chunks": 42,
        "total_words": 21000,
        "processing_time_s": 35.2
    }
}

En cas d'erreur : exit code 1 + message sur stderr.
"""

import os
# Brider la parallélisation CPU de PyTorch/Docling à un seul thread
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["VECLIB_MAXIMUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import sys
import json
import re
import time
import signal

# Timeout global pour le script (le parent PHP a 900s, on se met 850s pour être sûr)
TIMEOUT_SECONDS = 850

def _timeout_handler(signum, frame):
    raise TimeoutError(f"Script interrompu après {TIMEOUT_SECONDS}s (dépassement du timeout interne)")

# Configurer le signal d'alarme (Unix seulement)
try:
    signal.signal(signal.SIGALRM, _timeout_handler)
    signal.alarm(TIMEOUT_SECONDS)
except (AttributeError, ValueError):
    pass  # Windows ou environnement sans signal.alarm

# Limiter la mémoire vive (8 Go max) pour éviter l'OOM killer
try:
    import resource
    GB = 1024 * 1024 * 1024
    resource.setrlimit(resource.RLIMIT_AS, (8 * GB, 8 * GB))
except (ImportError, ValueError, resource.error):
    pass  # Windows ou limite non applicable

# ─── Paramètres de chunking ───────────────────────────────────────────────────
MIN_WORDS   = 80    # Fusionner avec le chunk suivant si trop court
MAX_WORDS   = 600   # Couper si trop long (sous la limite OVH 5000 chars ≈ 700 mots)
TARGET_WORDS = 400  # Taille cible pour les sous-chunks
# ──────────────────────────────────────────────────────────────────────────────

def clean_markdown(text: str) -> str:
    """Retire la syntaxe Markdown du texte pour FTS5 et vectorisation."""
    # Supprimer les titres (#, ##, etc.) — garder le texte
    text = re.sub(r'^#{1,6}\s+', '', text, flags=re.MULTILINE)
    # Supprimer le gras/italique (**word** *word*)
    text = re.sub(r'\*{1,3}([^*]+)\*{1,3}', r'\1', text)
    text = re.sub(r'_{1,3}([^_]+)_{1,3}', r'\1', text)
    # Supprimer les liens [texte](url)
    text = re.sub(r'\[([^\]]+)\]\([^\)]+\)', r'\1', text)
    # Supprimer les blocs de code ```...```
    text = re.sub(r'```[^`]*```', ' ', text, flags=re.DOTALL)
    text = re.sub(r'`([^`]+)`', r'\1', text)
    # Supprimer les lignes de tableau |---|---|
    text = re.sub(r'^\|[-:| ]+\|$', '', text, flags=re.MULTILINE)
    # Supprimer les `>` de citations
    text = re.sub(r'^>\s*', '', text, flags=re.MULTILINE)
    # Normaliser les espaces
    text = re.sub(r'\n{3,}', '\n\n', text)
    text = re.sub(r'[ \t]+', ' ', text)
    return text.strip()


def split_into_sections(markdown: str) -> list:
    """
    Découpe le Markdown par titres (#, ##, ###…).
    Retourne une liste de dicts: {heading, level, content}
    """
    # Pattern pour détecter les lignes de titres
    heading_pattern = re.compile(r'^(#{1,6})\s+(.+)$', re.MULTILINE)

    sections = []
    matches = list(heading_pattern.finditer(markdown))

    # S'il n'y a pas de titres, on retourne le document entier comme une section
    if not matches:
        cleaned = clean_markdown(markdown).strip()
        if cleaned:
            sections.append({
                'heading': '',
                'level': 0,
                'content': cleaned,
            })
        return sections

    # Texte avant le premier titre (intro)
    if matches[0].start() > 0:
        intro = markdown[:matches[0].start()].strip()
        intro_clean = clean_markdown(intro)
        if intro_clean:
            sections.append({
                'heading': '',
                'level': 0,
                'content': intro_clean,
            })

    # Découper entre chaque titre
    for i, match in enumerate(matches):
        level = len(match.group(1))
        heading_text = match.group(2).strip()

        # Le contenu de cette section va jusqu'au prochain titre (ou fin du doc)
        start = match.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(markdown)
        body = markdown[start:end].strip()
        body_clean = clean_markdown(body)

        sections.append({
            'heading': heading_text,
            'level': level,
            'content': body_clean,
        })

    return sections


def build_section_title(sections: list, idx: int) -> str:
    """
    Construit le fil d'Ariane de la section courante.
    Ex: "Chapitre 1 > Section 2 > Sous-section 3"
    """
    current = sections[idx]
    if not current['heading']:
        return ''

    current_level = current['level']
    path = [current['heading']]

    # Remonter dans la liste pour trouver les parents (niveaux inférieurs)
    for j in range(idx - 1, -1, -1):
        ancestor = sections[j]
        if ancestor['level'] < current_level and ancestor['heading']:
            path.insert(0, ancestor['heading'])
            current_level = ancestor['level']
            if current_level == 1:
                break

    return ' > '.join(path)


def split_long_text(text: str, max_words: int, target_words: int) -> list:
    """
    Découpe un texte trop long en sous-chunks de ~target_words mots.
    Coupe aux frontières de phrase ('. ') autant que possible.
    """
    words = text.split()
    if len(words) <= max_words:
        return [text]

    chunks = []
    sentences = re.split(r'(?<=[.!?])\s+', text)
    current = []
    current_words = 0

    for sentence in sentences:
        s_words = len(sentence.split())
        if current_words + s_words > target_words and current:
            chunks.append(' '.join(current))
            current = [sentence]
            current_words = s_words
        else:
            current.append(sentence)
            current_words += s_words

    if current:
        chunks.append(' '.join(current))

    return chunks if chunks else [text]


def build_chunks(sections: list) -> list:
    """
    Construit la liste finale de chunks en gérant :
    - La fusion des sections trop courtes avec la suivante
    - La découpe des sections trop longues
    """
    chunks = []
    buffer_content = ''
    buffer_heading = ''
    buffer_level = 0
    buffer_breadcrumb = ''

    for i, section in enumerate(sections):
        content = section['content']
        if not content:
            continue

        word_count = len(content.split())

        # Construire le fil d'Ariane une seule fois pour cette section
        breadcrumb = build_section_title(sections, i)

        if word_count < MIN_WORDS:
            # Section trop courte → fusionner avec le buffer courant
            if buffer_content:
                buffer_content += '\n\n' + content
            else:
                buffer_content = content
                buffer_heading = section['heading']
                buffer_level = section['level']
                buffer_breadcrumb = breadcrumb
        else:
            # Vider le buffer si on a accumulé quelque chose
            if buffer_content:
                sub_chunks = split_long_text(buffer_content, MAX_WORDS, TARGET_WORDS)
                for sc in sub_chunks:
                    wc = len(sc.split())
                    if wc > 10:
                        chunks.append({
                            'content': sc.strip(),
                            'section_title': buffer_breadcrumb,
                            'heading_level': buffer_level,
                            'word_count': wc,
                        })
                buffer_content = ''
                buffer_heading = ''
                buffer_level = 0
                buffer_breadcrumb = ''

            # Traiter la section courante
            sub_chunks = split_long_text(content, MAX_WORDS, TARGET_WORDS)
            for sc in sub_chunks:
                wc = len(sc.split())
                if wc > 10:
                    chunks.append({
                        'content': sc.strip(),
                        'section_title': breadcrumb,
                        'heading_level': section['level'],
                        'word_count': wc,
                    })

    # Vider le buffer restant
    if buffer_content:
        sub_chunks = split_long_text(buffer_content, MAX_WORDS, TARGET_WORDS)
        for sc in sub_chunks:
            wc = len(sc.split())
            if wc > 10:
                chunks.append({
                    'content': sc.strip(),
                    'section_title': buffer_breadcrumb,
                    'heading_level': buffer_level,
                    'word_count': wc,
                })

    return chunks


def main():
    if len(sys.argv) < 3:
        print("Usage: python pdf_to_semantic_chunks.py <pdf_path> <output_json_path>", file=sys.stderr)
        sys.exit(1)

    pdf_path = sys.argv[1]
    output_path = sys.argv[2]

    t0 = time.time()

    try:
        from docling.document_converter import DocumentConverter, PdfFormatOption
        from docling.datamodel.base_models import InputFormat
        from docling.datamodel.pipeline_options import PdfPipelineOptions
        try:
            import torch
            torch.set_num_threads(1)
        except ImportError:
            pass
    except ImportError as e:
        print(f"ERREUR import Docling: {e}", file=sys.stderr)
        sys.exit(1)

    try:
        # Même configuration que docling_export.py (pas d'OCR — le PDF est déjà OCRisé)
        pipeline_options = PdfPipelineOptions()
        pipeline_options.do_ocr = False

        converter = DocumentConverter(
            format_options={
                InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)
            }
        )

        result = converter.convert(pdf_path)
        markdown_full = result.document.export_to_markdown()

    except MemoryError as e:
        print(f"ERREUR Mémoire insuffisante pour Docling: {e}", file=sys.stderr)
        sys.exit(1)
    except TimeoutError as e:
        print(f"ERREUR {e}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"ERREUR conversion Docling: {e}", file=sys.stderr)
        sys.exit(1)

    try:
        sections = split_into_sections(markdown_full)
        chunks   = build_chunks(sections)
    except Exception as e:
        print(f"ERREUR découpage Markdown: {e}", file=sys.stderr)
        sys.exit(1)

    total_words = sum(c['word_count'] for c in chunks)
    elapsed     = round(time.time() - t0, 1)

    output = {
        'markdown_full': markdown_full,
        'chunks': chunks,
        'stats': {
            'total_chunks': len(chunks),
            'total_words': total_words,
            'processing_time_s': elapsed,
        }
    }

    try:
        with open(output_path, 'w', encoding='utf-8') as f:
            json.dump(output, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"ERREUR écriture JSON: {e}", file=sys.stderr)
        sys.exit(1)

    # Résumé sur stdout pour le worker PHP
    print(f"SUCCESS: {len(chunks)} chunks, {total_words} mots, {elapsed}s")

    # Désactiver l'alarme en cas de succès
    try:
        signal.alarm(0)
    except (AttributeError, ValueError):
        pass

    sys.exit(0)


if __name__ == '__main__':
    main()
