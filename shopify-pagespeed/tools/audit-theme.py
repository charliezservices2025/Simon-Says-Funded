#!/usr/bin/env python3
"""
Static auditor for a Shopify theme export.

Scans an unzipped theme (or the .zip itself) for the performance anti-patterns identified in
the badgemyauto.com PageSpeed diagnosis, and reports file:line locations grouped by the stage
that fixes them.

    python3 audit-theme.py path/to/theme.zip
    python3 audit-theme.py path/to/theme-directory/

Findings are heuristic — a static scan cannot prove which element is the LCP. Treat the output
as a ranked list of places to look, confirmed against the PageSpeed report's LCP breakdown.
"""

import io
import os
import re
import sys
import json
import zipfile
from collections import defaultdict

SEV = {'high': '\033[91mHIGH\033[0m', 'med': '\033[93mMED \033[0m', 'low': '\033[96mLOW \033[0m',
       'info': '\033[92mINFO\033[0m'}
if not sys.stdout.isatty():
    SEV = {'high': 'HIGH', 'med': 'MED ', 'low': 'LOW ', 'info': 'INFO'}

# Sections whose images are LCP candidates.
HERO_HINT = re.compile(r'(hero|banner|slideshow|slide|carousel|featured|masthead|header-image)', re.I)

findings = defaultdict(list)


def add(stage, severity, path, line, message, detail=''):
    findings[stage].append((severity, path, line, message, detail))


def lines_of(text):
    return text.split('\n')


# ----------------------------------------------------------------------------- loading ------

def load_theme(target):
    """Return {relative_path: text} for all text files in a theme zip or directory."""
    files = {}
    exts = ('.liquid', '.json', '.js', '.css', '.scss')

    if zipfile.is_zipfile(target):
        with zipfile.ZipFile(target) as z:
            for name in z.namelist():
                if name.endswith('/') or not name.endswith(exts):
                    continue
                try:
                    files[name] = z.read(name).decode('utf-8', 'replace')
                except Exception:
                    pass
        # Record asset sizes for the payload check.
        with zipfile.ZipFile(target) as z:
            sizes = {i.filename: i.file_size for i in z.infolist()}
        return files, sizes

    sizes = {}
    for root, _, names in os.walk(target):
        for n in names:
            full = os.path.join(root, n)
            rel = os.path.relpath(full, target)
            try:
                sizes[rel] = os.path.getsize(full)
            except OSError:
                continue
            if n.endswith(exts):
                try:
                    with open(full, encoding='utf-8', errors='replace') as fh:
                        files[rel] = fh.read()
                except Exception:
                    pass
    return files, sizes


# ------------------------------------------------------------------------------ checks ------

def check_head(path, text):
    """theme.liquid <head>: blocking scripts, preconnect budget, ordering."""
    if not path.endswith('layout/theme.liquid') and not path.endswith('theme.liquid'):
        return

    head_match = re.search(r'<head[^>]*>(.*?)</head>', text, re.S | re.I)
    head = head_match.group(1) if head_match else text
    head_start = head_match.start(1) if head_match else 0
    offset = text[:head_start].count('\n')

    # Blocking scripts in <head>.
    for m in re.finditer(r'<script\b[^>]*>', head, re.I):
        tag = m.group(0)
        if 'src=' not in tag.lower():
            continue                       # inline; handled separately
        if re.search(r'\b(defer|async)\b', tag, re.I):
            continue
        if 'perf-delay-third-party' in tag:
            continue                       # must be blocking by design
        line = offset + head[:m.start()].count('\n') + 1
        add('Stage 1', 'high', path, line,
            'Render-blocking <script> in <head> (no defer/async)', tag.strip()[:110])

    # Preconnect budget.
    preconnects = re.findall(r'<link[^>]+rel=["\']?preconnect["\']?[^>]*>', head, re.I)
    if len(preconnects) > 4:
        add('Stage 1', 'med', path, offset + 1,
            f'{len(preconnects)} preconnect links in <head> (budget: 4)',
            'Report warns ">6 preconnect/preload connections found"')

    # Ordering: LCP preload should precede content_for_header.
    cfh = head.lower().find('content_for_header')
    preload = head.lower().find("rel=\"preload\"")
    if preload == -1:
        preload = head.lower().find("rel='preload'")
    if cfh != -1 and preload != -1 and preload > cfh:
        add('Stage 1', 'med', path, offset + head[:preload].count('\n') + 1,
            'Image preload appears AFTER content_for_header',
            'App scripts will outrank the hero in the request queue')
    if cfh != -1 and preload == -1:
        add('Stage 1', 'high', path, offset + head[:cfh].count('\n') + 1,
            'No image preload in <head>',
            'Hero is not preloaded; see snippets/perf-lcp-preload.liquid')

    # Meta description.
    if not re.search(r'<meta[^>]+name=["\']?description', text, re.I):
        add('Stage 4', 'med', path, 1, 'No <meta name="description"> in layout',
            'SEO audit: "Document does not have a meta description"')


