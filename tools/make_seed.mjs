// Evaluates src/data.js (+ videos.js) and writes cms/_install/seed.json: the prototype's content becomes the CMS starting point.
import fs from 'fs'; import path from 'path'; import { fileURLToPath } from 'url';
const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
globalThis.window = {};
const code = fs.readFileSync(path.join(root, 'src/data.js'), 'utf8') + '\n' + fs.readFileSync(path.join(root, 'src/videos.js'), 'utf8')
  + '\n;({PROJECTS,CATS,PHOTOS,FILMS,HOME_FILMS,SERVICES,LOGOS,HOME_WORK,HOME_PHOTOS,C})';
const d = (0, eval)(code);
const seed = { projects: d.PROJECTS, cats: d.CATS, photos: d.PHOTOS, films: d.FILMS, homeFilms: d.HOME_FILMS, services: d.SERVICES,
  logos: d.LOGOS, homeWork: d.HOME_WORK, homePhotos: d.HOME_PHOTOS, copy: d.C };
fs.writeFileSync(path.join(root, 'cms/_install/seed.json'), JSON.stringify(seed, null, 1));
console.log('seed.json:', seed.projects.length, 'projects,', seed.photos.length, 'photos,', seed.films.length, 'films,', seed.logos.length, 'clients');
