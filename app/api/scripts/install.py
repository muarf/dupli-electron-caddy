import sys
import os
import subprocess
import json

def run_cmd(cmd, env=None):
    print(f"Running: {' '.join(cmd)}")
    result = subprocess.run(cmd, env=env)
    if result.returncode != 0:
        print(f"Error executing: {' '.join(cmd)}")
        sys.exit(result.returncode)

def main():
    if len(sys.argv) < 2:
        print("Usage: install.py <target_directory>")
        sys.exit(1)

    target_dir = sys.argv[1]
    os.makedirs(target_dir, exist_ok=True)
    
    # We will use this python executable to run pip
    python_exe = sys.executable

    print(f"--- Starting local AI installation in: {target_dir} ---")
    
    # Define HF_HOME so models are downloaded to the target directory
    hf_home = os.path.join(target_dir, "hf_cache")
    os.makedirs(hf_home, exist_ok=True)
    
    env = os.environ.copy()
    env["HF_HOME"] = hf_home

    print("1. Installing PyTorch (CPU)...")
    run_cmd([python_exe, "-m", "pip", "install", "torch", "torchvision", "--index-url", "https://download.pytorch.org/whl/cpu"])

    print("2. Installing Transformers and Docling...")
    run_cmd([python_exe, "-m", "pip", "install", "transformers", "docling", "pillow"])

    print("3. Downloading and caching HuggingFace Font Classifier model...")
    try:
        from transformers import pipeline
        # Force download of the model to the local cache
        _ = pipeline("image-classification", model="dchen0/font-classifier-v4")
        print("Font classifier model cached successfully.")
    except Exception as e:
        print(f"Error caching font model: {e}")

    print("4. Downloading and caching Docling models...")
    try:
        from docling.document_converter import DocumentConverter
        # Instantiating the converter triggers the download of the layout models
        _ = DocumentConverter()
        print("Docling models cached successfully.")
    except Exception as e:
        print(f"Error caching Docling models: {e}")

    print("\n--- Installation complete! ---")
    print("You can now close this window.")

if __name__ == "__main__":
    main()
