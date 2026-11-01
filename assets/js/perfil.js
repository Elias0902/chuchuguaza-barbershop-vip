/**
 * Mi perfil: pestañas, cambio de foto, datos de la cuenta y contraseña.
 */
(() => {
  'use strict';

  const { el, api, aviso } = window.Panel;
  const TAMANO_MAXIMO = 2 * 1024 * 1024;

  // ---------- Pestañas (también se abren desde el menú del perfil con #datos o #seguridad) ----------
  const secciones = document.querySelectorAll('[data-seccion]');
  const pestanas = document.querySelectorAll('[data-tab]');
  function mostrar(nombre) {
    const existe = [...secciones].some((s) => s.dataset.seccion === nombre);
    const destino = existe ? nombre : 'mi-perfil';
    secciones.forEach((s) => { s.hidden = s.dataset.seccion !== destino; });
    pestanas.forEach((p) => p.classList.toggle('active', p.dataset.tab === destino));
  }
  pestanas.forEach((p) => p.addEventListener('click', (evento) => {
    evento.preventDefault();
    history.replaceState(null, '', `#${p.dataset.tab}`);
    mostrar(p.dataset.tab);
  }));
  window.addEventListener('hashchange', () => mostrar(location.hash.slice(1)));
  mostrar(location.hash.slice(1));

  // ---------- Foto de perfil ----------
  function pintarAvatares(src) {
    document.querySelectorAll('[data-avatar]').forEach((nodo) => nodo.replaceChildren(el('img', { src, alt: '' })));
  }

  document.querySelector('[data-foto]')?.addEventListener('change', async (evento) => {
    const archivo = evento.target.files[0];
    evento.target.value = '';
    if (!archivo) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
      aviso('La foto debe ser una imagen JPG, PNG o WEBP.', 'error');
      return;
    }
    if (archivo.size > TAMANO_MAXIMO) {
      aviso('La imagen no puede pesar más de 2 MB.', 'error');
      return;
    }

    const formulario = new FormData();
    formulario.append('foto', archivo);
    try {
      const respuesta = await api('index.php?url=perfil&action=foto', { metodo: 'POST', formulario });
      pintarAvatares(`${respuesta.datos.avatar}?v=${Date.now()}`);
      aviso(respuesta.mensaje);
    } catch (error) {
      aviso(error.message, 'error');
    }
  });

  // ---------- Formularios de datos y contraseña ----------
  document.querySelectorAll('[data-form-perfil]').forEach((formulario) => {
    formulario.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      formulario.querySelectorAll('.field').forEach((campo) => campo.classList.remove('invalid'));
      formulario.querySelector('.form-alert')?.remove();

      const accion = formulario.dataset.formPerfil;
      const boton = formulario.querySelector('button[type="submit"]');
      const datos = Object.fromEntries(new FormData(formulario));
      boton.disabled = true;
      try {
        const respuesta = await api(`index.php?url=perfil&action=${accion}`, { metodo: 'POST', datos });
        aviso(respuesta.mensaje);
        if (accion === 'clave') formulario.reset();
        if (accion === 'datos') {
          document.querySelectorAll('[data-perfil-nombre]').forEach((nodo) => {
            nodo.textContent = nodo.closest('.topbar') ? respuesta.datos.nombre_usuario : `@${respuesta.datos.nombre_usuario}`;
          });
          document.querySelectorAll('[data-perfil-correo]').forEach((nodo) => { nodo.textContent = respuesta.datos.email_usuario; });
        }
      } catch (error) {
        formulario.prepend(el('div', { class: 'form-alert', role: 'alert' }, error.message));
        const campo = error.campo ? formulario.querySelector(`[name="${error.campo}"]`) : null;
        if (campo) { campo.closest('.field')?.classList.add('invalid'); campo.focus(); }
      } finally {
        boton.disabled = false;
      }
    });
  });
})();
