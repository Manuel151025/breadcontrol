// assets/js/landing.js — comportamiento de la portada publica.
//
// Estaba en dos bloques <script> incrustados dentro de views/auth/landing.php.
// Se mueve aqui sin cambiar una linea de logica: mientras exista un bloque de
// script sin src, la CSP necesita 'unsafe-inline' en script-src, y eso deja
// abierta la via mas comun de XSS (punto 22 del anexo).

// Nav scroll
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 40));

// Mobile menu
const ham = document.getElementById('navHam');
const mob = document.getElementById('navMobile');
const ico = document.getElementById('hamIco');
ham.addEventListener('click', () => {
  const open = mob.classList.toggle('open');
  ico.className = open ? 'bi bi-x-lg' : 'bi bi-list';
});
mob.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  mob.classList.remove('open');
  ico.className = 'bi bi-list';
}));

// Scroll reveal
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
document.querySelectorAll('.reveal, .reveal-x').forEach(el => observer.observe(el));

// Hero instant
setTimeout(() => {
  document.querySelectorAll('.hero .reveal, .hero .reveal-x').forEach(el => el.classList.add('visible'));
}, 150);

// Al abrir un enlace con ancla (/#modulos), el navegador salta ANTES de que las
// imágenes y las animaciones terminen de asentar la página, así que el destino
// acaba fuera de la vista. Se recoloca una vez que todo cargó.
window.addEventListener('load', function () {
  if (!location.hash) return;
  var destino = document.querySelector(location.hash);
  if (!destino) return;
  setTimeout(function () {
    destino.scrollIntoView({ block: 'start', behavior: 'auto' });
  }, 150);
});
