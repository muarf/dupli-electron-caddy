import sys
from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.base_models import InputFormat
from docling.datamodel.pipeline_options import PdfPipelineOptions

if len(sys.argv) < 3:
    print("Usage: python docling_export.py <input_pdf_path> <output_html_path>")
    sys.exit(1)

pdf_path = sys.argv[1]
html_path = sys.argv[2]

# Configure Docling to not run OCR itself (we pre-OCR the PDF)
pipeline_options = PdfPipelineOptions()
pipeline_options.do_ocr = False

converter = DocumentConverter(
    format_options={
        InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)
    }
)

try:
    # Convert the document
    result = converter.convert(pdf_path)
    
    # Export to HTML
    html_content = result.document.export_to_html()
    
    with open(html_path, 'w', encoding='utf-8') as f:
        f.write(html_content)
        
    print(f"SUCCESS: {html_path}")
except Exception as e:
    print(f"ERROR: {str(e)}")
    sys.exit(1)
