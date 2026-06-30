import sys
import os

try:
    import docx
except ImportError:
    print("Erreur : La bibliotheque 'python-docx' n'est pas installee.")
    sys.exit(1)

def text_to_docx(txt_path, docx_path):
    if not os.path.exists(txt_path):
        print(f"Erreur : Le fichier source {txt_path} n'existe pas.")
        sys.exit(1)
        
    doc = docx.Document()
    with open(txt_path, 'r', encoding='utf-8') as f:
        text = f.read()
    
    paragraphs = text.split('\n\n')
    for p_text in paragraphs:
        p_text = p_text.strip().replace('\n', ' ')
        if p_text:
            p = doc.add_paragraph(p_text)
            # Ajoute un espace apres le paragraphe
            p.paragraph_format.space_after = docx.shared.Pt(12)
            
    doc.save(docx_path)
    print("Docx genere avec succes.")

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python text_to_docx.py <source.txt> <destination.docx>")
        sys.exit(0)
    text_to_docx(sys.argv[1], sys.argv[2])
