(()=>{
const RM = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (s,r=document)=>r.querySelector(s), $$ = (s,r=document)=>[...r.querySelectorAll(s)];
const esc = s=>String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const I = k=>ASSETS[k]||(k&&/[\/.]/.test(k)?k:'');          // asset key, or an upload path from the CMS
const DIM = k=>(MANIFEST[k]||[4,5]);
const LOGO = f=>ASSETS['logo/'+f]||(f&&/[\/.]/.test(f)?f:'');
const vsrc = f=>{const v=f&&f.video||'';if(v.startsWith('poster:'))return videoURL(v.slice(7));return v||videoURL(f&&f.k)};
const byslug = s=>PROJECTS.find(p=>p.slug===s);
const nf = n=>String(n).padStart(2,'0');
document.getElementById('yr').textContent = new Date().getFullYear();

/* ============================================================
   PIXEL SYSTEM
   One shape (the square), colour always sampled from the image.
   ============================================================ */
function loaded(img){return img.complete&&img.naturalWidth?Promise.resolve(img):new Promise(r=>{img.addEventListener('load',()=>r(img),{once:true});img.addEventListener('error',()=>r(null),{once:true})})}
function coverRect(iw,ih,cw,ch,pos=[.5,.5]){const s=Math.max(cw/iw,ch/ih),w=iw*s,h=ih*s;return[(cw-w)*pos[0],(ch-h)*pos[1],w,h]}
const hex=(r,g,b)=>'#'+[r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
function palette(img,n=6){
  const c=document.createElement('canvas');c.width=12;c.height=12;const x=c.getContext('2d',{willReadFrequently:true});x.drawImage(img,0,0,12,12);
  let d;try{d=x.getImageData(0,0,12,12).data}catch(e){return[]}
  const cells=[];for(let i=0;i<d.length;i+=4)cells.push([d[i],d[i+1],d[i+2]]);
  cells.sort((a,b)=>(a[0]*.3+a[1]*.59+a[2]*.11)-(b[0]*.3+b[1]*.59+b[2]*.11));
  return Array.from({length:n},(_,k)=>hex(...cells[Math.round((k+.5)*cells.length/n-.5)]));
}
function hash(x,y){const t=Math.sin(x*127.1+y*311.7)*43758.5453;return t-Math.floor(t)}
function vnoise(x,y){const i=Math.floor(x),j=Math.floor(y),f=x-i,g=y-j,u=f*f*(3-2*f),v=g*g*(3-2*g);
  return (hash(i,j)*(1-u)+hash(i+1,j)*u)*(1-v)+(hash(i,j+1)*(1-u)+hash(i+1,j+1)*u)*v}

/* Palette squares: four to seven per photograph, one per distinct colour, each placed where its colour lives,
   never on a face or head (boxes from tools/detect_people.py + manual fixes), and clear of the palette chip.
   Sizes: a quarter cell, one cell, four cells. The chip in the corner names the palette with a strategy word. */
function distRGB(a,b){const dr=a[0]-b[0],dg=a[1]-b[1],db=a[2]-b[2];return Math.sqrt(dr*dr*.3+dg*dg*.59+db*db*.11)}
function seedOf(str){let h=2166136261;for(const c of str){h^=c.charCodeAt(0);h=Math.imul(h,16777619)}return (h>>>0)/4294967295}
function paletteWord(cols){
  // brand-strategy vocabulary, chosen from lightness, saturation, warmth, contrast and dominant hue
  const hsl=cols.map(([r,g,b])=>{r/=255;g/=255;b/=255;const mx=Math.max(r,g,b),mn=Math.min(r,g,b),l=(mx+mn)/2,d=mx-mn;
    let h=0;if(d){h=mx===r?((g-b)/d)%6:mx===g?(b-r)/d+2:(r-g)/d+4;h*=60;if(h<0)h+=360}
    return {h,s:d?d/(1-Math.abs(2*l-1)):0,l,warm:r-b}});
  const avg=k=>hsl.reduce((a,c)=>a+c[k],0)/hsl.length;
  const L=avg('l'),S=avg('s'),W=avg('warm'),C=Math.max(...hsl.map(c=>c.l))-Math.min(...hsl.map(c=>c.l));
  const sat=hsl.filter(c=>c.s>.25),green=sat.filter(c=>c.h>70&&c.h<165).length,blue=sat.filter(c=>c.h>=165&&c.h<250).length;
  if(S<.12)return 'Timeless';
  if(C>.68)return 'Bold';
  if(green>=2&&green>=blue)return 'Rooted';
  if(L>.6)return W>.02?'Radiant':'Serene';
  if(L<.3)return W>.02?'Intimate':'Nocturnal';
  if(blue>=2)return 'Tranquil';
  return W>.04?'Welcoming':'Composed';
}
function spots(frame,img){
  if(frame.dataset.done)return;frame.dataset.done=1;
  const r=frame.getBoundingClientRect();if(r.width<2)return;
  const key=frame.dataset.k||'',av=(AVOID&&AVOID[key])||{h:[],s:[]};
  const W=r.width,H=r.height,land=W>H*1.1,cols=land?14:9,cell=W/cols,rows=Math.max(1,Math.floor(H/cell));
  const g=2,c=document.createElement('canvas');c.width=cols*g;c.height=rows*g;
  const x=c.getContext('2d',{willReadFrequently:true});const [dx,dy,dw,dh]=coverRect(img.naturalWidth,img.naturalHeight,cols*g,rows*g);x.drawImage(img,dx,dy,dw,dh);
  let d;try{d=x.getImageData(0,0,cols*g,rows*g).data}catch(e){return}
  // frame cells -> image coordinates, to test against the avoid boxes
  const sc=Math.max(W/img.naturalWidth,H/img.naturalHeight),ox=(W-img.naturalWidth*sc)/2,oy=(H-img.naturalHeight*sc)/2;
  const toImg=(fx,fy)=>[(fx-ox)/(img.naturalWidth*sc),(fy-oy)/(img.naturalHeight*sc)];
  const hits=(boxes,cx,cy,s,pad)=>{const [u0,v0]=toImg(cx*cell,cy*cell),[u1,v1]=toImg((cx+s)*cell,(cy+s)*cell);
    return boxes.some(b=>u1>b[0]-pad&&u0<b[2]+pad&&v1>b[1]-pad&&v0<b[3]+pad)};
  const chip=[cols-(land?5.2:4.2),rows-(land?1.9:1.7)];                 // keep the corner chip clear
  const cand=[];
  for(let yy=0;yy<rows*g;yy++)for(let xx=0;xx<cols*g;xx++){const k=(yy*cols*g+xx)*4,rgb=[d[k],d[k+1],d[k+2]];
    const mx=Math.max(...rgb),mn=Math.min(...rgb),l=rgb[0]*.3+rgb[1]*.59+rgb[2]*.11;
    const px=xx/g,py=yy/g;if(px>=chip[0]&&py>=chip[1])continue;
    if(hits(av.h,px,py,1,.02))continue;
    cand.push({x:px,y:py,rgb,score:(mx?(mx-mn)/mx:0)*.7+Math.abs(l-128)/255*.3-(hits(av.s,px,py,1,0)?.35:0)})}
  cand.sort((a,b)=>b.score-a.score);
  const want=4+Math.floor(seedOf(key||img.src.slice(-40))*4);            // 4..7, stable per photograph
  const chosen=cand.length?[cand[0]]:[];
  for(const th of [26,16,8]){
    while(chosen.length<want){let best=null,bd=-1;
      for(const p of cand){const cd=Math.min(...chosen.map(q=>distRGB(p.rgb,q.rgb)));const sd=Math.min(...chosen.map(q=>Math.hypot(p.x-q.x,p.y-q.y)));
        if(sd<1.5)continue;const v=cd+sd*2;if(cd>th&&v>bd){bd=v;best=p}}
      if(!best)break;chosen.push(best)}
    if(chosen.length>=want)break;
  }
  const sizes=[1,.5,2,1,.5,1,.5];
  const placed=[];
  chosen.forEach((p,i)=>{let s=sizes[i%sizes.length];let cx=Math.round(p.x/.5)*.5,cy=Math.round(p.y/.5)*.5;
    cx=Math.min(Math.max(0,cx),cols-s);cy=Math.min(Math.max(0,cy),rows-s);
    if(s===2&&(hits(av.h,cx,cy,2,.02)||(cx+2>chip[0]&&cy+2>chip[1])))s=1;          // a big square must not grow onto a head
    if(hits(av.h,cx,cy,s,.01))return;
    const e=document.createElement('i');e.className='sw';
    e.style.cssText=`left:${cx/cols*100}%;top:${cy*cell/H*100}%;width:${s/cols*100}%;background:${hex(...p.rgb)};--d:${i*70}ms`;
    frame.appendChild(e);placed.push(p.rgb)});
  if(!placed.length)return;
  // the palette chip, bottom right: grows on hover, names the palette, makes the squares on the photo pulse
  const pal=document.createElement('span');pal.className='pal';
  pal.innerHTML=placed.map(c=>`<i style="background:${hex(...c)}"></i>`).join('');
  pal.addEventListener('pointerenter',()=>frame.classList.add('pal-on'));
  pal.addEventListener('pointerleave',()=>frame.classList.remove('pal-on'));
  frame.appendChild(pal);
  requestAnimationFrame(()=>requestAnimationFrame(()=>frame.classList.add('sw-on')));
}

/* WebGL pixel field for full-bleed media (hero, CTA, project hero, 404).
   Takes an image today; a VideoTexture drops into the same shader later. */
const FRAG=`
precision highp float;
uniform sampler2D uTex; uniform vec2 uRes; uniform vec2 uImg; uniform vec2 uFocus; uniform float uDpr;
uniform float uCell; uniform float uStep; uniform float uDensity; uniform vec4 uBox; uniform float uForce;
uniform float uDark; uniform float uEdge; uniform float uScroll; uniform vec3 uTrail[10];
float h(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453);}
float n(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);return mix(mix(h(i),h(i+vec2(1,0)),f.x),mix(h(i+vec2(0,1)),h(i+vec2(1,1)),f.x),f.y);}
vec3 tex(vec2 px){float s=max(uRes.x/uImg.x,uRes.y/uImg.y);vec2 sz=uImg*s;vec2 u=(px-(uRes-sz)*uFocus)/sz;return texture2D(uTex,vec2(u.x,1.-u.y)).rgb;}
void main(){
  vec2 px=vec2(gl_FragCoord.x,uRes.y*uDpr-gl_FragCoord.y)/uDpr;
  vec2 id=floor(px/uCell);vec2 c0=id*uCell;vec2 cc=c0+uCell*.5;
  vec3 photo=tex(px);
  vec3 avg=vec3(0.);for(int i=0;i<3;i++)for(int j=0;j<3;j++)avg+=tex(c0+uCell*(vec2(float(i),float(j))+.5)/3.);avg/=9.;
  vec2 cu=cc/uRes;
  float inBox=step(uBox.x,cu.x)*step(cu.x,uBox.z)*step(uBox.y,cu.y)*step(cu.y,uBox.w);
  float dens=mix(uDensity,uDensity*.3,inBox);
  dens*=mix(.35,1.,step(64./uRes.y,cu.y));
  float f=n(id*.21+vec2(uStep*.11,uScroll-uStep*.05))*.72+h(id)*.28;
  float on=0.*step(1.-dens*1.25,f);
  // each trail point lives 1 -> 0; cells dissolve in and out one by one, sparse and soft
  float w=0.;
  for(int k=0;k<10;k++){vec3 t=uTrail[k];if(t.z>0.){float d=length(cc-t.xy)/uCell;
    float life=(1.-smoothstep(.82,1.,t.z))*smoothstep(0.,.55,t.z);   // quick fade in, long fade out
    float reach=1.-smoothstep(.6,2.1,d);                                // edges must be ascending in GLSL
    float lim=life*reach*.5*(1.-.75*inBox);
    float cellv=(h(id*1.37+vec2(float(k)*3.1,7.7))<lim)?1.:0.;       // strict: no cell when lim is 0
    w=max(w,cellv*life);}}
  on=max(on,w);
  on=max(on,uForce);
  vec2 m=mod(px,uCell);
  float edge=1.-step(1.,m.x)*step(1.,m.y)*step(m.x,uCell-1.)*step(m.y,uCell-1.);
  float ghost=0.*edge;
  vec2 pu=px/uRes;                                   // per pixel, so the shading is a smooth gradient, never a grid
  float d=distance(pu,vec2(.5,.52));
  photo*=1.-uDark*(1.-smoothstep(.05,.62,d));
  photo*=1.-uEdge*(.32*(1.-smoothstep(0.,.2,pu.y))+.28*smoothstep(.8,1.,pu.y));
  float r=h(id+13.1);
  if(r<.45){vec2 q=vec2(h(id+2.7),h(id+5.9))*uRes;vec2 qc=floor(q/uCell)*uCell;vec3 a2=vec3(0.);
    for(int i=0;i<2;i++)for(int j=0;j<2;j++)a2+=tex(qc+uCell*(vec2(float(i),float(j))+.5)/2.);avg=a2/4.;}
  float l=dot(avg,vec3(.299,.587,.114));
  avg=clamp(mix(vec3(l),avg,1.45),0.,1.);
  avg*=mix(.82,1.12,h(id+21.4));
  avg*=mix(1.,.8,inBox);
  ghost*=step(80./uRes.y,cu.y);
  vec3 col=mix(photo,avg,on*.9);
  col=mix(col,vec3(1.),ghost*.8);
  gl_FragColor=vec4(col,1.);
}`;
function pixelField(host,img,o){
  if(!window.THREE)return null;
  const probe=document.createElement('canvas');probe.width=probe.height=1;const px=probe.getContext('2d');px.drawImage(img,0,0,1,1);
  try{px.getImageData(0,0,1,1)}catch(e){return null}
  let renderer;try{renderer=new THREE.WebGLRenderer({antialias:false,powerPreference:'high-performance'})}catch(e){return null}
  if(!renderer.getContext())return null;
  const cv=renderer.domElement;cv.className='glc';cv.setAttribute('aria-hidden','true');
  host.insertBefore(cv,host.querySelector('.scrim')||host.querySelector('.copy'));
  const src=document.createElement('canvas');const sc=Math.min(1,2048/Math.max(img.naturalWidth,img.naturalHeight));
  src.width=Math.round(img.naturalWidth*sc);src.height=Math.round(img.naturalHeight*sc);const sx=src.getContext('2d');sx.drawImage(img,0,0,src.width,src.height);
  try{sx.getImageData(0,0,1,1)}catch(e){return null}
  const tex=new THREE.CanvasTexture(src);tex.minFilter=THREE.LinearFilter;tex.generateMipmaps=false;
  const trail=Array.from({length:10},()=>new THREE.Vector3());
  const U={uTex:{value:tex},uRes:{value:new THREE.Vector2()},uImg:{value:new THREE.Vector2(src.width,src.height)},uFocus:{value:new THREE.Vector2(...(o.focus||[.5,.5]))},
    uDpr:{value:1},uCell:{value:40},uStep:{value:0},uDensity:{value:o.density},uBox:{value:new THREE.Vector4(...o.box)},uForce:{value:0},
    uDark:{value:o.dark??.3},uEdge:{value:o.edge??1},uScroll:{value:0},uTrail:{value:trail}};
  const mat=new THREE.ShaderMaterial({uniforms:U,vertexShader:'void main(){gl_Position=vec4(position.xy,0.,1.);}',fragmentShader:FRAG,depthTest:false});
  const scene=new THREE.Scene(),cam=new THREE.Camera(),geo=new THREE.PlaneGeometry(2,2);scene.add(new THREE.Mesh(geo,mat));
  let base=40,introOn=false,alive=true,raf=0,it=0;
  const size=()=>{const r=host.getBoundingClientRect(),dpr=Math.min(devicePixelRatio||1,2);renderer.setPixelRatio(dpr);renderer.setSize(r.width,r.height,false);
    U.uRes.value.set(r.width,r.height);U.uDpr.value=dpr;base=r.width/(innerWidth<760?o.colsM||12:o.cols);host.dataset.cell=base.toFixed(1);if(!introOn)U.uCell.value=base};
  size();addEventListener('resize',size);host.classList.add('gl');
  if(introOn){U.uForce.value=1;const mul=[12,6,3,1.5];let i=0;U.uCell.value=base*mul[0];
    it=setInterval(()=>{i++;if(i<mul.length)U.uCell.value=base*mul[i];else{clearInterval(it);U.uForce.value=0;U.uCell.value=base;introOn=false}},200)}
  let tp=0,last=null;
  const pm=e=>{if(e.pointerType!=='mouse')return;const r=host.getBoundingClientRect(),x=e.clientX-r.left,y=e.clientY-r.top;
    if(!last||Math.hypot(x-last[0],y-last[1])>base*1.1){trail[tp].set(x,y,1);tp=(tp+1)%trail.length;last=[x,y]}};
  const pd=e=>{if(e.pointerType==='mouse')return;const r=host.getBoundingClientRect();for(let k=0;k<3;k++){trail[tp].set(e.clientX-r.left+(k-1)*base,e.clientY-r.top,1);tp=(tp+1)%trail.length}};
  host.addEventListener('pointermove',pm);host.addEventListener('pointerdown',pd);
  let vis=true;const io=new IntersectionObserver(es=>{vis=es[0].isIntersecting});io.observe(host);
  const t0=performance.now();let prev=t0;
  const loop=now=>{if(!alive)return;raf=requestAnimationFrame(loop);if(!vis)return;const dt=Math.min(.1,(now-prev)/1000);prev=now;
    U.uStep.value=RM?0:Math.floor((now-t0)/850);trail.forEach(v=>{v.z=Math.max(0,v.z-dt*.55)});
    const r=host.getBoundingClientRect();U.uScroll.value=Math.round(-r.top/Math.max(1,base))*.21;renderer.render(scene,cam)};
  raf=requestAnimationFrame(loop);
  const destroy=()=>{alive=false;cancelAnimationFrame(raf);clearInterval(it);io.disconnect();removeEventListener('resize',size);host.removeEventListener('pointermove',pm);host.removeEventListener('pointerdown',pd);
    geo.dispose();mat.dispose();U.uTex.value.dispose();renderer.dispose();renderer.forceContextLoss&&renderer.forceContextLoss();cv.remove()};
  destroy.useVideo=v=>{const vt=new THREE.VideoTexture(v);vt.minFilter=THREE.LinearFilter;vt.generateMipmaps=false;
    const old=U.uTex.value;U.uTex.value=vt;U.uImg.value.set(v.videoWidth,v.videoHeight);old.dispose()};
  return destroy;
}

/* ============================================================
   TEMPLATES
   ============================================================ */
const btn=(href,label,cls='')=>`<a class="btn ${cls}" href="#${href}">${label} <i></i></a>`;
const label=t=>`<span class="label">${t}</span>`;
function frame(k,alt,{ratio,px,pal}={}){
  const [w,h]=DIM(k);const ar=ratio||`${w}/${h}`;
  return `<div class="frame" data-k="${k}"${pal||PAL_PAGE?' data-pal="1"':''}${px?` data-px="${px}"`:''} style="aspect-ratio:${ar}"><img src="${I(k)}" alt="${esc(alt)}" loading="lazy" width="${w}" height="${h}"></div>`;
}
function projCard(p,cls,land,withSvc){
  const loc=[p.location,p.year].filter(Boolean).join(' · ')||'Location to confirm';
  return `<a class="card ${land?'land ':''}${cls}" href="#work-${p.slug}">
    ${frame(land?p.coverL:p.coverV,p.name,{ratio:land?'3/2':'4/5'})}
    <div class="row"><div><h3>${esc(p.name)}</h3><div class="meta">${esc(loc)}</div>${withSvc?`<div class="meta svc">${esc((p.services||[]).join(', '))}</div>`:''}</div><span class="arrow" aria-hidden="true">↗</span></div></a>`;
}
const intro=(lab,line)=>`<div class="intro"><div class="l">${label(esc(lab))}${line?`<p>${esc(line)}</p>`:''}</div></div>`;
const paras=t=>String(t||'').split(/\n\s*\n/).filter(Boolean).map(x=>`<p>${esc(x.trim())}</p>`).join('');
function cta(project){
  return `<section class="cta" id="enquire" data-field="prefooter">
    <img class="bg" src="${I(C.img.prefooter)}" alt="" loading="lazy">
    <div class="scrim"></div>
    <div class="copy"><h2>${esc(project?C.cta.projectH2:C.cta.h2)}</h2>${project?'':`<p class="lead">${esc(C.cta.lead)}</p>`}
      ${btn(project?'contact-'+project:'contact',esc(C.cta.button),'light')}<p class="meta" style="color:inherit;opacity:.85">${esc(C.available)}</p></div></section>`;
}
function logoGrid(){
  return `<div class="logos">${LOGOS.map(([f,n,slug,w,h])=>{const s=`<img src="${LOGO(f)}" alt="" style="--w:${w}%;--h:${h}%" loading="lazy">`;
    return slug&&byslug(slug)?`<a class="logo" href="#work-${slug}" title="${esc(n)}" aria-label="${esc(n)}, view project">${s}</a>`:`<div class="logo" role="img" title="${esc(n)}" aria-label="${esc(n)}">${s}</div>`}).join('')}</div>`;
}
function filmBtn(f,i,big){
  const meta=esc(f.meta||[f.client!==f.title?f.client:'',f.loc].filter(Boolean).join(' · ')||f.cat);
  if(f.project&&byslug(f.project))return `<a class="film linked${big?' big':''}" href="#work-${f.project}"><div class="frame" style="aspect-ratio:16/9"><img src="${I(f.k)}" alt="" loading="lazy"><span class="go" aria-hidden="true">↗</span></div>
    <div class="row"><div><h3>${esc(f.title)}</h3><div class="meta">${meta}</div></div><span class="vp">View project</span></div></a>`;
  return `<button class="film${big?' big':''}" data-film="${i}"><div class="frame" style="aspect-ratio:16/9"><img src="${I(f.k)}" alt="" loading="lazy"><span class="play" aria-hidden="true"></span></div>
    <div class="row"><div><h3>${esc(f.title)}</h3><div class="meta">${meta}</div></div><span class="vp">Play film</span></div></button>`;
}

function hrail(lab,cards,title){
  return `<section class="hscroll"><div class="hs-sticky"><div class="wrap hs-head">${label(esc(lab))}${title?`<h2>${esc(title)}</h2>`:''}<span class="hs-count meta"><b>01</b> / ${nf(cards.length)}</span></div>
    <div class="hs-view"><div class="hs-track">${cards.join('')}</div></div>
    <div class="wrap"><div class="hs-bar"><i></i></div></div></div></section>`;
}
let PAL_PAGE=false; // set while a page that shows palettes everywhere (home, services) is rendered
const PAGES={};

PAGES.home=()=>{
  const W=HOME_WORK.map(byslug).filter(Boolean).slice(0,6);const cls=['c1','c2','c3','c4','c5','c6'];const H=C.home;
  return {hero:true,filmList:HOME_FILMS,lightbox:HOME_PHOTOS.map(([k,c])=>({k,cap:c})),html:`
  <section class="hero" id="hero" data-field="hero">
    <picture><source media="(max-width:760px) and (orientation:portrait)" srcset="${I(C.img.heroM)}"><img src="${I(C.img.hero)}" alt="${esc(H.heroAlt)}" fetchpriority="high"></picture>
    <div class="scrim"></div>
    <div class="copy"><p class="display" aria-hidden="true">${esc(H.display)}</p>
      <h1>${esc(H.h1)}</h1>
      <p class="sub">${esc(H.sub)}</p>${btn('work',esc(H.button),'light')}</div>
    <span class="cue" aria-hidden="true"><i></i></span>
  </section>
  <section class="sec"><div class="wrap">${intro(H.workLabel,H.workLine)}
    <div class="grid work">${W.map((p,i)=>projCard(p,cls[i],i%2===1)).join('')}</div>
    <div class="more">${btn('work','View all work')}</div></div></section>
  <section class="sec photo"><div class="wrap">${intro(H.photoLabel,H.photoLine)}
    <div class="grid gal">${HOME_PHOTOS.slice(0,7).map(([k,c],i)=>`<figure class="g${i+1}"><button data-lb="${i}" aria-label="Open photograph ${i+1}">${frame(k,c,{ratio:'2/3'})}</button><figcaption class="meta">${esc(c)}</figcaption></figure>`).join('')}</div>
    <div class="more">${btn('photography','View photography')}</div></div></section>
  <section class="sec"><div class="wrap">${intro(H.trustedLabel,H.trustedLine)}${logoGrid()}</div></section>
  <section class="sec photo"><div class="wrap">${intro(H.filmLabel,H.filmLine)}
    <div class="grid films">${HOME_FILMS.slice(0,3).map((f,i)=>`<div class="f${i+1}">${filmBtn(f,i,i===0)}</div>`).join('')}</div>
    <div class="more">${btn('film','View film')}</div></div></section>
  <section class="sec studio"><div class="wrap grid">
    <div class="lab">${label(esc(H.studioLabel))}</div>
    <div class="txt"><p class="lead">${esc(H.studioLead)}</p>${paras(H.studioText)}
      <p class="meta">${esc(C.available)}</p><div>${btn('about','About '+esc(C.brand))}</div></div>
    <div class="img">${frame(C.img.studio,H.studioAlt,{ratio:'2/3'})}</div></div></section>
  <section class="sec" style="padding-top:0"><div class="wrap"><div class="intro"><div class="l">${label(esc(H.servicesLabel))}</div></div>
    <ul class="svc">${SERVICES.map(s=>`<li><a href="#services-${s.id}" data-img="${I(s.img)}"><span class="dot" data-k="${s.img}"></span><h3>${esc(s.name)}</h3><p>${esc(s.short)}</p><span class="arr" aria-hidden="true">→</span></a></li>`).join('')}</ul>
    <div class="more">${btn('services','Explore services')}</div></div></section>
  ${cta()}`};
};

PAGES.work=()=>{
  const cls=['w-a','w-b','w-c','w-d','w-e'];const land=[true,false,false,true,true];
  return {html:`
  <section class="pintro"><div class="wrap"><h1>${esc(C.pages.workTitle)}</h1><p>${esc(C.pages.workIntro)}</p><span class="count">${nf(PROJECTS.length)} projects</span></div></section>
  <section style="padding-bottom:clamp(88px,12vw,180px)"><div class="wrap"><div class="grid wgrid">
    ${PROJECTS.map((p,i)=>projCard(p,cls[i%5],land[i%5],true)).join('')}
  </div></div></section>${cta()}`};
};

PAGES.project=slug=>{
  const p=byslug(slug);if(!p)return PAGES.notfound();
  const i=PROJECTS.indexOf(p),next=PROJECTS[(i+1)%PROJECTS.length];
  const tbc=v=>v?`<span class="v">${esc(v)}</span>`:`<span class="v tbc">To confirm</span>`;
  let vids=[];
  const vid=(k,v)=>{vids.push({k,video:v||'',title:p.name,meta:p.client});return `<button class="vid" data-film="${vids.length-1}">${frame(k,'',{ratio:'16/9'})}<span class="play" aria-hidden="true"></span></button>`};
  const blocks=p.blocks||[];
  const palAt=new Set([0,Math.floor(blocks.length/2)]);
  const b=blocks.map((bl,bi)=>{const pal=palAt.has(bi);const K=[].concat(bl.k);
    switch(bl.t){
      case 'large':return `<div class="blk blk-large ${bl.side||''}">${frame(K[0],p.name,{pal})}</div>`;
      case 'pair':return `<div class="blk blk-pair">${K.slice(0,2).map((k,j)=>frame(k,p.name,{pal:pal&&j===0})).join('')}</div>`;
      case 'full':return `<div class="blk blk-full">${frame(K[0],p.name,{ratio:'16/9',pal})}</div>`;
      case 'drone':return `<div class="blk blk-drone">${frame(K[0],p.name+', aerial',{ratio:'16/9',pal})}</div>`;
      case 'mixed':return `<div class="blk blk-mixed">${frame(K[0],p.name,{pal})}${bl.video?vid(K[1],bl.src):frame(K[1],p.name)}</div>`;
      case 'video':return `<div class="blk blk-video">${vid(K[0],bl.src)}</div>`;
      case 'text':return `<div class="blk blk-text">${paras(bl.text)}</div>`;
    }return ''}).join('');
  return {hero:true,filmList:vids,html:`
  <section class="phero" data-field="phero">
    <picture><source media="(max-width:760px) and (orientation:portrait)" srcset="${I(p.heroM||p.coverV)}"><img src="${I(p.hero||p.coverL)}" alt="${esc(p.name)}"></picture>
    <div class="scrim"></div>
    <div class="copy"><h1>${esc(p.name)}</h1><span class="loc">${esc(p.location||'Location to confirm')}</span></div>
  </section>
  <div class="wrap">
    <div class="metab"><div>${label('Client')}${tbc(p.client)}</div><div>${label('Location')}${tbc(p.location)}</div><div>${label('Year')}${tbc(p.year)}</div><div>${label('Services')}${tbc((p.services||[]).join(', '))}</div></div>
    ${p.desc?`<div class="pdesc">${paras(p.desc)}</div>`:''}
    <div class="blocks">${b}</div>
    ${p.logo?`<div class="clogo"><span class="meta">Client</span><img class="m" src="${LOGO(p.logo)}" alt="${esc(p.client)}"></div>`:''}
  </div>
  <section class="band pcta"><h2>${esc(C.cta.projectH2)}</h2>${btn('contact-'+p.slug,esc(C.cta.button))}<span class="meta">${esc(C.available)}</span></section>
  <a class="nextp" href="#work-${next.slug}"><img src="${I(next.coverL)}" alt="" loading="lazy"><div class="sc"></div>
    <div class="copy"><span class="label" style="color:inherit">Next project</span><h2>${esc(next.name)}</h2><span class="meta" style="color:inherit">${esc(next.location||'')}</span></div></a>`,next:next.coverL};
};

function layoutPhotos(list){
  // editorial rhythm: verticals large and offset, landscapes across; never a uniform grid
  const pat=[['1/7',0],['8/12','12vw'],['3/8','-2vw'],['9/13','6vw'],['1/5','8vw'],['6/12',0]];
  const patL=['1/13','2/12'];
  let v=0,l=0;
  return list.map((ph,i)=>{const [w,h]=DIM(ph.k);const landscape=w>h*1.15;let col,mt=0;
    if(landscape){col=patL[l++%2]}else{[col,mt]=pat[v++%pat.length]}
    const pr=ph.project?byslug(ph.project):null;
    const cap=photoCap(ph);
    if(pr)return `<figure style="grid-column:${col};margin-top:${mt}"><a class="plink" href="#work-${pr.slug}" aria-label="${esc(pr.name)}, view project">${frame(ph.k,cap)}<figcaption class="meta"><span>${esc(cap)}</span><span class="vp">View project</span></figcaption></a></figure>`;
    return `<figure style="grid-column:${col};margin-top:${mt}"><button data-lb="${i}" aria-label="Open photograph">${frame(ph.k,cap)}</button><figcaption class="meta">${esc(cap)}</figcaption></figure>`}).join('');
}
function photoCap(ph){const pr=ph.project?byslug(ph.project):null;const c=CATS.find(c=>c[0]===(ph.cats||[])[0]);return [ph.loc,pr?pr.name:''].filter(Boolean).join(' / ')||(c?c[1]:'')}
PAGES.photography=cat=>{
  const valid=CATS.find(c=>c[0]===cat);const list=valid?PHOTOS.filter(p=>(p.cats||[]).includes(cat)):PHOTOS;
  const count=c=>PHOTOS.filter(p=>(p.cats||[]).includes(c)).length;
  return {lightbox:list.map(ph=>({k:ph.k,cap:photoCap(ph),project:ph.project})),html:`
  <section class="pintro"><div class="wrap"><h1>${esc(C.pages.photoTitle)}</h1><p>${esc(C.pages.photoIntro)}</p></div></section>
  <nav class="filters" aria-label="Photography categories"><div class="wrap">
    <a class="chip${valid?'':' on'}" href="#photography">All <span class="n">${PHOTOS.length}</span></a>
    ${CATS.map(([id,n])=>`<a class="chip${cat===id?' on':''}" href="#photography-${id}">${esc(n)} <span class="n">${count(id)}</span></a>`).join('')}
  </div></nav>
  <section><div class="wrap"><div class="grid pgrid">${layoutPhotos(list)}</div></div></section>${cta()}`};
};

PAGES.film=()=>{
  const groups={};FILMS.forEach(f=>(groups[f.cat]=groups[f.cat]||[]).push(f));
  const big=[],rest=[];Object.entries(groups).forEach(([c,l])=>l.length>=2?big.push([c,l]):rest.push(...l));
  let idx=0;const all=[];
  const cards=l=>l.map(f=>{all.push(f);return filmBtn(f,idx++)}).join('');
  const html=big.map(([c,l])=>`<section class="fgroup">${label(esc(c))}<div class="fcards">${cards(l)}</div></section>`).join('')+
    (rest.length?`<section class="fgroup">${label('More films')}<div class="fcards">${cards(rest)}</div></section>`:'');
  return {filmList:all,html:`
  <section class="pintro"><div class="wrap"><h1>${esc(C.pages.filmTitle)}</h1><p>${esc(C.pages.filmIntro)}</p></div></section>
  <div class="wrap" style="padding-bottom:clamp(88px,12vw,180px)">${html}</div>${cta()}`};
};

PAGES.services=anchor=>({anchor,html:`
  <section class="pintro"><div class="wrap"><h1>${esc(C.pages.servicesTitle)}</h1><p>${esc(C.pages.servicesIntro)}</p></div></section>
  <div class="wrap">${SERVICES.map((s,i)=>`<section class="split${i%2?' flip':''}" id="svc-${s.id}">
    <div class="media">${frame(s.img,s.name,{ratio:'4/5'})}</div>
    <div class="t"><h2>${esc(s.name)}</h2>${paras(s.body)}${s.deliv?`<div class="deliv"><span class="meta">Typical deliverables</span><span>${esc(s.deliv)}</span></div>`:''}</div></section>`).join('')}</div>
  <section class="band"><p>${esc(C.pages.servicesBand)}</p>${btn('contact','Start a project')}</section>`});

PAGES.about=()=>{const A=C.about;const films=[];
  const disc=(A.disc||[]).slice(0,3).map((d,i)=>{const cls='dcard d'+(i+1),ratio=['4/5','16/10','1/1'][i];
    const inner=`${frame(d.img,d.label+(d.meta?', '+d.meta:''),{ratio})}${d.film?'<span class="play" aria-hidden="true"></span>':''}<div class="dcap"><span class="label">${esc(d.label)}</span><span class="meta">${esc(d.meta||'')}</span></div>`;
    if(d.film){films.push({k:d.img,video:d.film,title:d.meta||d.label,meta:''});return `<button class="${cls} vid" data-film="${films.length-1}">${inner}</button>`}
    return d.project&&byslug(d.project)?`<a class="${cls}" href="#work-${d.project}">${inner}</a>`:`<div class="${cls}">${inner}</div>`}).join('');
  return {filmList:films,html:`
  <section class="about-open"><div class="wrap grid">
    <div class="t"><h1>${esc(A.title)}</h1>${paras(A.intro)}</div>
    <div class="i">${frame(C.img.aboutOpen,A.openAlt,{ratio:'3/4'})}</div></div></section>
  <section class="txtblock"><div class="wrap grid"><div class="l">${label(esc(A.approachTitle))}</div><div class="r"><h2>${esc(A.approachTitle)}</h2>${paras(A.approach)}</div></div></section>
  <section class="txtblock" style="padding-top:0;padding-bottom:0"><div class="wrap"><div class="grid"><div class="l">${label(esc(A.productionTitle))}</div><div class="r"><h2>${esc(A.productionTitle)}</h2>${paras(A.production)}</div></div></div></section>
  <section class="disc"><div class="wrap"><div class="grid disc-g">${disc}</div></div></section>
  ${hrail(A.stepsTitle,(A.steps||[]).map((s,i)=>`<article class="hs-card step">${frame(s.img,s.t,{ratio:'4/5'})}<div class="hs-cap"><span class="num">${nf(i+1)}</span><h3>${esc(s.t)}</h3><p>${esc(s.d)}</p></div></article>`),A.stepsTitle)}
  <section class="world" data-field="world"><img src="${I(C.img.world)}" alt="" loading="lazy"><div class="sc"></div>
    <div class="copy"><h2>${esc(A.worldTitle)}</h2>${paras(A.worldText)}
      <div class="world-cta"><span class="lead">${esc(A.worldCta)}</span>${btn('contact',esc(C.cta.button),'light')}</div></div></section>`};
};

PAGES.contact=project=>{
  const p=project?byslug(project):null;const K=C.contact;
  const types=K.types&&K.types.length?K.types:['Photography','Film','Photography + Film','Creative Direction','Full Production'];
  const F=(id,lab,{type='text',req=true,ph='',w=false}={})=>`<div class="field${w?' w':''}"><label for="${id}">${lab}${req?'':' <span class="opt">(optional)</span>'}</label>
    <input id="${id}" name="${id}" type="${type}" ${req?'required':''} placeholder="${esc(ph)}" autocomplete="${type==='email'?'email':id==='f-name'?'name':id==='f-company'?'organization':'off'}"><span class="err" aria-live="polite"></span></div>`;
  return {html:`
  <section class="contact"><div class="wrap grid">
    <div class="l"><h1>${esc(K.title)}</h1>
      <p class="intro-t">${esc(K.intro)}</p>
      <form class="form" id="enq" novalidate>
        ${F('f-name','Name')}${F('f-company','Company / Brand')}
        ${F('f-email','Email',{type:'email'})}${F('f-web','Website / Instagram',{req:false,ph:'e.g. www.yourhotel.com or @yourhotel'})}
        ${F('f-loc','Project location',{ph:'City, country'})}${F('f-dates','Preferred dates',{req:false,ph:'e.g. March 2027, flexible'})}
        <div class="field w"><label for="f-type">Project type</label><select id="f-type" name="f-type" required><option value="">Select</option>${types.map(t=>`<option>${esc(t)}</option>`).join('')}</select><span class="err" aria-live="polite"></span></div>
        <div class="field w"><label for="f-details">Project details</label><textarea id="f-details" name="f-details" required placeholder="Tell us about the property, the goal and what you have in mind."></textarea><span class="err" aria-live="polite"></span></div>
        <input type="hidden" name="source" value="${esc(p?'project:'+p.slug:'contact')}">
        <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
        <label class="consent"><input type="checkbox" id="f-consent" required><span>I agree to ${esc(C.brand)} processing my details to respond to this enquiry. <a href="#privacy">Privacy Policy</a></span></label>
        <div class="field w" style="margin-top:-18px"><span class="err" id="consentErr" aria-live="polite"></span></div>
        <div class="actions"><button class="btn" type="submit">Send enquiry <i></i></button>${p?`<span class="meta">About: ${esc(p.name)}</span>`:''}</div>
      </form>
      <div class="direct"><span class="label">Direct contact</span>
        ${K.email?`<span>Email: <span style="user-select:all">${esc(K.email)}</span></span>`:''}
        ${K.instagram?`<a href="${esc(K.instagram)}" target="_blank" rel="noopener">Instagram ↗</a>`:''}
        ${K.linkedin?`<a href="${esc(K.linkedin)}" target="_blank" rel="noopener">LinkedIn ↗</a>`:''}
        <span class="meta">${esc(C.available)}</span></div>
    </div>
    <div class="r">${frame(C.img.contact,K.imgAlt,{ratio:'4/5'})}</div>
  </div></section><div style="height:clamp(80px,10vw,160px)"></div>`};
};

PAGES.privacy=()=>({html:`<section class="legal-p"><div class="wrap"><h1>Privacy &amp; Cookies</h1><div class="body">
  ${C.privacy&&C.privacy.trim()?paras(C.privacy):`<p>The legal text is being prepared. It will cover the points below.</p>
  ${['Who operates this website','What the enquiry form collects and why','How long data is kept','Your rights as a visitor','The analytics tool and its cookies','How to change your cookie choice','Contact for data requests']
   .map(t=>`<h2>${t}</h2><p>To be supplied.</p>`).join('')}`}
  <div style="margin-top:24px"><button class="btn" id="cookieOpen" type="button">Cookie settings <i></i></button></div></div></div></section>${cta()}`});

PAGES.notfound=()=>({hero:true,status:404,html:`<section class="nf" data-field="nf"><picture><img src="${I(C.img.notfound)}" alt=""></picture><div class="scrim"></div>
  <div class="copy"><span class="label" style="color:inherit">404</span><h1>${esc(C.notfound.h1)}</h1><p style="margin:0">${esc(C.notfound.p)}</p>
  <div class="b">${btn('work','View our work','light')}${btn('','Back to home','light')}</div></div></section>`});

/* ============================================================
   ROUTER
   ============================================================ */
/* Two addressing modes, one set of route tokens ('work-palau', 'photography-drone', ...):
   hash (#work-palau) for the prototype and the artifact, real paths (/work/palau) on the live server. */
const PATH_MODE=!!(SITE_DATA&&SITE_DATA.routing==='path');
function tokenToPath(t){t=(t||'').replace(/^#/,'');if(!t||t==='top')return '/';
  for(const [a,b] of [['work-','/work/'],['photography-','/photography/'],['services-','/services/'],['contact-','/contact?project=']])if(t.startsWith(a))return b+encodeURIComponent(t.slice(a.length));
  return '/'+t}
function pathToToken(){const p=location.pathname.replace(/\/+$/,'')||'/',q=new URLSearchParams(location.search);if(p==='/')return '';
  const seg=p.slice(1).split('/').map(decodeURIComponent);
  if(seg[0]==='contact'&&seg.length===1&&q.get('project'))return 'contact-'+q.get('project');
  if(seg.length===2&&['work','photography','services'].includes(seg[0]))return seg[0]+'-'+seg[1];
  return seg.length===1?seg[0]:'__none'}
const curToken=()=>PATH_MODE?pathToToken():location.hash.slice(1);
const isRoute=t=>{t=(t||'').replace(/^#/,'');return t===''||t==='top'||parse(t)[0]!=='notfound'};
function pathLinks(root){if(!PATH_MODE)return;$$('a[href^="#"]',root).forEach(a=>{const t=a.getAttribute('href').slice(1);if(isRoute(t))a.setAttribute('href',tokenToPath(t))})}
function parse(h){
  h=(h||'').replace(/^#/,'');
  if(!h||h==='top')return ['home'];
  if(h==='work')return ['work'];
  if(h.startsWith('work-'))return ['project',h.slice(5)];
  if(h==='photography')return ['photography'];
  if(h.startsWith('photography-'))return ['photography',h.slice(12)];
  if(h==='film')return ['film'];
  if(h==='services')return ['services'];
  if(h.startsWith('services-'))return ['services',h.slice(9)];
  if(h==='about')return ['about'];
  if(h==='contact')return ['contact'];
  if(h.startsWith('contact-'))return ['contact',h.slice(8)];
  if(h==='privacy')return ['privacy'];
  return ['notfound'];
}
const app=$('#app');let cleanups=[],current=null,filmList=[],lbList=[];
const navKey={home:null,work:'work',project:'work',photography:'photography',film:'film',services:'services',about:'about',contact:'contact'};
const TITLES={home:C.brand,work:'Work',photography:'Photography',film:'Film',services:'Services',about:'About',contact:'Contact',privacy:'Privacy & Cookies',notfound:'Page not found'};

async function go(first){
  const [name,arg]=parse(curToken());
  const key=name+(arg||'');
  if(key===current)return;
  const soft=current&&current.startsWith('photography')&&name==='photography';
  const sameAnchor=current&&current.startsWith('services')&&name==='services';
  current=key;
  PAL_PAGE=(name==='home'||name==='services');
  const view=(PAGES[name]||PAGES.notfound)(arg);
  PAL_PAGE=false;
  const animate=!first&&!RM&&!sameAnchor;
  const keepY=soft?Math.min(scrollY,($('.filters')?.offsetTop||0)):0;
  if(animate){
    if(soft){const g=$('.pgrid');if(g){g.classList.add('leave');await wait(260)}}
    else{app.classList.add('leave');await wait(420)}
  }
  cleanups.forEach(f=>{try{f()}catch(e){}});cleanups=[];
  app.innerHTML=`<div class="page">${view.html}</div>`;
  document.title=(name==='project'&&byslug(arg)?byslug(arg).name+' | ':TITLES[name]&&name!=='home'?TITLES[name]+' | ':'')+C.brand;
  pathLinks(app);
  filmList=view.filmList||[];lbList=view.lightbox||[];
  const navHref=PATH_MODE?tokenToPath(navKey[name]||''):'#'+navKey[name];
  $$('.nav a.link').forEach(a=>a.classList.toggle('on',!!navKey[name]&&a.getAttribute('href')===navHref));
  if(view.anchor){const t=$('#svc-'+view.anchor);if(t)requestAnimationFrame(()=>scrollToY(t.getBoundingClientRect().top+scrollY-60,first))}
  else if(!sameAnchor)scrollToY(soft?keepY:0,true);
  if(animate){
    if(soft){const g=$('.pgrid');g&&g.classList.add('enter');requestAnimationFrame(()=>requestAnimationFrame(()=>g&&g.classList.remove('enter')))}
    else{app.classList.remove('leave');app.classList.add('enter');requestAnimationFrame(()=>requestAnimationFrame(()=>{app.classList.remove('enter');setTimeout(()=>app.classList.add('settled'),700)}));app.classList.remove('settled')}
  }
  hydrate(first);onScroll();
}
const wait=ms=>new Promise(r=>setTimeout(r,ms));
if(PATH_MODE){
  // internal links change the address without a reload; everything else (assets, admin, other sites) behaves normally
  document.addEventListener('click',e=>{const a=e.target.closest&&e.target.closest('a[href]');if(!a||e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||a.target==='_blank')return;
    const u=new URL(a.href,location.href);if(u.origin!==location.origin||/^\/(admin|api|assets|uploads)\b/.test(u.pathname)||/\.[a-z0-9]{2,5}$/i.test(u.pathname))return;
    e.preventDefault();if(u.pathname+u.search!==location.pathname+location.search){history.pushState(null,'',u.pathname+u.search);go(false)}});
  addEventListener('popstate',()=>go(false));
  if(location.hash&&isRoute(location.hash))history.replaceState(null,'',tokenToPath(location.hash));   // old #links keep working
  pathLinks(document);
}else addEventListener('hashchange',()=>go(false));

/* smooth, weighted scrolling */
let lenis=null;
if(window.Lenis&&!RM){lenis=new Lenis({duration:1.15,easing:t=>1-Math.pow(1-t,3.2),smoothWheel:true});
  const raf=t=>{lenis.raf(t);requestAnimationFrame(raf)};requestAnimationFrame(raf)}
function scrollToY(y,instant){if(lenis)lenis.scrollTo(y,{immediate:!!instant,force:true});else scrollTo({top:y,behavior:instant?'instant':'smooth'})}
function lockScroll(on){document.documentElement.style.overflow=on?'hidden':'';if(lenis)on?lenis.stop():lenis.start()}

/* ============================================================
   HYDRATE: wire the page that was just mounted
   ============================================================ */
function hydrate(first){
  // images arrive out of focus and settle; then their palette squares step in
  const frames=$$('.frame',app);
  const settle=f=>{f.classList.add('in');const img=f.querySelector('img');if(f.closest('.nextp,.world')||!f.dataset.pal)return;
    loaded(img).then(im=>{if(im&&f.isConnected)setTimeout(()=>spots(f,im),650)})};
  if('IntersectionObserver' in window&&!RM){const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){io.unobserve(e.target);settle(e.target)}}),{rootMargin:'0px 0px -8% 0px'});
    frames.forEach(f=>io.observe(f));cleanups.push(()=>io.disconnect())}else frames.forEach(settle);

  // headings and lead lines rise out of a blur, word by word
  const texts=$$('.display,.hero h1,.pintro h1,.pintro p,.intro p,.phero h1,.cta h2,.world h2,.about-open h1,.contact h1,.txtblock h2,.split h2,.nf h1,.band p,.band h2,.hs-head h2',app);
  texts.forEach(splitWords);
  const tio=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){tio.unobserve(e.target);e.target.classList.add('in')}}),{rootMargin:'0px 0px -6% 0px'});
  texts.forEach(t=>{if(t.closest('.hero,.phero,.pintro,.nf,.about-open,.contact'))requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('in')));else tio.observe(t)});
  cleanups.push(()=>tio.disconnect());
  setTimeout(()=>texts.forEach(t=>t.classList.add('in')),4000); // never leave copy hidden

  // hero: first frame comes in out of focus; scrolling away blurs the copy again
  const hero=$('.hero,.phero',app);
  if(hero){if(!RM){hero.classList.add('boot');setTimeout(()=>hero.classList.remove('boot'),first?250:80)}
    const copy=hero.querySelector('.copy');let tk=false;
    const hs=()=>{tk=false;const y=Math.max(0,-hero.getBoundingClientRect().top),k=Math.min(1,y/(hero.offsetHeight*.6));
      copy.style.filter=k>0?`blur(${(k*10).toFixed(1)}px)`:'';copy.style.opacity=(1-k*.9).toFixed(3);copy.style.transform=`translateY(${(-k*40).toFixed(1)}px)`};
    const on=()=>{if(!tk){tk=true;requestAnimationFrame(hs)}};addEventListener('scroll',on,{passive:true});cleanups.push(()=>removeEventListener('scroll',on))}

  // WebGL fields: pixels only where the cursor is
  const FIELDS={hero:{cols:26,colsM:12,density:0,box:[.24,.22,.76,.78],focus:[.5,.45],dark:.34},
    phero:{cols:24,colsM:10,density:0,box:[0,.62,.7,1],dark:.1},
    prefooter:{cols:28,colsM:12,density:0,box:[0,0,0,0],dark:.22,edge:0},
    world:{cols:28,colsM:12,density:0,box:[0,0,0,0],dark:.5,edge:0},
    nf:{cols:24,colsM:10,density:0,box:[0,0,0,0],dark:.45}};
  $$('[data-field]',app).forEach(host=>{const o=FIELDS[host.dataset.field];const img=host.querySelector('picture img, img');
    const start=()=>loaded(img).then(im=>{if(!im||!host.isConnected)return;const d=pixelField(host,im,o);if(d)cleanups.push(d);
      if(host.dataset.field==='hero')heroLoop(host,d)});
    const ob=new IntersectionObserver(es=>{if(es[0].isIntersecting){ob.disconnect();start()}},{rootMargin:'300px'});ob.observe(host);cleanups.push(()=>ob.disconnect())});

  // services list: marker takes a colour from its photograph; image trails the cursor, softly
  const flt=$('#float'),fimg=$('#floatImg');let fx=0,fy=0,tx=0,ty=0,fr=0;
  const follow=()=>{fx+=(tx-fx)*.14;fy+=(ty-fy)*.14;flt.style.transform=`translate(${fx.toFixed(1)}px,${fy.toFixed(1)}px) rotate(${((tx-fx)*.02).toFixed(2)}deg)`;fr=requestAnimationFrame(follow)};
  $$('.svc a',app).forEach(a=>{const pre=new Image();pre.src=a.dataset.img;
    loaded(pre).then(im=>{if(!im)return;const p=palette(im,6);a.querySelector('.dot').style.background=p[2]||''});
    a.addEventListener('pointerenter',e=>{if(e.pointerType!=='mouse')return;fimg.src=a.dataset.img;tx=fx=e.clientX+28;ty=fy=e.clientY-160;flt.classList.add('on');cancelAnimationFrame(fr);fr=requestAnimationFrame(follow)});
    a.addEventListener('pointerleave',()=>{flt.classList.remove('on');setTimeout(()=>cancelAnimationFrame(fr),400)});
    a.addEventListener('pointermove',e=>{tx=e.clientX+28;ty=e.clientY-160});
    a.addEventListener('click',()=>flt.classList.remove('on'))});
  cleanups.push(()=>{cancelAnimationFrame(fr);flt.classList.remove('on')});

  // horizontal rails driven by vertical scroll
  $$('.hscroll',app).forEach(sec=>{const d=hRail(sec);if(d)cleanups.push(d)});

  // lightbox + film triggers
  $$('[data-lb]',app).forEach(b=>b.addEventListener('click',()=>openLB(+b.dataset.lb,b)));
  $$('[data-film]',app).forEach(b=>b.addEventListener('click',e=>{if(e.target.closest('[data-stop]'))return;openFilm(+b.dataset.film,b)}));

  rollLinks($$('.nav a.link,.fcols a,.legal a,.direct a',document));

  const form=$('#enq',app);if(form)wireForm(form);
  const co=$('#cookieOpen',app);if(co)co.onclick=()=>{ck.hidden=false;$('#ckAccept').focus()};
}

