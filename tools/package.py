"""Zips cms/ into dist/chasing-stories-server.zip for the client's server (no local config, uploads or cache)."""
import os, zipfile
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
C = os.path.join(ROOT, 'cms')
out = os.path.join(ROOT, 'dist', 'chasing-stories-server.zip')
skip_files = {'includes/config.php', 'router.php'}
n = 0
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for dp, dn, fn in os.walk(C):
        rel_dir = os.path.relpath(dp, C).replace(os.sep, '/')
        for f in fn:
            rel = (f if rel_dir == '.' else rel_dir + '/' + f)
            top = rel.split('/')[0]
            if rel in skip_files or f == '.DS_Store':
                continue
            if top in ('cache', 'uploads') and f != '.htaccess':
                continue
            z.write(os.path.join(dp, f), rel)
            n += 1
print('%s  %d files  %.1f MB' % (os.path.relpath(out, ROOT), n, os.path.getsize(out) / 1e6))