def check_images(path, text):
    """Responsive images, LCP discoverability, CLS protection."""
    is_hero_file = bool(HERO_HINT.search(path))

    for m in re.finditer(r'<img\b[^>]*>', text, re.I | re.S):
        tag = m.group(0)
        line = text[:m.start()].count('\n') + 1
        low = tag.lower()

        has_wh = 'width=' in low and 'height=' in low
        lazy = 'loading="lazy"' in low or "loading='lazy'" in low
        eager = 'loading="eager"' in low or "loading='eager'" in low
        fp_high = 'fetchpriority="high"' in low or "fetchpriority='high'" in low

        if not has_wh:
            add('Stage 3', 'high', path, line,
                '<img> missing width/height attributes — CLS risk',
                'Your CLS is currently 0.006/0; missing dimensions is how that regresses')

        if 'srcset=' not in low:
            add('Stage 3', 'med', path, line, '<img> without srcset (no responsive candidates)')

        if is_hero_file and lazy:
            add('Stage 1', 'high', path, line,
                'LAZY image in a hero/banner file — likely the 15.8s LCP cause',
                'The preload scanner deliberately defers loading="lazy" images')

        if is_hero_file and eager and not fp_high:
            add('Stage 1', 'med', path, line,
                'Eager hero image without fetchpriority="high"',
                'fetchpriority is what moves it ahead of app scripts')

    # image_url without an explicit width produces full-size masters.
    for m in re.finditer(r'image_url(?!\s*:\s*width)', text):
        nxt = text[m.end():m.end() + 60]
        if 'width' in nxt:
            continue
        line = text[:m.start()].count('\n') + 1
        add('Stage 3', 'med', path, line, 'image_url without an explicit width:',
            'Serves an oversized derivative; cause of "Improve image delivery"')

    # CSS background images in hero files are undiscoverable by the preload scanner.
    if is_hero_file:
        for m in re.finditer(r'background-image\s*:\s*url', text, re.I):
            line = text[:m.start()].count('\n') + 1
            add('Stage 1', 'high', path, line,
                'CSS background-image in a hero file — invisible to the preload scanner',
                'Convert to <img> + object-fit: cover (identical visual result)')


def check_fonts(path, text):
    for m in re.finditer(r'font_face(?![^\n]*font_display)', text):
        line = text[:m.start()].count('\n') + 1
        add('Stage 3', 'low', path, line, "font_face without font_display: 'swap'",
            'Audit: "Font display" — text stays invisible while the font loads')

    for m in re.finditer(r'@font-face', text, re.I):
        block = text[m.start():m.start() + 400]
        if 'font-display' not in block.lower():
            line = text[:m.start()].count('\n') + 1
            add('Stage 3', 'low', path, line, '@font-face without font-display')


def check_third_party(path, text):
    """Hardcoded external scripts — the classic leftover from uninstalled apps."""
    for m in re.finditer(r'<script\b[^>]*\bsrc=["\']?(https?:)?//([^"\'/\s>]+)', text, re.I):
        host = m.group(2)
        if 'cdn.shopify.com' in host or '{{' in host or '{%' in host:
            continue
        line = text[:m.start()].count('\n') + 1
        add('Stage 2', 'high', path, line, f'Hardcoded third-party script: {host}',
            'Candidate for removal or for the deferral blocklist')

    if re.search(r'jquery', text, re.I) and path.endswith(('.liquid', '.js')):
        for m in re.finditer(r'jquery[.\-\w]*\.js', text, re.I):
            line = text[:m.start()].count('\n') + 1
            add('Stage 2', 'med', path, line, 'jQuery referenced',
                '~30KB parse + execute; usually replaceable with native DOM APIs')
            break


