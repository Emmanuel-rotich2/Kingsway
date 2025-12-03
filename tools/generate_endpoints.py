#!/usr/bin/env python3
import re
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONTROLLERS = ROOT / 'api' / 'controllers'
OUT_DIR = ROOT / 'documantations' / 'REST APIs Endpoints'
OUT_DIR.mkdir(parents=True, exist_ok=True)
OUT_FILE = OUT_DIR / 'ENDPOINTS_FOR_FRONTEND.txt'

fn_pattern = re.compile(r'public\s+function\s+([a-zA-Z0-9_]+)\s*\(')
class_pattern = re.compile(r'class\s+([A-Za-z0-9_]+)')

mapping = []

for file in sorted(CONTROLLERS.glob('*.php')):
    controller = file.stem
    controller_name = controller.replace('Controller','').lower()
    with open(file, 'r', encoding='utf-8') as f:
        text = f.read()
    # find class name
    mclass = class_pattern.search(text)
    if mclass:
        # iterate functions
        for m in fn_pattern.finditer(text):
            fn = m.group(1)
            # ignore private/protected helpers (we matched public only)
            # derive verb
            verb = None
            for v in ['get','post','put','delete','patch']:
                if fn.lower().startswith(v):
                    verb = v.upper()
                    tail = fn[len(v):]
                    break
            if verb is None:
                # fallback: index or other
                verb = 'GET'
                tail = fn
            # create path by converting tail CamelCase to kebab
            if tail == '':
                path_tail = ''
            else:
                # insert dashes before uppercase letters, lower
                s = re.sub('(.)([A-Z][a-z]+)', r'\1-\2', tail)
                s = re.sub('([a-z0-9])([A-Z])', r'\1-\2', s).strip('-')
                path_tail = s.replace('_','-').lower()
            # build URL
            if path_tail == '':
                url = f"/api/{controller_name}"
            else:
                # if tail starts with plural controller name, drop duplicate
                possible_dup = controller_name.rstrip('s')
                if path_tail.startswith(possible_dup+'-'):
                    path_tail = path_tail[len(possible_dup)+1:]
                url = f"/api/{controller_name}/{path_tail}"
            # determine if id param likely required (heuristic: method name contains 'Get' at end or 'Get' after resource)
            id_hint = ''
            if re.search(r'Get$|Get[A-Z]|Get_', fn):
                id_hint = ' (optional id parameter possible)'
            if re.search(r'ById|Id$|Get$|Get[A-Z]|Get_', fn) and 'list' not in fn.lower():
                # leave as hint only
                pass
            mapping.append((verb, url, fn, id_hint))

# write file
with open(OUT_FILE, 'w', encoding='utf-8') as out:
    out.write('# Generated endpoint mapping for frontend\n')
    out.write('# Format: HTTP_VERB  PATH  => ControllerMethod  # notes\n\n')
    for verb, url, fn, hint in mapping:
        out.write(f"{verb:6}  {url:50}  => {fn}{hint}\n")

print('Wrote', OUT_FILE)