/* hero loop: the [SMALL] master from Drive, muted and looping; drives the WebGL field when there is one */
function heroLoop(host,field){
  if(RM)return;const portrait=innerWidth<760&&innerHeight>innerWidth;const url=(portrait?C.video.heroM||C.video.hero:C.video.hero)||videoURL(portrait?'hero-m':'hero');if(!url)return;
  const v=document.createElement('video');Object.assign(v,{muted:true,loop:true,playsInline:true,autoplay:true,preload:'auto'});
  v.setAttribute('muted','');v.setAttribute('playsinline','');v.crossOrigin='anonymous';v.className='hero-vid';v.src=url;
  host.querySelector('picture').appendChild(v);
  v.addEventListener('playing',()=>{if(!host.isConnected)return;if(field&&field.useVideo){try{field.useVideo(v)}catch(e){v.classList.add('on')}}else v.classList.add('on')},{once:true});
  v.addEventListener('error',()=>v.remove(),{once:true});
  v.play().catch(()=>{});
  cleanups.push(()=>{v.pause();v.removeAttribute('src');v.load();v.remove()});
}

/* words wrapped once, so each can rise out of a blur on its own delay */
function splitWords(el){
  if(el.dataset.split)return;el.dataset.split=1;let n=0;
  const walk=node=>{[...node.childNodes].forEach(c=>{
    if(c.nodeType===3){const parts=c.textContent.split(/(\s+)/);const frag=document.createDocumentFragment();
      parts.forEach(p=>{if(!p)return;if(/^\s+$/.test(p)){frag.appendChild(document.createTextNode(p));return}
        const w=document.createElement('span');w.className='w';const i=document.createElement('span');i.textContent=p;i.style.transitionDelay=(n++*45)+'ms';w.appendChild(i);frag.appendChild(w)});
      c.replaceWith(frag)}
    else if(c.nodeType===1&&c.tagName!=='BR')walk(c)})};
  walk(el);el.classList.add('wsplit');
}

