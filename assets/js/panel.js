/**
 * Utilidades compartidas del panel: menú lateral, menú del perfil,
 * peticiones fetch con CSRF, modales, confirmaciones y avisos.
 * Se exponen en window.Panel para modulo.js y perfil.js.
 */
(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  /** Crea un elemento: el('button', { class: 'btn', onclick: fn }, 'Texto'). */
  function el(etiqueta, atributos = {}, ...hijos) {
    const nodo = document.createElement(etiqueta);
    for (const [clave, valor] of Object.entries(atributos)) {
      if (valor === null || valor === undefined || valor === false) continue;
      if (clave.startsWith('on') && typeof valor === 'function') nodo.addEventListener(clave.slice(2), valor);
      else if (clave === 'class') nodo.className = valor;
      else if (clave === 'dataset') Object.assign(nodo.dataset, valor);
      else if (valor === true) nodo.setAttribute(clave, '');
      else nodo.setAttribute(clave, valor);
    }
    for (const hijo of hijos.flat()) {
      if (hijo === null || hijo === undefined || hijo === false) continue;
      nodo.append(hijo instanceof Node ? hijo : document.createTextNode(String(hijo)));
    }
    return nodo;
  }

  const icono = (nombre) => el('i', { class: `fas ${nombre}`, 'aria-hidden': 'true' });

  /** Petición al servidor. Lanza Error con .campo si la respuesta no es correcta. */
  async function api(url, { metodo = 'GET', datos = null, formulario = null } = {}) {
    document.dispatchEvent(new Event('panel:peticion')); // cuenta como actividad para el cierre por inactividad
    const opciones = { method: metodo, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (metodo !== 'GET') {
      opciones.headers['X-CSRF-Token'] = csrf;
      if (formulario) {
        opciones.body = formulario;
      } else {
        opciones.headers['Content-Type'] = 'application/json';
        opciones.body = JSON.stringify(datos ?? {});
      }
    }

    let respuesta;
    try {
      respuesta = await fetch(url, opciones);
    } catch {
      throw new Error('No hay conexión con el servidor.');
    }
    let json = null;
    try {
      json = await respuesta.json();
    } catch {
      throw new Error('El servidor respondió de forma inesperada.');
    }
    if (respuesta.status === 401) {
      window.location.href = 'index.php?url=autenticator&action=acceso_interno';
    }
    if (!respuesta.ok || !json.ok) {
      const error = new Error(json?.mensaje || 'Ocurrió un error.');
      error.campo = json?.campo ?? null;
      throw error;
    }
    return json;
  }

  // ---------- Avisos ----------
  let contenedorAvisos = null;
  function aviso(mensaje, tipo = 'exito') {
    if (!contenedorAvisos) {
      contenedorAvisos = el('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' });
      document.body.append(contenedorAvisos);
    }
    const nodo = el('div', { class: `toast${tipo === 'error' ? ' error' : ''}` },
      icono(tipo === 'error' ? 'fa-exclamation-triangle' : 'fa-check'), el('span', {}, mensaje));
    contenedorAvisos.append(nodo);
    setTimeout(() => nodo.remove(), tipo === 'error' ? 6500 : 4000);
  }

  // ---------- Modales ----------
  /** Abre un modal. Devuelve { cerrar, cuerpo, pie, raiz }. */
  function modal({ titulo, tamano = '', alCerrar = null }) {
    const anterior = document.activeElement;
    const cuerpo = el('div', { class: 'modal-body' });
    const pie = el('div', { class: 'modal-footer' });
    const idTitulo = `modal-${Date.now()}`;
    const ventana = el('div', { class: `modal ${tamano}`, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': idTitulo },
      el('div', { class: 'modal-header' },
        el('h2', { id: idTitulo }, titulo),
        el('button', { class: 'icon-button', type: 'button', 'aria-label': 'Cerrar', onclick: () => cerrar() }, icono('fa-times'))),
      cuerpo, pie);
    const raiz = el('div', { class: 'modal-backdrop' }, ventana);

    const alTeclado = (evento) => { if (evento.key === 'Escape') cerrar(); };
    raiz.addEventListener('mousedown', (evento) => { if (evento.target === raiz) cerrar(); });
    document.addEventListener('keydown', alTeclado);
    document.body.append(raiz);

    function cerrar() {
      document.removeEventListener('keydown', alTeclado);
      raiz.remove();
      if (alCerrar) alCerrar();
      if (anterior && typeof anterior.focus === 'function') anterior.focus();
    }
    return { cerrar, cuerpo, pie, raiz, ventana };
  }

  /** Confirmación con botones Cancelar / Confirmar. Resuelve true o false. */
  function confirmar({ titulo = 'Confirmar', mensaje, textoConfirmar = 'Confirmar', peligro = false }) {
    return new Promise((resolver) => {
      let respuesta = false;
      const ventana = modal({ titulo, tamano: 'modal-sm', alCerrar: () => resolver(respuesta) });
      ventana.cuerpo.append(el('p', {}, mensaje));
      const aceptar = el('button', { class: `btn ${peligro ? 'btn-danger' : 'btn-primary'}`, type: 'button', onclick: () => { respuesta = true; ventana.cerrar(); } }, textoConfirmar);
      ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, 'Cancelar'), aceptar);
      aceptar.focus();
    });
  }

  // ---------- Menú lateral ----------
  function iniciarSidebar() {
    const shell = document.querySelector('.app-shell');
    const boton = document.querySelector('[data-menu-toggle]');
    const cerrarMenu = () => { shell?.classList.remove('menu-open'); boton?.setAttribute('aria-expanded', 'false'); };
    boton?.addEventListener('click', () => {
      const abierto = shell.classList.toggle('menu-open');
      boton.setAttribute('aria-expanded', String(abierto));
    });
    document.querySelectorAll('[data-menu-close]').forEach((nodo) => nodo.addEventListener('click', cerrarMenu));
    document.addEventListener('keydown', (evento) => { if (evento.key === 'Escape') cerrarMenu(); });

    let plegados = [];
    try { plegados = JSON.parse(localStorage.getItem('panel.gruposPlegados') || '[]'); } catch { plegados = []; }
    document.querySelectorAll('.sidebar-group').forEach((grupo) => {
      const clave = grupo.dataset.grupo;
      const toggle = grupo.querySelector('.sidebar-group-toggle');
      const aplicar = (plegado) => { grupo.classList.toggle('collapsed', plegado); toggle.setAttribute('aria-expanded', String(!plegado)); };
      aplicar(plegados.includes(clave) && !grupo.classList.contains('has-active'));
      toggle.addEventListener('click', () => {
        const plegado = !grupo.classList.contains('collapsed');
        aplicar(plegado);
        plegados = plegado ? [...new Set([...plegados, clave])] : plegados.filter((c) => c !== clave);
        try { localStorage.setItem('panel.gruposPlegados', JSON.stringify(plegados)); } catch { /* sin almacenamiento */ }
      });
    });
    document.querySelector('.sidebar-link.active')?.scrollIntoView({ block: 'nearest' });
  }

  // ---------- Menú del perfil ----------
  function iniciarDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((contenedor) => {
      const boton = contenedor.querySelector('[data-dropdown-toggle]');
      const menu = contenedor.querySelector('[data-dropdown-menu]');
      const alternar = (abrir) => { menu.hidden = !abrir; boton.setAttribute('aria-expanded', String(abrir)); };
      boton.addEventListener('click', () => alternar(menu.hidden));
      document.addEventListener('click', (evento) => { if (!contenedor.contains(evento.target)) alternar(false); });
      document.addEventListener('keydown', (evento) => { if (evento.key === 'Escape') alternar(false); });
    });
  }

  // ---------- Modo claro / oscuro ----------
  function iniciarTema() {
    const boton = document.querySelector('[data-tema-toggle]');
    if (!boton) return;
    const pintar = () => {
      const claro = document.documentElement.dataset.tema === 'claro';
      boton.querySelector('i').className = `fas ${claro ? 'fa-moon' : 'fa-sun'}`;
      boton.setAttribute('aria-label', claro ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro');
    };
    boton.addEventListener('click', () => {
      const nuevo = document.documentElement.dataset.tema === 'claro' ? 'oscuro' : 'claro';
      document.documentElement.dataset.tema = nuevo;
      try { localStorage.setItem('panel.tema', nuevo); } catch { /* sin almacenamiento */ }
      pintar();
    });
    pintar();
  }

  // ---------- Cierre por inactividad ----------
  /**
   * El servidor cierra la sesión si no recibe peticiones en el plazo; aquí solo:
   * 1) avisamos «sigo aquí» (como mucho cada 30 s) cuando la persona usa el ratón o el teclado,
   * 2) mostramos un aviso 20 s antes y 3) llevamos al acceso cuando se cumple el plazo.
   */
  function iniciarInactividad() {
    const limite = Number(document.querySelector('meta[name="inactividad"]')?.content || 0) * 1000;
    if (!limite) return;
    const AVISO = 20000;
    let ultimaActividad = Date.now();
    let ultimoPing = Date.now();
    let aviso = null;

    const salir = () => { window.location.href = 'index.php?url=autenticator&action=acceso_interno'; };
    const ping = () => {
      ultimoPing = Date.now();
      fetch('index.php?url=autenticator&action=ping', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((r) => r.json()).then((r) => { if (!r.ok) salir(); }).catch(() => {});
    };
    const actividad = () => {
      ultimaActividad = Date.now();
      if (aviso) return; // con el aviso abierto hay que pulsar «Seguir conectado»
      if (Date.now() - ultimoPing > 30000) ping();
    };
    document.addEventListener('panel:peticion', () => { ultimoPing = Date.now(); });
    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach((evento) => document.addEventListener(evento, actividad, { passive: true }));

    setInterval(() => {
      const inactivo = Date.now() - ultimaActividad;
      const sinPing = Date.now() - ultimoPing;
      if (sinPing >= limite) salir();
      else if (inactivo >= limite - AVISO && !aviso) {
        let respuesta = false;
        aviso = modal({ titulo: 'Tu sesión está por cerrarse', tamano: 'modal-sm', alCerrar: () => { aviso = null; if (respuesta) { ultimaActividad = Date.now(); ping(); } } });
        const cuenta = el('strong', {}, '20');
        aviso.cuerpo.append(el('p', {}, 'Por seguridad, la sesión se cierra tras ', String(limite / 60000), ' minutos sin actividad. Se cerrará en ', cuenta, ' segundos.'));
        aviso.cuenta = cuenta;
        aviso.pie.append(
          el('button', { class: 'btn btn-secondary', type: 'button', onclick: salir }, 'Salir'),
          el('button', { class: 'btn btn-primary', type: 'button', onclick: () => { respuesta = true; aviso.cerrar(); } }, 'Seguir conectado'));
      }
      if (aviso?.cuenta) aviso.cuenta.textContent = String(Math.max(0, Math.ceil((limite - sinPing) / 1000)));
    }, 1000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    iniciarSidebar();
    iniciarDropdowns();
    iniciarTema();
    iniciarInactividad();
  });

  window.Panel = { el, icono, api, aviso, modal, confirmar, csrf };
})();
