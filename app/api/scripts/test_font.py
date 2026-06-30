import sys
from transformers import pipeline
from PIL import Image, ImageDraw, ImageFont

# Create a dummy image with text if no image is provided
image_path = "test_font_image.jpg"
if len(sys.argv) > 1:
    image_path = sys.argv[1]
else:
    print(f"No image provided, generating a dummy image at {image_path}...")
    img = Image.new('RGB', (400, 100), color=(255, 255, 255))
    d = ImageDraw.Draw(img)
    # Just draw some text
    d.text((10,10), "Hello World", fill=(0,0,0))
    img.save(image_path)

print(f"Loading model dchen0/font-classifier-v4...")
font_recognizer = pipeline("image-classification", model="dchen0/font-classifier-v4")

print(f"Analyzing {image_path}...")
result = font_recognizer(image_path)

print("\n--- RESULTS ---")
for res in result:
    print(f"Font: {res['label']} (Confidence: {res['score']:.4f})")
