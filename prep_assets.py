# Downloads the chosen Drive images and writes web-sized WebP files + manifest.json
import json,re,os,io,urllib.request,concurrent.futures as cf
from PIL import Image
G=json.load(open('raw/groups.json'))
def pick(group,idx): return [G[group][i][1] for i in idx]
SEL={
 'palau':    pick('FS Palau PHOTO',[30,9,16,31,21,36,44,57,59,35]),
 'heritance':pick('Heritance Aarah PHOTO',[38,9,14,15,12,7,26,33,36,40]),
 'hideaway': pick('Hideaway PHOTO',[7,1,13,4,15,33,25,22,36,38]),
 'hoiana':   pick('Hoiana PHOTO',[17,1,5,16,31,26,8,23,40,43]),
 'bangkok':  pick('FS Bangkok PHOTO',[3,1,4,9,18,13,19,24,7,20]),
 'millennium':pick('Millenium PHOTO',[13,2,15,23,0,33,28,11,39,44]),
 'sixsenses':pick('Six Senses PHOTO',[9,12,1,17,21,6,14,22,11,24]),
 'westin':   pick('Westin PHOTO',[26,33,40,29,45,15,62,110,87,76]),
}
def spread(group,n):
    L=G[group];return [L[round(i*(len(L)-1)/(n-1))][1] for i in range(n)]
CAT={'hospitality':spread('04_PHOTOGRAPHY/Hospitality',8),'interiors':spread('04_PHOTOGRAPHY/Interiors',8),
     'lifestyle':spread('04_PHOTOGRAPHY/Lifestyle',8),'food':spread('04_PHOTOGRAPHY/Food',8),
     'nature':spread('04_PHOTOGRAPHY/Destination',8),'product':spread('04_PHOTOGRAPHY/Brand',6)}
FILMG={'palau':'05_FILM/Selected films/Four Seasons Explorer Palau','heritance':'05_FILM/Selected films/Heritance Aarah',
 'bangkok':'05_FILM/Selected films/Four Seasons Bangkok','hideaway':'05_FILM/Selected films/Hideaway Beach Resort&SPA',
 'intercontinental':'05_FILM/Selected films/Intercontinental','westin':'05_FILM/Selected films/Westin','italy':'05_FILM/Selected films/Italy',
 'mazda':'05_FILM/Selected films/Mazda Europe','garmin':'05_FILM/Selected films/Mazda Europe/Garmin','dior':'05_FILM/Selected films/DIOR'}
jobs=[]
for p,ids in SEL.items():
    for k,i in enumerate(ids): jobs.append((f'{p}-{k+1}',i,1100,64))
for c,ids in CAT.items():
    for k,i in enumerate(ids): jobs.append((f'ph-{c}-{k+1}',i,900,62))
for f,g in FILMG.items():
    for k,(n,i) in enumerate(G.get(g,[])): jobs.append((f'film-{f}-{k+1}',i,1100,64))
def get(j):
    key,i,lim,q=j;out=f'assets/{key}.webp'
    if os.path.exists(out): return key,os.path.getsize(out)
    try:
        d=urllib.request.urlopen(f'https://drive.google.com/thumbnail?id={i}&sz=w{lim*2}',timeout=90).read()
        im=Image.open(io.BytesIO(d)).convert('RGB');im.thumbnail((lim,lim),Image.LANCZOS);im.save(out,'WEBP',quality=q,method=6)
        return key,os.path.getsize(out)
    except Exception as e: return key,str(e)
with cf.ThreadPoolExecutor(12) as ex: R=list(ex.map(get,jobs))
bad=[r for r in R if isinstance(r[1],str)];print('bad',bad)
# existing homepage/site images -> webp too
for f in os.listdir('site/i'):
    k=f.rsplit('.',1)[0];out=f'assets/{k}.webp'
    if os.path.exists(out): continue
    im=Image.open('site/i/'+f).convert('RGB');big=k in('hero','hero-m','prefooter')
    im.thumbnail((1800,1800) if big else (1200,1200),Image.LANCZOS);im.save(out,'WEBP',quality=70 if big else 64,method=6)
man={}
for f in sorted(os.listdir('assets')):
    if f.endswith('.webp'):
        im=Image.open('assets/'+f);man[f[:-5]]=[im.width,im.height]
json.dump(man,open('assets/manifest.json','w'))
tot=sum(os.path.getsize('assets/'+f) for f in os.listdir('assets'))
print(len(man),'images',tot//1024,'KB')