/* a horizontal rail pinned in place while the page scrolls vertically */
function hRail(sec){
  const track=sec.querySelector('.hs-track'),bar=sec.querySelector('.hs-bar i'),count=sec.querySelector('.hs-count b');
  const items=track.children.length;let dist=0;
  const size=()=>{dist=Math.max(0,track.scrollWidth-sec.querySelector('.hs-sticky').clientWidth);sec.style.height=(innerHeight+dist*1.1)+'px'};
  let tk=false;
  const upd=()=>{tk=false;const r=sec.getBoundingClientRect(),span=sec.offsetHeight-innerHeight;const p=span>0?Math.min(1,Math.max(0,-r.top/span)):0;
    track.style.transform=`translate3d(${(-p*dist).toFixed(1)}px,0,0)`;if(bar)bar.style.transform=`scaleX(${Math.max(.02,p)})`;
    if(count)count.textContent=nf(Math.min(items,1+Math.round(p*(items-1))))};
  const on=()=>{if(!tk){tk=true;requestAnimationFrame(upd)}};
  size();upd();addEventListener('scroll',on,{passive:true});addEventListener('resize',size);
  const ro=new ResizeObserver(()=>{size();upd()});ro.observe(track);
  return ()=>{removeEventListener('scroll',on);removeEventListener('resize',size);ro.disconnect()};
}

