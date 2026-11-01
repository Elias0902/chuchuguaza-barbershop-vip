/**
 * Matriz de permisos de un rol (módulo Roles → botón «Permisos»).
 * Se registra como extensión y modulo.js la llama al hacer clic en la acción.
 */
(() => {
  'use strict';

  const { el, icono, api, aviso, modal } = window.Panel;

  async function abrirPermisos(registro, contexto) {
    let datos;
    try {
      datos = (await api(contexto.url('permisos', { id: registro.id_rol }))).datos;
    } catch (error) {
      aviso(error.message, 'error');
      return;
    }

    const editable = contexto.permisos.modificar;
    const acciones = Object.entries(datos.acciones); // [['consultar', 'Consultar'], ...]
    const casillas = []; // { ruta, permiso, input }
    const ventana = modal({ titulo: `Permisos del rol «${datos.rol.nombre_rol}»`, tamano: 'modal-lg' });

    const porFila = (ruta) => casillas.filter((c) => c.ruta === ruta);
    const porColumna = (permiso) => casillas.filter((c) => c.permiso === permiso);

    /** Registrar, modificar o eliminar exigen consultar; sin consultar no hay nada más. */
    function coherencia(cambiada) {
      const fila = porFila(cambiada.ruta);
      const consultar = fila.find((c) => c.permiso === 'consultar');
      if (cambiada.permiso === 'consultar' && !cambiada.input.checked) fila.forEach((c) => { c.input.checked = false; });
      if (cambiada.permiso !== 'consultar' && cambiada.input.checked) consultar.input.checked = true;
    }

    /** Si falta alguna casilla marcada, marca todas; si no, las desmarca. */
    function alternarGrupo(lista) {
      const marcar = lista.some((c) => !c.input.checked);
      lista.forEach((c) => { c.input.checked = marcar; coherencia(c); });
    }

    const cabecera = el('tr', {}, el('th', {}, 'Módulo'),
      acciones.map(([permiso, nombre]) => el('th', { class: 'perm-col' },
        editable
          ? el('button', { class: 'perm-toggle', type: 'button', title: `Marcar o desmarcar «${nombre}» en todos`, onclick: () => alternarGrupo(porColumna(permiso)) }, nombre)
          : nombre)),
      el('th', { class: 'perm-col' }, editable ? 'Todo' : ''));

    const cuerpo = el('tbody');
    datos.grupos.forEach((grupo) => {
      cuerpo.append(el('tr', { class: 'perm-group' }, el('td', { colspan: acciones.length + 2 }, grupo.titulo)));
      grupo.modulos.forEach((modulo) => {
        const fila = el('tr', {}, el('td', {}, el('span', { class: 'perm-module' }, icono(modulo.icono), modulo.titulo)));
        acciones.forEach(([permiso, nombre]) => {
          const input = el('input', { type: 'checkbox', 'aria-label': `${nombre} en ${modulo.titulo}`, disabled: !editable });
          input.checked = modulo.permisos.includes(permiso);
          const casilla = { ruta: modulo.ruta, permiso, input };
          input.addEventListener('change', () => coherencia(casilla));
          casillas.push(casilla);
          fila.append(el('td', { class: 'perm-col' }, input));
        });
        fila.append(el('td', { class: 'perm-col' }, editable
          ? el('button', { class: 'icon-button', type: 'button', title: 'Marcar o desmarcar todo el módulo', 'aria-label': `Todo ${modulo.titulo}`, onclick: () => alternarGrupo(porFila(modulo.ruta)) }, icono('fa-check'))
          : null));
        cuerpo.append(fila);
      });
    });

    ventana.cuerpo.append(
      el('p', { class: 'perm-help' }, editable
        ? 'Marca lo que puede hacer este rol en cada módulo. Eliminar incluye restaurar. Registrar, modificar o eliminar activan consultar automáticamente.'
        : 'Tu rol puede ver estos permisos, pero no modificarlos.'),
      el('div', { class: 'table-wrap' }, el('table', { class: 'data-table perm-table' }, el('thead', {}, cabecera), cuerpo)));

    ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, editable ? 'Cancelar' : 'Cerrar'));
    if (!editable) return;

    const guardar = el('button', { class: 'btn btn-primary', type: 'button' }, icono('fa-save'), 'Guardar permisos');
    ventana.pie.append(guardar);
    guardar.addEventListener('click', async () => {
      const permisos = {};
      casillas.filter((c) => c.input.checked).forEach((c) => { (permisos[c.ruta] ??= []).push(c.permiso); });
      guardar.disabled = true;
      try {
        const respuesta = await api(contexto.url('guardar_permisos'), { metodo: 'POST', datos: { id: registro.id_rol, permisos } });
        ventana.cerrar();
        aviso(respuesta.mensaje);
        contexto.recargar();
      } catch (error) {
        aviso(error.message, 'error');
      } finally {
        guardar.disabled = false;
      }
    });
  }

  window.ModuloExtensiones = { ...(window.ModuloExtensiones || {}), permisos: abrirPermisos };
})();
