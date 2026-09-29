"""Assemble the site.

  python3 build.py          -> dist/index.html  (one self-contained file, images inlined; what gets published)
  python3 build.py --dev    -> dist/dev.html    (same page, images loaded from ../assets for quick iteration)
  python3 build.py --web    -> public/          (what the server serves: index.html + assets/ as files)
"""
import base64, json, os, re, sys

ROOT = os.path.dirname(os.path.abspath(__file__))
dev = '--dev' in sys.argv
web = '--web' in sys.argv   # real server: images stay separate files next to index.html
src = lambda f: open(os.path.join(ROOT, 'src', f), encoding='utf-8').read()

shell, css, data, app = src('shell.html'), src('styles.css'), src('data.js'), src('app.js')
manifest = json.load(open(os.path.join(ROOT, 'assets', 'manifest.json')))

# only ship the images the code actually references
code = data + app
used = sorted(k for k in manifest if re.search(r"['\"]%s['\"]" % re.escape(k), code))
logos = sorted(os.listdir(os.path.join(ROOT, 'assets', 'logo')))

def uri(path, mime):
    with open(path, 'rb') as f:
        return 'data:%s;base64,%s' % (mime, base64.b64encode(f.read()).decode())

assets = {}
for k in used:
    p = os.path.join(ROOT, 'assets', k + '.webp')
    assets[k] = ('assets/%s.webp' % k) if web else ('../assets/%s.webp' % k) if dev else uri(p, 'image/webp')
for f in logos:
    p = os.path.join(ROOT, 'assets', 'logo', f)
    mime = 'image/svg+xml' if f.endswith('.svg') else 'image/png'
    assets['logo/' + f] = ('assets/logo/' + f) if web else ('../assets/logo/' + f) if dev else uri(p, mime)

man = {k: manifest[k] for k in used}
asset_js = 'const ASSETS=%s;\nconst MANIFEST=%s;' % (json.dumps(assets), json.dumps(man))

out = (shell.replace('/*STYLES*/', css)
            .replace('/*ASSETS*/', asset_js)
            .replace('/*DATA*/', data)
            .replace('/*APP*/', app))

os.makedirs(os.path.join(ROOT, 'dist'), exist_ok=True)
if web:
    import shutil
    out_dir = os.path.join(ROOT, 'public')   # document root on the server; committed to git
    shutil.rmtree(out_dir, ignore_errors=True)
    os.makedirs(os.path.join(out_dir, 'assets', 'logo'))
    for k in used: shutil.copy(os.path.join(ROOT, 'assets', k + '.webp'), os.path.join(out_dir, 'assets'))
    for f in logos: shutil.copy(os.path.join(ROOT, 'assets', 'logo', f), os.path.join(out_dir, 'assets', 'logo'))
    head = ('<!doctype html><html lang="en"><head><meta charset="utf-8">'
            '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
            '<meta name="robots" content="noindex,nofollow">'
            '<meta name="description" content="Visual storytelling for luxury hospitality, travel and lifestyle brands. Photography, film and creative production, available worldwide.">'
            '</head><body>')
    open(os.path.join(out_dir, 'index.html'), 'w', encoding='utf-8').write(head + out + '</body></html>')
    open(os.path.join(out_dir, 'robots.txt'), 'w').write('User-agent: *\nDisallow: /\n')
    # the Bunnyshell server runs Apache: serve index.html, keep the test site out of search, cache images
    open(os.path.join(out_dir, '.htaccess'), 'w').write(
        'DirectoryIndex index.html\n'
        '<IfModule mod_headers.c>\n  Header set X-Robots-Tag "noindex, nofollow"\n</IfModule>\n'
        '<IfModule mod_expires.c>\n  ExpiresActive On\n  ExpiresByType image/webp "access plus 30 days"\n  ExpiresByType image/svg+xml "access plus 30 days"\n  ExpiresByType image/png "access plus 30 days"\n</IfModule>\n')
    # the Bunnyshell vhost serves the repo root and ignores .htaccess, so the page also lives at the root;
    # it points at assets/ in the root, which already holds every referenced image under the same name
    shutil.copy(os.path.join(out_dir, 'index.html'), os.path.join(ROOT, 'index.html'))
    shutil.copy(os.path.join(out_dir, 'robots.txt'), os.path.join(ROOT, 'robots.txt'))
    print('web build: public/  (%d images)' % len(used))
    sys.exit(0)
name = 'dev.html' if dev else 'index.html'
path = os.path.join(ROOT, 'dist', name)
open(path, 'w', encoding='utf-8').write(out)
# local preview wrapper (the artifact host adds this skeleton itself)
if dev:
    open(os.path.join(ROOT, 'dist', 'preview.html'), 'w', encoding='utf-8').write(
        '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>' + out + '</body></html>')
else:
    open(os.path.join(ROOT, 'dist', 'check.html'), 'w', encoding='utf-8').write(
        '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"></head><body>' + out + '</body></html>')
import shutil
if not dev: shutil.copy(os.path.join(ROOT, "dist", "check.html"), os.path.join(ROOT, "Chasing Stories - site.html"))
print('%s  %d images  %.1f MB' % (name, len(used), os.path.getsize(path) / 1e6))
missing = [k for k in set(re.findall(r"['\"]((?:palau|heritance|hideaway|hoiana|bangkok|millennium|sixsenses|westin|film|ph|pl|hv|ab|w|p|s|f)-[a-z0-9-]+)['\"]", code)) if k not in manifest]
if missing: print('MISSING assets:', sorted(missing))