/* buttons lean toward the pointer */
function magnetic(els){els.forEach(el=>{if(el.dataset.mag)return;el.dataset.mag=1;
  el.addEventListener('pointermove',e=>{if(e.pointerType!=='mouse')return;const r=el.getBoundingClientRect();const x=(e.clientX-r.left-r.width/2)/r.width,y=(e.clientY-r.top-r.height/2)/r.height;
    el.style.transform=`translate(${(x*10).toFixed(1)}px,${(y*8).toFixed(1)}px)`});
  el.addEventListener('pointerleave',()=>{el.style.transform=''})})}

/* link labels roll to a second copy on hover */
function rollLinks(els){els.forEach(a=>{if(a.dataset.roll||a.children.length)return;a.dataset.roll=1;const t=a.textContent;
  a.innerHTML=`<span class="roll"><span>${esc(t)}</span><span aria-hidden="true">${esc(t)}</span></span>`})}

/* ---------- form ---------- */
function wireForm(form){
  const check=el=>{const f=el.closest('.field');if(!f)return true;const e=f.querySelector('.err');let msg='';
    if(el.required&&!el.value.trim())msg='Please fill in this field.';
    else if(el.type==='email'&&el.value&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value))msg='Please enter a valid email address.';
    f.classList.toggle('bad',!!msg);e.textContent=msg;return !msg};
  $$('input,select,textarea',form).forEach(el=>el.addEventListener('blur',()=>{if(el.type!=='checkbox'&&el.type!=='hidden')check(el)}));
  form.addEventListener('submit',e=>{e.preventDefault();let ok=true,first=null;
    $$('input:not([type=hidden]):not([type=checkbox]),select,textarea',form).forEach(el=>{if(!check(el)){ok=false;first=first||el}});
    const c=$('#f-consent',form);$('#consentErr').textContent=c.checked?'':'Please confirm you agree so we can reply.';if(!c.checked){ok=false;first=first||c}
    if(!ok){first.focus();return}
    const done=(ok,msg)=>{form.innerHTML=`<div class="form-msg${ok?'':' bad'}" role="status"><div>${msg}</div></div>`};
    if(!(SITE_DATA&&SITE_DATA.api)){done(true,`<strong>Thank you.</strong> ${esc(C.contact.success)}<br><span class="meta">Prototype: nothing was sent. The live site posts this to the studio inbox.</span>`);return}
    const btnS=form.querySelector('button[type=submit]');btnS.disabled=true;btnS.style.opacity=.5;
    const fd=new FormData(form);fd.set('consent',$('#f-consent',form).checked?'1':'');fd.set('page',location.pathname+location.search);
    fetch(SITE_DATA.api,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}).then(r=>r.json().catch(()=>({ok:false}))).then(j=>{
      if(j&&j.ok)done(true,`<strong>Thank you.</strong> ${esc(C.contact.success)}`);
      else{btnS.disabled=false;btnS.style.opacity='';const er=form.querySelector('.form-err')||form.insertAdjacentElement('beforeend',Object.assign(document.createElement('p'),{className:'form-err'}));
        er.textContent=(j&&j.error)||('Something went wrong. Please try again or write to us at '+(C.contact.email||'')+'.')}
    }).catch(()=>{btnS.disabled=false;btnS.style.opacity='';done(false,'Something went wrong. Please try again or write to us at '+esc(C.contact.email||'')+'.')})});
}

