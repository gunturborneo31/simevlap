#!/usr/bin/env python3
import sys

if len(sys.argv) < 2:
    print('Usage: remove_bom.py <file>')
    sys.exit(2)

path = sys.argv[1]
with open(path, 'rb') as f:
    data = f.read()
# UTF-8 BOM
bom = b'\xef\xbb\xbf'
if data.startswith(bom):
    print('BOM found — removing')
    data = data[len(bom):]
    with open(path, 'wb') as f:
        f.write(data)
    print('Wrote file without BOM:', path)
else:
    print('No BOM found in', path)