def check_app_embeds(files):
    """config/settings_data.json lists enabled app embed blocks — names the actual apps."""
    for path, text in files.items():
        if not path.endswith('settings_data.json'):
            continue
        try:
            data = json.loads(text)
        except Exception:
            continue

        current = data.get('current')
        if not isinstance(current, dict):
            continue
        blocks = current.get('blocks') or {}
        if not blocks:
            continue

        enabled = []
        for _, block in blocks.items():
            if not isinstance(block, dict):
                continue
            btype = block.get('type', '')
            if block.get('disabled') is True:
                continue
            # App embeds carry a "shopify://apps/..." type.
            if 'shopify://apps/' in btype:
                name = btype.split('shopify://apps/')[1].split('/')[0]
                enabled.append(name)

        for name in sorted(set(enabled)):
            add('Stage 2', 'info', path, 1, f'App embed ENABLED: {name}',
                'Each app embed injects JS on every page — the TBT hit list')


def check_assets(sizes):
    for path, size in sorted(sizes.items(), key=lambda kv: -kv[1]):
        if '/assets/' not in path and not path.startswith('assets/'):
            continue
        if size > 150_000 and path.endswith(('.js', '.css')):
            add('Stage 2', 'med', path, 1, f'Large theme asset: {size // 1024} KB',
                'Contributes to "Reduce unused JavaScript" (531 KiB)')
        elif size > 300_000 and path.endswith(('.png', '.jpg', '.jpeg', '.gif')):
            add('Stage 3', 'med', path, 1, f'Large image asset: {size // 1024} KB',
                'Page weight is 2,341 KiB; target < 1,000 KiB')


# ------------------------------------------------------------------------------- report -----

STAGE_DOCS = {
    'Stage 1': '02-lcp-and-render-blocking.md  (mobile LCP 15.8s -> 2.2s, up to +25 pts)',
    'Stage 2': '03-javascript-and-tbt.md       (TBT 640ms -> 129ms, the path to desktop 95)',
    'Stage 3': '04-images-fonts-caching.md     (payload, responsive images, fonts)',
    'Stage 4': '05-accessibility-bestpractices-seo.md (a11y / best practices / SEO)',
}
ORDER = {'high': 0, 'med': 1, 'low': 2, 'info': 3}


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 1

    target = sys.argv[1]
    if not os.path.exists(target):
        print(f'error: {target} not found')
        return 1

    files, sizes = load_theme(target)
    if not files:
        print(f'error: no theme files found in {target}')
        return 1

    print(f'Scanned {len(files)} theme files from {target}\n')

    for path, text in files.items():
        check_head(path, text)
        if path.endswith(('.liquid', '.js')):
            check_images(path, text)
            check_third_party(path, text)
        if path.endswith(('.liquid', '.css', '.scss')):
            check_fonts(path, text)
    check_app_embeds(files)
    check_assets(sizes)

    total = 0
    for stage in ('Stage 1', 'Stage 2', 'Stage 3', 'Stage 4'):
        items = findings.get(stage)
        if not items:
            continue
        print(f'\n{"=" * 78}\n{stage} — {STAGE_DOCS[stage]}\n{"=" * 78}')
        items.sort(key=lambda f: (ORDER[f[0]], f[1], f[2]))
        for sev, path, line, msg, detail in items:
            total += 1
            print(f'  [{SEV[sev]}] {path}:{line}')
            print(f'         {msg}')
            if detail:
                print(f'         -> {detail}')

    print(f'\n{"=" * 78}\n{total} findings.')
    print('Static heuristics: confirm the LCP element against the report\'s "LCP breakdown"')
    print('before rewriting a hero. See 01-IMPLEMENTATION-CHECKLIST.md for the order of work.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