/* ---------- lightbox ---------- */
const lb=$('#lb'),lbImg=$('#lbImg');let cur=0,lastFocus=null;
function show(i,instant){cur=(i+lbList.length)%lbList.length;const it=lbList[cur];
  const swap=()=>{lbImg.src=I(it.k);lbImg.alt=it.cap||'';$('#lbCap').textContent=it.cap||'';$('#lbCount').textContent=nf(cur+1)+' / '+nf(lbList.length);
    const pl=$('#lbProject');pl.hidden=!it.project;if(it.project)pl.href='#work-'+it.project;lbImg.classList.remove('swap')};
  if(instant||RM)swap();else{lbImg.classList.add('swap');setTimeout(swap,220)}}
function openLB(i,from){if(!lbList.length)return;lastFocus=document.activeElement;show(i,true);lb.hidden=false;lockScroll(true);
  const src=from&&from.querySelector('img');
  if(src&&!RM){requestAnimationFrame(()=>{const a=src.getBoundingClientRect(),b=lbImg.getBoundingClientRect();if(!b.width)return;
    const sc=a.width/b.width;lbImg.style.transition='none';lbImg.style.transform=`translate(${a.left+a.width/2-(b.left+b.width/2)}px,${a.top+a.height/2-(b.top+b.height/2)}px) scale(${sc})`;
    requestAnimationFrame(()=>{lbImg.style.transition='';lbImg.style.transform=''})})}
  requestAnimationFrame(()=>lb.classList.add('open'));$('#lbClose').focus()}
