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
    dest_dir = os.path.dirname(docx_path)
    old_cwd = os.getcwd()
    cv = None
    try:
        # Se placer dans le dossier de destination pour permettre au multiprocessing
        # d'écrire ses fichiers temporaires de pages sans conflit de permissions
        if dest_dir and os.path.exists(dest_dir):
            os.chdir(dest_dir)

        cv = Converter(pdf_path)
        cv.convert(docx_path, multi_processing=True, cpu_count=0)
        print("Conversion terminee avec succes.")
    except Exception as e:
        print(f"Erreur lors de la conversion : {str(e)}")
        sys.exit(1)
    finally:
        if cv is not None:
            try:
                cv.close()
            except Exception:
                pass
        if dest_dir and os.path.exists(dest_dir):
            import glob
            for temp_json in glob.glob(os.path.join(dest_dir, "pages-*.json")):
                try:
                    os.remove(temp_json)
                except OSError:
                    pass
        try:
            os.chdir(old_cwd)
        except OSError:
            pass

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python pdf_to_docx.py <source.pdf> <destination.docx>")
        sys.argv = ["", "test.pdf", "test.docx"]
    convert_pdf_to_docx(sys.argv[1], sys.argv[2])
