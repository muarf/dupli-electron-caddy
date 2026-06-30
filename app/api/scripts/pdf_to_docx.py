import sys
import os

try:
    from pdf2docx import Converter
except ImportError:
    print("Erreur : La bibliotheque 'pdf2docx' n'est pas installee dans l'environnement Python.")
    sys.exit(1)

def convert_pdf_to_docx(pdf_path, docx_path):
    if not os.path.exists(pdf_path):
        print(f"Erreur : Le fichier source {pdf_path} n'existe pas.")
        sys.exit(1)
        
    print(f"Conversion de {pdf_path} en {docx_path}...")
    try:
        cv = Converter(pdf_path)
        cv.convert(docx_path, start=0, pages=None)
        cv.close()
        print("Conversion terminee avec succes.")
    except Exception as e:
        print(f"Erreur lors de la conversion : {str(e)}")
        sys.exit(1)

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python pdf_to_docx.py <source.pdf> <destination.docx>")
        sys.argv = ["", "test.pdf", "test.docx"]
    convert_pdf_to_docx(sys.argv[1], sys.argv[2])