function closeLB(){lb.classList.remove('open');setTimeout(()=>{lb.hidden=true},RM?0:320);lockScroll(false);lastFocus&&lastFocus.focus()}
$('#lbPrev').onclick=()=>show(cur-1);$('#lbNext').onclick=()=>show(cur+1);$('#lbClose').onclick=closeLB;$('#lbProject').addEventListener('click',closeLB);
let sx=null;$('#lbStage').addEventListener('pointerdown',e=>{sx=e.clientX});
$('#lbStage').addEventListener('pointerup',e=>{if(sx==null)return;const d=e.clientX-sx;sx=null;if(Math.abs(d)>50)show(cur+(d<0?1:-1))});

/* ---------- film player shell ---------- */
const fm=$('#fm');
function openFilm(i,from){const f=filmList[i];if(!f)return;$('#fmImg').src=I(f.k);
  const v=$('#fmVid'),url=vsrc(f),has=!!url;v.hidden=!has;$('#fmImg').hidden=has;$('#fmPlay').hidden=has;$('#fmNote').hidden=has;
  if(has){v.poster=I(f.k);v.src=url;v.onerror=()=>{v.hidden=true;$('#fmImg').hidden=false;$('#fmPlay').hidden=false;$('#fmNote').hidden=false};v.play().catch(()=>{})}$('#fmTitle').textContent=[f.title,f.meta||f.client].filter(Boolean).join(' · ');
  lastFocus=from;fm.hidden=false;lockScroll(true);requestAnimationFrame(()=>fm.classList.add('open'));$('#fmClose').focus()}
