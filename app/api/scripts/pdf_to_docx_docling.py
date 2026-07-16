import sys
import subprocess
import os
from pathlib import Path
import logging
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat

logging.basicConfig(level=logging.INFO)
from docling.datamodel.pipeline_options import PdfPipelineOptions

if len(sys.argv) < 3:
    print("Usage: python pdf_to_docx_docling.py <input_pdf_path> <output_docx_path>")
    sys.exit(1)

pdf_path = sys.argv[1]
docx_path = sys.argv[2]
md_path = docx_path + ".md"

# Configure Docling to not run OCR itself (we pre-OCR the PDF)
pipeline_options = PdfPipelineOptions()
pipeline_options.do_ocr = False

converter = DocumentConverter(
    format_options={
        InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)
    }
)

try:
    # 1. Convert PDF with Docling
    result = converter.convert(pdf_path)
    
    # 2. Export to Markdown
    md_content = result.document.export_to_markdown()
    
    # 3. Save Markdown to a temporary file
    with open(md_path, 'w', encoding='utf-8') as f:
        f.write(md_content)
        
    # 4. Use pandoc to convert Markdown to DOCX
    subprocess.run(['pandoc', '-f', 'markdown', '-t', 'docx', '-o', docx_path, md_path], check=True)
    
    # 5. Clean up temporary Markdown file
    if os.path.exists(md_path):
        os.remove(md_path)
        
    print(f"SUCCESS: {docx_path}")
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
