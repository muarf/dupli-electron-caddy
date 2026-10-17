import sys
import os

try:
    from pdf2docx import Converter
except ImportError:
    print("Erreur : La bibliotheque 'pdf2docx' n'est pas installee dans l'environnement Python.")
    sys.exit(1)

import tempfile
import shutil
import glob

def convert_pdf_to_docx(pdf_path, docx_path):
    if not os.path.exists(pdf_path):
        print(f"Erreur : Le fichier source {pdf_path} n'existe pas.")
        sys.exit(1)
        
    print(f"Conversion de {pdf_path} en {docx_path}...")

    old_cwd = os.getcwd()
    # Create a dedicated temporary directory for the conversion process
    temp_dir = tempfile.mkdtemp(prefix="pdf2docx_")
    cv = None

    try:
        # Move into the temporary directory so multiprocessing artifacts are isolated
        os.chdir(temp_dir)

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

        # Always restore the original working directory
        os.chdir(old_cwd)

        # Clean up the entire temporary directory and its contents
        try:
            shutil.rmtree(temp_dir)
        except Exception as e:
            print(f"Warning: Failed to remove temporary directory {temp_dir}: {str(e)}")

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python pdf_to_docx.py <source.pdf> <destination.docx>")
        sys.argv = ["", "test.pdf", "test.docx"]
    convert_pdf_to_docx(sys.argv[1], sys.argv[2])