function closeFM(){const v=$('#fmVid');v.pause();v.removeAttribute('src');v.load();fm.classList.remove('open');setTimeout(()=>{fm.hidden=true},RM?0:320);lockScroll(false);lastFocus&&lastFocus.focus()}
$('#fmClose').onclick=closeFM;

addEventListener('keydown',e=>{
  if(!lb.hidden){if(e.key==='Escape')closeLB();if(e.key==='ArrowRight')show(cur+1);if(e.key==='ArrowLeft')show(cur-1)}
  else if(!fm.hidden&&e.key==='Escape')closeFM();
  else if(!mnav.hidden&&e.key==='Escape')closeMenu();
});

/* ---------- header: transparent over a hero, hides on scroll down ---------- */
const hdr=$('#hdr');let lastY=scrollY;
function onScroll(){const y=scrollY;const hero=$('.hero,.phero,.nf',app);const h=hero?hero.offsetHeight-hdr.offsetHeight:0;
  hdr.classList.toggle('over',!!hero&&y<h);
  const hide=y>Math.max(h,120)&&y>lastY+4;if(hide)hdr.classList.add('hide');else if(y<lastY-4||y<h)hdr.classList.remove('hide');
  document.body.classList.toggle('hdr-hidden',hdr.classList.contains('hide'));lastY=y}
