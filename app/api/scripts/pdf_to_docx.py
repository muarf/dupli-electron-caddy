import sys
import os
import tempfile
import shutil

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
    abs_pdf_path = os.path.abspath(pdf_path)
    abs_docx_path = os.path.abspath(docx_path)
    dest_dir = os.path.dirname(abs_docx_path)
    old_cwd = os.getcwd()
    work_dir = None
    cv = None
    try:
        # Isoler les fichiers temporaires du multiprocessing dans un sous-dossier dédié
        base_temp_dir = dest_dir if (dest_dir and os.path.exists(dest_dir)) else None
        work_dir = tempfile.mkdtemp(prefix="pdf2docx_", dir=base_temp_dir)
        os.chdir(work_dir)

        cv = Converter(abs_pdf_path)
        cv.convert(abs_docx_path, multi_processing=True, cpu_count=0)
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
        try:
            os.chdir(old_cwd)
        except OSError:
            pass
        if work_dir and os.path.exists(work_dir):
            shutil.rmtree(work_dir, ignore_errors=True)

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python pdf_to_docx.py <source.pdf> <destination.docx>")
        sys.argv = ["", "test.pdf", "test.docx"]
    convert_pdf_to_docx(sys.argv[1], sys.argv[2])
