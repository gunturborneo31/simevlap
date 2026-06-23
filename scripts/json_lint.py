#!/usr/bin/env python3
import sys, json

if len(sys.argv) < 2:
    print('Usage: json_lint.py <file>')
    sys.exit(2)
path = sys.argv[1]
try:
    with open(path, 'r', encoding='utf-8') as f:
        text = f.read()
except Exception as e:
    print('Failed to read file:', e)
    sys.exit(2)
try:
    json.loads(text)
    print('OK: JSON parsed successfully')
    sys.exit(0)
except json.JSONDecodeError as e:
    print(f'JSONDecodeError: {e.msg} at line {e.lineno} col {e.colno} (pos {e.pos})')
    lines = text.splitlines()
    i = max(0, e.lineno-1)
    start = max(0, i-5)
    end = min(len(lines), i+5)
    print('\nContext around error:')
    for idx in range(start, end):
        prefix = '>>' if idx == i else '  '
        # show printable representation safely
        line = lines[idx]
        print(f"{prefix} {idx+1}: {line}")
    sys.exit(1)
except Exception as e:
    print('Unexpected error while parsing JSON:', e)
    sys.exit(3)