addEventListener('scroll',onScroll,{passive:true});

/* ---------- mobile menu ---------- */
const mnav=$('#mnav'),mb=$('#menuBtn');
function openMenu(){mnav.hidden=false;mb.setAttribute('aria-expanded','true');lockScroll(true);requestAnimationFrame(()=>mnav.classList.add('open'));$('#menuClose').focus()}
function closeMenu(){mnav.classList.remove('open');mb.setAttribute('aria-expanded','false');lockScroll(false);setTimeout(()=>{mnav.hidden=true},RM?0:360)}
mb.onclick=openMenu;$('#menuClose').onclick=()=>{closeMenu();mb.focus()};$$('#mnav a').forEach(a=>a.addEventListener('click',closeMenu));

/* ---------- cookie consent (remembered 6 months) ---------- */
const ck=$('#cookie'),KEY='cs-consent';
function getC(){try{const v=JSON.parse(localStorage.getItem(KEY));return v&&v.t>Date.now()-15552e6?v:null}catch(e){return null}}
function setC(a){try{localStorage.setItem(KEY,JSON.stringify({a,t:Date.now()}))}catch(e){}ck.hidden=true}
if(!getC())setTimeout(()=>{ck.hidden=false},RM?0:2600);
$('#ckAccept').onclick=()=>setC(true);$('#ckDecline').onclick=()=>setC(false);$('#cookieSettings').onclick=()=>{ck.hidden=false;$('#ckAccept').focus()};

/* ---------- square pointer ----------
   A small square; over anything clickable it turns 45 degrees and grows; over a pixel field it takes
   half the side of the cells the cursor paints. Position is written straight through (no easing) so it never lags. */
if(matchMedia('(pointer:fine)').matches&&!RM){
  document.documentElement.classList.add('sq-cursor');
  const sq=document.createElement('div');sq.className='sq';sq.innerHTML='<i></i>';document.body.appendChild(sq);
  const CLICK='a,button,[role="button"],label,select,summary,.chip,.pal';
  let x=-99,y=-99,pend=false,state='';
  const paint=()=>{pend=false;sq.style.transform=`translate3d(${x}px,${y}px,0)`};
  addEventListener('pointermove',e=>{if(e.pointerType!=='mouse')return;x=e.clientX;y=e.clientY;
    const t=e.target;let st='',size='';
    if(t.closest&&t.closest('input,textarea'))st='text';
    else if(t.closest&&t.closest(CLICK))st='click';
    else{const f=t.closest&&t.closest('[data-field].gl');if(f&&f.dataset.cell){st='field';size=(f.dataset.cell/2).toFixed(1)+'px'}}
    if(st!==state){sq.dataset.s=st;state=st}
    sq.style.setProperty('--fs',size||'');sq.classList.add('on');
    if(!pend){pend=true;requestAnimationFrame(paint)}},{passive:true});
  document.addEventListener('pointerleave',()=>sq.classList.remove('on'));
  addEventListener('blur',()=>sq.classList.remove('on'));
}

/* shell texts (footer, mobile menu) come from the CMS too */
$$('[data-c]').forEach(el=>{const path=el.dataset.c.split('.');let v=C;for(const k of path)v=v&&v[k];if(v==null||v==='')return;
  if(el.dataset.attr==='href')el.setAttribute('href',v);else el.textContent=v});
$$('[data-c-hide]').forEach(el=>{const path=el.dataset.cHide.split('.');let v=C;for(const k of path)v=v&&v[k];if(!v)el.remove()});

go(true);
})();
