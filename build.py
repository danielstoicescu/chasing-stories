"""Assemble the site.

  python3 build.py          -> dist/index.html  (one self-contained file, images inlined; what gets published)
  python3 build.py --dev    -> dist/dev.html    (same page, images loaded from ../assets for quick iteration)
  python3 build.py --web    -> public/          (what the server serves: index.html + assets/ as files)
"""
import subprocess, base64, json, os, re, sys

ROOT = os.path.dirname(os.path.abspath(__file__))
dev = '--dev' in sys.argv
web = '--web' in sys.argv   # real server: images stay separate files next to index.html
cms = '--cms' in sys.argv   # the PHP site in cms/: template with slots PHP fills, every asset, seed content
src = lambda f: open(os.path.join(ROOT, 'src', f), encoding='utf-8').read()

shell, css, data, app = src('shell.html'), src('styles.css'), src('data.js') + '\n' + src('videos.js'), src('app.js')
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
# face / head boxes the palette squares must avoid (auto-detected, then manual fixes win per image)
avoid = {}
for fn in ('avoid.json', 'avoid_manual.json'):
    p = os.path.join(ROOT, 'assets', fn)
    if os.path.exists(p):
        for k, v in json.load(open(p)).items():
            if k in used: avoid[k] = v
asset_js = 'const ASSETS=%s;\nconst MANIFEST=%s;\nconst AVOID=%s;' % (json.dumps(assets), json.dumps(man), json.dumps(avoid))

out = (shell.replace('/*STYLES*/', css)
            .replace('/*ASSETS*/', asset_js)
            .replace('/*DATA*/', data)
            .replace('/*APP*/', app))

os.makedirs(os.path.join(ROOT, 'dist'), exist_ok=True)
if cms:
    import shutil, subprocess
    C = os.path.join(ROOT, 'cms')
    # 1. page template: PHP puts <title>/meta in <!--HEAD--> and window.SITE_DATA + ASSETS/MANIFEST/AVOID in /*BOOT*/
    body = re.sub(r'^<title>.*?</title>\s*', '', shell, count=1)
    tpl = (body.replace('/*STYLES*/', css).replace('/*ASSETS*/', '/*BOOT*/').replace('/*DATA*/', data).replace('/*APP*/', app))
    open(os.path.join(C, 'site.template.html'), 'w', encoding='utf-8').write(
        '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><!--HEAD--></head><body>' + tpl + '</body></html>')
    # 2. every shipped image (the whole Drive selection, so the panel's library has them), logos, manifest, face boxes
    A = os.path.join(C, 'assets')
    os.makedirs(os.path.join(A, 'logo'), exist_ok=True)
    for f in os.listdir(os.path.join(ROOT, 'assets')):
        src_f = os.path.join(ROOT, 'assets', f)
        if f.endswith('.webp') or f in ('manifest.json', 'avoid.json', 'avoid_manual.json'):
            shutil.copy(src_f, os.path.join(A, f))
    for f in logos:
        shutil.copy(os.path.join(ROOT, 'assets', 'logo', f), os.path.join(A, 'logo', f))
    subprocess.run([sys.executable, os.path.join(ROOT, 'tools', 'make_thumbs.py')], check=False)
    open(os.path.join(A, 'favicon.svg'), 'w').write('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 3"><rect width="3" height="3" fill="#0F1514"/><rect x="0" y="2" width="1" height="1" fill="#fff"/><rect x="1" y="1" width="1" height="1" fill="#fff"/><rect x="2" y="0" width="1" height="1" fill="#fff"/></svg>')
    # 3. starting content for the installer
    subprocess.run(['node', os.path.join(ROOT, 'tools', 'make_seed.mjs')], check=True)
    print('cms build: cms/site.template.html + %d images' % len([f for f in os.listdir(A) if f.endswith('.webp')]))
    sys.exit(0)
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
    # the vhost only accepts index.php as a directory index (it was a WordPress app), so hand the page over from PHP
    open(os.path.join(ROOT, 'index.php'), 'w').write(
        "<?php\n// Serves the static Chasing Stories page at / (the server's directory index is index.php only).\n"
        "header('Content-Type: text/html; charset=utf-8');\nheader('X-Robots-Tag: noindex, nofollow');\nreadfile(__DIR__ . '/index.html');\n")
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
