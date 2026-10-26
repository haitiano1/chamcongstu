export const BASE=document.querySelector('meta[name="app-base"]').content;
export function apiUrl(path){const [route,...query]=path.replace(/^\//,'').split('?');const params=new URLSearchParams(query.join('?'));params.set('route','/'+route);return BASE+'/api.php?'+params;}
