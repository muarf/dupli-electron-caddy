import sys
import json
import warnings
import logging
import os

# Set Hugging Face cache directory to a globally writable temp dir
# to avoid PermissionError for the web server user (www-data)
os.environ["HF_HOME"] = "/tmp/dupli_hf_cache"

# Suppress all warnings and standard output messages from transformers/PIL
warnings.filterwarnings("ignore")
os.environ["TF_CPP_MIN_LOG_LEVEL"] = "3"
logging.getLogger("transformers").setLevel(logging.ERROR)

# Redirect stdout to stderr for loading process to keep stdout clean for JSON
old_stdout = sys.stdout
sys.stdout = sys.stderr

try:
    from transformers import pipeline
    from PIL import Image

    if len(sys.argv) < 2:
        sys.exit(1)

    image_path = sys.argv[1]
    
    # Load model (prints to stderr)
    font_recognizer = pipeline("image-classification", model="dchen0/font-classifier-v4")
    
    # Run analysis
    result = font_recognizer(image_path)
    
    # Format the result
    formatted_result = []
    for res in result:
        formatted_result.append({
            "label": res["label"],
            "score": round(res["score"], 4)
        })
        
    # Restore stdout
    sys.stdout = old_stdout
    
    # Print clean JSON
    print(json.dumps(formatted_result))
    
except Exception as e:
    # Restore stdout and print error as JSON
    sys.stdout = old_stdout
    print(json.dumps({"error": str(e)}))
    sys.exit(1)
