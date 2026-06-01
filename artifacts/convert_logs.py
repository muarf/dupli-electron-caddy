import json
import re
import os

log_path = 'logs/chat_history.log'
json_path = 'logs/chat_history.json'

if not os.path.exists(log_path):
    print("Log file not found")
    exit(1)

with open(log_path, 'r', encoding='utf-8') as f:
    content = f.read()

entries = content.split('------------------')
history = []

# Pattern for header: [2026-04-29 16:31:46] (Luth-1.7B (Rapide)) Q: question | T: 59.9s
header_re = re.compile(r'\[(.*?)\] \((.*?)\) Q: (.*?) \| T: ([\d\.]+)s')

for entry in entries:
    entry = entry.strip()
    if not entry:
        continue
    
    header_match = header_re.search(entry)
    if not header_match:
        continue
    
    timestamp = header_match.group(1)
    model_str = header_match.group(2).lower()
    model = 'pro' if 'expert' in model_str or 'pro' in model_str else 'fast'
    question = header_match.group(3)
    elapsed = header_match.group(4)
    
    # Extract response
    response_part = entry.split('R: ', 1)
    if len(response_part) < 2:
        # Check if it's one of the one-line logs at the end
        if entry.count('\n') == 0:
             # Just a header line, no response recorded
             continue
        response_text = ""
    else:
        response_text = response_part[1].strip()
    
    thought = ""
    if '<think>' in response_text:
        thought_match = re.search(r'<think>(.*?)</think>', response_text, re.DOTALL)
        if thought_match:
            thought = thought_match.group(1).strip()
            response_text = response_text.replace(f'<think>{thought_match.group(1)}</think>', '').strip()
        else:
            # Maybe unterminated think
            thought = response_text.replace('<think>', '').strip()
            response_text = ""
    
    history.append({
        "timestamp": timestamp,
        "model": model,
        "question": question,
        "response": response_text,
        "thought": thought,
        "prompt": "(Ancien log - Prompt non conservé)",
        "sources": [],
        "elapsed": elapsed
    })

# Sort by timestamp descending
history.sort(key=lambda x: x['timestamp'], reverse=True)

# Write to JSON
with open(json_path, 'w', encoding='utf-8') as f:
    json.dump(history, f, indent=4, ensure_ascii=False)

print(f"Successfully converted {len(history)} entries to {json_path}")
