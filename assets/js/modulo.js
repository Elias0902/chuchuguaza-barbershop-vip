/**
 * Módulo CRUD genérico: consulta la tabla por AJAX (action=consultar) y arma
 * el listado, los filtros, la búsqueda, la paginación y el formulario de
 * registrar / modificar, además de la eliminación lógica y la restauración.
 * La configuración (columnas, campos, opciones) la envía el controlador.
 */
(() => {
  'use strict';

  const { el, icono, api, aviso, modal, confirmar } = window.Panel;
  const config = JSON.parse(document.getElementById('modulo-config').textContent);

  // ---------- Formatos ----------
  const decimal = new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const entero = new Intl.NumberFormat('es-VE', { maximumFractionDigits: 0 });
  const dos = (n) => String(n).padStart(2, '0');

  function aFecha(valor) {
    const [fecha, hora = '00:00:00'] = String(valor).split(/[ T]/);
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const [hh, mm] = hora.split(':').map(Number);
    return new Date(anio, mes - 1, dia, hh || 0, mm || 0);
  }

  const formatos = {
    texto: (v) => String(v),
    moneda: (v) => `${config.moneda} ${decimal.format(Number(v))}`,
    entero: (v) => entero.format(Number(v)),
    porcentaje: (v) => `${decimal.format(Number(v))}%`,
    minutos: (v) => `${v} min`,
    numero: (v) => `#${v}`,
    fecha: (v) => { const d = aFecha(v); return `${dos(d.getDate())}/${dos(d.getMonth() + 1)}/${d.getFullYear()}`; },
    fechahora: (v) => {
      const d = aFecha(v);
      const horas = d.getHours() % 12 || 12;
      return `${dos(d.getDate())}/${dos(d.getMonth() + 1)}/${d.getFullYear()} ${dos(horas)}:${dos(d.getMinutes())} ${d.getHours() < 12 ? 'a. m.' : 'p. m.'}`;
    },
  };

  const NUMERICOS = ['moneda', 'entero', 'porcentaje'];

  function formatear(valor, columna) {
    if (valor === null || valor === undefined || valor === '') return '—';
    const formato = columna.formato || 'texto';
    if (formato === 'insignia') return el('span', { class: `badge ${columna.colores?.[valor] || 'gris'}` }, valor);
    if (formato === 'sino') return el('span', { class: `badge ${Number(valor) === 1 ? 'verde' : 'gris'}` }, Number(valor) === 1 ? 'Sí' : 'No');
    return (formatos[formato] || formatos.texto)(valor);
  }

  const ahoraLocal = () => { const d = new Date(); return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}T${dos(d.getHours())}:${dos(d.getMinutes())}`; };
  const hoy = () => ahoraLocal().slice(0, 10);
  const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const capitalizar = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1);

  /** Valor de la base de datos → valor de un input. */
  function valorParaInput(valor, tipo) {
    if (valor === null || valor === undefined) return '';
    if (tipo === 'fechahora') return String(valor).replace(' ', 'T').slice(0, 16);
    if (tipo === 'fecha') return String(valor).slice(0, 10);
    return String(valor);
  }

  class ModuloCrud {
    constructor(configuracion) {
      this.config = configuracion;
      this.registros = [];
      this.filtro = configuracion.tieneEstado ? 'activos' : 'todos';
      this.busqueda = '';
      this.pagina = 1;
      this.porPagina = 15;
      this.mostrarAcciones = !configuracion.soloConsulta;

      this.cabecera = document.querySelector('[data-cabecera]');
      this.cuerpo = document.querySelector('[data-cuerpo]');
      this.resumen = document.querySelector('[data-resumen]');
      this.paginaActual = document.querySelector('[data-pagina-actual]');
      this.enlaceReporte = document.querySelector('[data-reporte]');

      this.dibujarCabecera();
      this.enlazarEventos();
      this.cargar();
    }

    url(accion, parametros = {}) {
      const url = new URL(this.config.url, window.location.href);
      if (accion) url.searchParams.set('action', accion);
      Object.entries(parametros).forEach(([clave, valor]) => url.searchParams.set(clave, valor));
      return url.toString();
    }

    get columnasTotales() {
      return this.config.columnas.length + (this.config.tieneEstado ? 1 : 0) + (this.mostrarAcciones ? 1 : 0);
    }

    enlazarEventos() {
      document.querySelector('[data-nuevo]')?.addEventListener('click', () => this.abrirFormulario(null, 'nuevo'));
      document.querySelectorAll('[data-filtro]').forEach((boton) => boton.addEventListener('click', () => {
        this.filtro = boton.dataset.filtro;
        this.pagina = 1;
        document.querySelectorAll('[data-filtro]').forEach((b) => {
          b.classList.toggle('active', b === boton);
          b.setAttribute('aria-selected', String(b === boton));
        });
        this.render();
      }));
      let espera;
      document.querySelector('[data-buscar]')?.addEventListener('input', (evento) => {
        clearTimeout(espera);
        espera = setTimeout(() => { this.busqueda = normalizar(evento.target.value.trim()); this.pagina = 1; this.render(); }, 150);
      });
      document.querySelectorAll('[data-pagina]').forEach((boton) => boton.addEventListener('click', () => {
        this.pagina += Number(boton.dataset.pagina);
        this.render();
      }));
    }

    // ---------- Consultar ----------
    async cargar() {
      try {
        const respuesta = await api(this.url('consultar'));
        this.registros = respuesta.datos;
        this.render();
      } catch (error) {
        this.cuerpo.replaceChildren(el('tr', {}, el('td', { class: 'empty', colspan: this.columnasTotales }, error.message)));
      }
    }

    dibujarCabecera() {
      const fila = el('tr');
      this.config.columnas.forEach((columna) => fila.append(el('th', { class: NUMERICOS.includes(columna.formato) ? 'num' : null }, columna.etiqueta)));
      if (this.config.tieneEstado) fila.append(el('th', {}, 'Estado'));
      if (this.mostrarAcciones) fila.append(el('th', { class: 'num' }, 'Acciones'));
      this.cabecera.replaceChildren(fila);
    }

    filtrados() {
      return this.registros.filter((registro) => {
        if (this.filtro === 'activos' && Number(registro.status) !== 1) return false;
        if (this.filtro === 'eliminados' && Number(registro.status) !== 0) return false;
        if (!this.busqueda) return true;
        const texto = this.config.columnas.map((c) => {
          const valor = registro[c.campo];
          const visible = valor === null || valor === undefined ? '' : (c.formato && formatos[c.formato] ? formatos[c.formato](valor) : valor);
          return `${visible} ${c.sub ? registro[c.sub] ?? '' : ''}`;
        }).join(' ');
        return normalizar(texto).includes(this.busqueda);
      });
    }

    render() {
      const activos = this.registros.filter((r) => Number(r.status) === 1).length;
      const contadores = { activos, eliminados: this.registros.length - activos, todos: this.registros.length };
      document.querySelectorAll('[data-contador]').forEach((nodo) => { nodo.textContent = contadores[nodo.dataset.contador]; });

      const lista = this.filtrados();
      const paginas = Math.max(1, Math.ceil(lista.length / this.porPagina));
      this.pagina = Math.min(Math.max(1, this.pagina), paginas);
      const inicio = (this.pagina - 1) * this.porPagina;
      const visibles = lista.slice(inicio, inicio + this.porPagina);

      if (visibles.length === 0) {
        const mensaje = this.registros.length === 0
          ? (this.config.soloConsulta ? 'Todavía no hay movimientos.' : 'Todavía no hay registros. Usa el botón de arriba para agregar el primero.')
          : 'No hay registros que coincidan con el filtro o la búsqueda.';
        this.cuerpo.replaceChildren(el('tr', {}, el('td', { class: 'empty', colspan: this.columnasTotales }, mensaje)));
      } else {
        this.cuerpo.replaceChildren(...visibles.map((registro) => this.fila(registro)));
      }

      this.resumen.textContent = lista.length === 0 ? 'Sin resultados' : `Mostrando ${inicio + 1}–${inicio + visibles.length} de ${lista.length}`;
      this.paginaActual.textContent = `${this.pagina} / ${paginas}`;
      document.querySelector('[data-pagina="-1"]').disabled = this.pagina <= 1;
      document.querySelector('[data-pagina="1"]').disabled = this.pagina >= paginas;
      if (this.enlaceReporte) this.enlaceReporte.href = this.url('reporte', { filtro: this.filtro });
    }

    fila(registro) {
      const activo = !this.config.tieneEstado || Number(registro.status) === 1;
      const tr = el('tr', { class: activo ? null : 'is-deleted' });
      this.config.columnas.forEach((columna) => {
        const valor = registro[columna.campo];
        const largo = typeof valor === 'string' && valor.length > 40;
        const corto = ['fecha', 'fechahora', 'numero', 'insignia', 'sino'].includes(columna.formato) || String(valor ?? '').length <= 24;
        const clase = NUMERICOS.includes(columna.formato) ? 'num' : (largo ? 'long' : (corto ? 'nowrap' : null));
        const td = el('td', { class: clase }, formatear(valor, columna));
        if (columna.sub && registro[columna.sub]) td.append(el('small', {}, registro[columna.sub]));
        tr.append(td);
      });
      if (this.config.tieneEstado) {
        tr.append(el('td', {}, el('span', { class: `badge ${activo ? 'verde' : 'gris'}` }, activo ? 'Activo' : 'Eliminado')));
      }
      if (this.mostrarAcciones) tr.append(el('td', {}, this.botonesFila(registro, activo)));
      return tr;
    }

    cumple(condicion, registro) {
      if (!condicion) return true;
      return Object.entries(condicion).every(([campo, esperado]) => (Array.isArray(esperado) ? esperado : [esperado])
        .map(String).includes(String(registro[campo])));
    }

    botonesFila(registro, activo) {
      const boton = (titulo, nombreIcono, alHacerClic, clase = '') => el('button', {
        class: `icon-button ${clase}`, type: 'button', title: titulo, 'aria-label': titulo, onclick: alHacerClic,
      }, icono(nombreIcono));

      const puede = this.config.permisos;
      const botones = [boton('Ver detalle', 'fa-eye', () => this.abrirFormulario(registro, 'ver'))];
      if (activo) {
        this.config.acciones
          .filter((accion) => puede[accion.permiso || 'modificar'] && this.cumple(accion.si, registro))
          .forEach((accion) => {
            botones.push(boton(accion.etiqueta, accion.icono || 'fa-bolt', () => this.ejecutarAccion(accion, registro), accion.peligro ? 'danger' : 'success'));
          });
        if (puede.modificar && this.cumple(this.config.edicionSi, registro)) botones.push(boton('Modificar', 'fa-pen', () => this.abrirFormulario(registro, 'editar')));
        if (puede.eliminar && this.config.tieneEstado) botones.push(boton('Eliminar', 'fa-trash-alt', () => this.eliminar(registro), 'danger'));
      } else if (puede.eliminar) {
        botones.push(boton('Restaurar', 'fa-undo', () => this.restaurar(registro), 'success'));
      }
      return el('div', { class: 'row-actions' }, botones);
    }

    describir(registro) {
      const columna = this.config.columnas[0];
      const valor = formatear(registro[columna.campo], columna);
      return valor instanceof Node ? valor.textContent : valor;
    }

    // ---------- Eliminación lógica y restauración ----------
    async eliminar(registro) {
      const ok = await confirmar({
        titulo: `Eliminar ${this.config.singular}`,
        mensaje: `¿Eliminar «${this.describir(registro)}»? Quedará como eliminado (eliminación lógica) y podrás restaurarlo desde «Eliminados».`,
        textoConfirmar: 'Eliminar',
        peligro: true,
      });
      if (ok) await this.enviar('eliminar', { id: registro[this.config.clave] });
    }

    async restaurar(registro) {
      const ok = await confirmar({
        titulo: `Restaurar ${this.config.singular}`,
        mensaje: `¿Restaurar «${this.describir(registro)}»? Volverá a estar activo.`,
        textoConfirmar: 'Restaurar',
      });
      if (ok) await this.enviar('restaurar', { id: registro[this.config.clave] });
    }

    async enviar(accion, datos) {
      try {
        const respuesta = await api(this.url(accion), { metodo: 'POST', datos });
        aviso(respuesta.mensaje);
        await this.cargar();
        return true;
      } catch (error) {
        aviso(error.message, 'error');
        return false;
      }
    }

    /** Acciones propias del módulo (cerrar caja, cobrar, completar cita...). */
    async ejecutarAccion(accion, registro) {
      const id = registro[this.config.clave];

      // Acciones con interfaz propia (por ejemplo, la matriz de permisos de Roles).
      if (accion.extension) {
        const extension = window.ModuloExtensiones?.[accion.extension];
        if (extension) {
          extension(registro, {
            url: (nombre, parametros) => this.url(nombre, parametros),
            recargar: () => this.cargar(),
            permisos: this.config.permisos,
          });
        }
        return;
      }

      if (!accion.pedir) {
        const ok = await confirmar({ titulo: accion.etiqueta, mensaje: accion.confirmar || `¿${accion.etiqueta}?`, textoConfirmar: accion.etiqueta, peligro: !!accion.peligro });
        if (ok) await this.enviar(accion.clave, { id });
        return;
      }

      const valores = Object.fromEntries(Object.entries(accion.pedir).map(([campo, def]) => [campo, def.defectoFila ? registro[def.defectoFila] : (def.defecto ?? '')]));
      const ventana = modal({ titulo: accion.titulo || accion.etiqueta, tamano: 'modal-sm' });
      const formulario = this.construirFormulario(accion.pedir, valores, 'nuevo');
      ventana.cuerpo.append(formulario.nodo);
      const guardar = el('button', { class: 'btn btn-primary', type: 'submit', form: formulario.id }, icono(accion.icono || 'fa-check'), accion.etiqueta);
      ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, 'Cancelar'), guardar);
      formulario.enfocar();
      formulario.nodo.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!formulario.validar()) return;
        guardar.disabled = true;
        try {
          const respuesta = await api(this.url(accion.clave), { metodo: 'POST', datos: { id, ...formulario.valores() } });
          ventana.cerrar();
          aviso(respuesta.mensaje);
          this.cargar();
        } catch (error) {
          formulario.mostrarError(error);
        } finally {
          guardar.disabled = false;
        }
      });
    }

    // ---------- Registrar, modificar y ver ----------
    async abrirFormulario(registro, modo) {
      let datos = null;
      if (registro) {
        try {
          datos = (await api(this.url('obtener', { id: registro[this.config.clave] }))).datos;
        } catch (error) {
          aviso(error.message, 'error');
          return;
        }
      }

      const singular = this.config.singular;
      const titulos = {
        nuevo: `${this.config.femenino ? 'Nueva' : 'Nuevo'} ${singular}`,
        editar: `Modificar ${singular}`,
        ver: `Detalle de ${singular}`,
      };
      const ventana = modal({ titulo: capitalizar(titulos[modo]) });

      if (this.config.soloConsulta) {
        ventana.cuerpo.append(this.detalleSoloLectura(registro));
        ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, 'Cerrar'));
        return;
      }

      const valores = {};
      Object.entries(this.config.campos).forEach(([campo, def]) => {
        valores[campo] = datos ? datos[campo] : this.valorPorDefecto(campo, def);
      });
      const formulario = this.construirFormulario(this.config.campos, valores, modo);
      ventana.cuerpo.append(formulario.nodo);

      if (modo === 'ver') {
        const activo = !this.config.tieneEstado || Number(registro.status) === 1;
        const puedeEditar = activo && this.config.permisos.modificar && this.cumple(this.config.edicionSi, registro);
        ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, 'Cerrar'));
        if (puedeEditar) {
          ventana.pie.append(el('button', { class: 'btn btn-primary', type: 'button', onclick: () => { ventana.cerrar(); this.abrirFormulario(registro, 'editar'); } }, icono('fa-pen'), 'Modificar'));
        }
        return;
      }

      const guardar = el('button', { class: 'btn btn-primary', type: 'submit', form: formulario.id },
        icono(modo === 'nuevo' ? 'fa-plus' : 'fa-save'), modo === 'nuevo' ? 'Registrar' : 'Guardar cambios');
      ventana.pie.append(el('button', { class: 'btn btn-secondary', type: 'button', onclick: () => ventana.cerrar() }, 'Cancelar'), guardar);
      formulario.enfocar();

      formulario.nodo.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!formulario.validar()) return;
        const datosEnvio = formulario.valores();
        if (modo === 'editar') datosEnvio.id = datos[this.config.clave];
        guardar.disabled = true;
        try {
          const respuesta = await api(this.url(modo === 'nuevo' ? 'registrar' : 'modificar'), { metodo: 'POST', datos: datosEnvio });
          ventana.cerrar();
          aviso(respuesta.mensaje);
          if (modo === 'nuevo' && this.filtro === 'eliminados') document.querySelector('[data-filtro="activos"]')?.click();
          this.cargar();
        } catch (error) {
          formulario.mostrarError(error);
        } finally {
          guardar.disabled = false;
        }
      });
    }

    valorPorDefecto(campo, def) {
      if (this.config.defectos?.[campo] !== undefined && this.config.defectos[campo] !== null) return this.config.defectos[campo];
      if (def.defecto === 'ahora') return ahoraLocal();
      if (def.defecto === 'hoy') return hoy();
      return def.defecto ?? (def.tipo === 'detalle' ? [] : '');
    }

    /** Detalle en solo lectura para módulos de consulta (bitácora). */
    detalleSoloLectura(registro) {
      const lista = el('dl', { class: 'info-grid' });
      this.config.columnas.forEach((columna) => {
        const valor = formatear(registro[columna.campo], columna);
        lista.append(el('div', {}, el('dt', {}, columna.etiqueta), el('dd', {}, valor)));
      });
      return lista;
    }

    /**
     * Arma un formulario con la definición de campos del modelo.
     * Devuelve el nodo y funciones para leer valores, validar y mostrar errores.
     */
    construirFormulario(campos, valores, modo) {
      const id = `form-${Math.random().toString(36).slice(2)}`;
      const nodo = el('form', { class: 'form-grid', id, novalidate: true });
      const alerta = el('div', { class: 'form-alert', role: 'alert', hidden: true });
      nodo.append(alerta);

      const controles = {};
      Object.entries(campos).forEach(([campo, def]) => {
        if (def.formulario === false) return;
        const control = def.tipo === 'detalle'
          ? this.campoDetalle(campo, def, valores[campo], modo)
          : this.campo(campo, def, valores[campo], modo);
        controles[campo] = control;
        nodo.append(control.contenedor);
      });

      // Autollenado: al elegir una opción se copia un dato suyo en otro campo (precio, costo, saldo).
      Object.values(controles).forEach((control) => {
        const regla = control.def.autollenar;
        if (!regla || !controles[regla.campo] || modo === 'ver') return;
        control.entrada.addEventListener('change', () => {
          const dato = control.entrada.selectedOptions?.[0]?.dataset[regla.dato];
          if (dato !== undefined && dato !== '') controles[regla.campo].poner(dato);
        });
      });

      const limpiarErrores = () => {
        alerta.hidden = true;
        Object.values(controles).forEach((c) => c.marcarError(null));
      };

      return {
        id,
        nodo,
        valores: () => Object.fromEntries(Object.entries(controles).map(([campo, c]) => [campo, c.obtener()])),
        enfocar: () => nodo.querySelector('input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])')?.focus(),
        validar: () => {
          limpiarErrores();
          const primero = Object.values(controles).find((c) => !c.validar());
          if (primero) { primero.enfocar(); return false; }
          return true;
        },
        mostrarError: (error) => {
          limpiarErrores();
          alerta.textContent = error.message;
          alerta.hidden = false;
          if (error.campo && controles[error.campo]) {
            controles[error.campo].marcarError(error.message);
            controles[error.campo].enfocar();
          }
        },
      };
    }

    /** Un campo simple: input, select, textarea o checkbox. */
    campo(campo, def, valor, modo, compacto = false) {
      const idCampo = `campo-${campo}-${Math.random().toString(36).slice(2, 7)}`;
      const requerido = def.requerido === true || (def.requerido === 'registro' && modo === 'nuevo');
      const bloqueado = modo === 'ver' || (modo === 'editar' && def.fijo);
      const tipo = def.tipo || 'texto';
      let entrada;

      if (tipo === 'opcion' || tipo === 'relacion') {
        entrada = el('select', { id: idCampo, name: campo, required: requerido, disabled: bloqueado });
        entrada.append(el('option', { value: '' }, requerido ? 'Selecciona...' : 'Ninguno'));
        if (tipo === 'opcion') {
          (def.opciones || []).forEach((opcion) => entrada.append(el('option', { value: opcion }, opcion)));
        } else {
          const grupos = new Map();
          (this.config.opciones[def.fuente] || []).forEach((opcion) => {
            const esActual = String(opcion.valor) === String(valor ?? '');
            if (!opcion.activo && !esActual) return;
            const extras = Object.fromEntries(Object.entries(opcion).filter(([k]) => !['valor', 'texto', 'activo', 'grupo'].includes(k)).map(([k, v]) => [k, v ?? '']));
            const nodo = el('option', { value: opcion.valor, dataset: extras }, opcion.texto + (opcion.activo ? '' : ' (no disponible)'));
            if (opcion.grupo) {
              if (!grupos.has(opcion.grupo)) { grupos.set(opcion.grupo, el('optgroup', { label: opcion.grupo })); entrada.append(grupos.get(opcion.grupo)); }
              grupos.get(opcion.grupo).append(nodo);
            } else {
              entrada.append(nodo);
            }
          });
        }
        entrada.value = valorParaInput(valor, tipo);
      } else if (tipo === 'textoLargo') {
        entrada = el('textarea', { id: idCampo, name: campo, maxlength: def.max, required: requerido, disabled: bloqueado, placeholder: def.placeholder });
        entrada.value = valorParaInput(valor, tipo);
      } else if (tipo === 'booleano') {
        entrada = el('input', { id: idCampo, name: campo, type: 'checkbox', disabled: bloqueado });
        entrada.checked = Number(valor) === 1 || valor === true;
      } else {
        const tipos = { email: 'email', telefono: 'tel', clave: 'password', entero: 'number', decimal: 'number', fecha: 'date', fechahora: 'datetime-local' };
        entrada = el('input', {
          id: idCampo, name: campo, type: tipos[tipo] || 'text', required: requerido, disabled: bloqueado,
          maxlength: ['entero', 'decimal', 'fecha', 'fechahora'].includes(tipo) ? null : def.max,
          min: def.min, max: def.maxValor, step: tipo === 'decimal' ? '0.01' : (tipo === 'entero' ? '1' : null),
          placeholder: def.placeholder, autocomplete: tipo === 'clave' ? 'new-password' : 'off',
          inputmode: tipo === 'decimal' ? 'decimal' : null,
        });
        entrada.value = tipo === 'clave' ? '' : valorParaInput(valor, tipo);
      }

      const error = el('small', { class: 'field-error', hidden: true });
      let contenedor;
      if (compacto) {
        entrada.setAttribute('aria-label', def.etiqueta);
        contenedor = el('div', { class: 'field' }, entrada, error);
      } else if (tipo === 'booleano') {
        contenedor = el('div', { class: `field check-field${def.ancho === 'completo' ? ' full' : ''}` },
          entrada, el('label', { for: idCampo }, def.etiqueta), def.ayuda ? el('small', { class: 'field-help' }, def.ayuda) : null);
      } else {
        const ancho = def.ancho === 'completo' || tipo === 'textoLargo' ? ' full' : '';
        contenedor = el('div', { class: `field${ancho}` },
          el('label', { for: idCampo }, def.etiqueta, requerido ? el('span', { class: 'req' }, ' *') : null),
          entrada,
          def.ayuda && modo !== 'ver' ? el('small', { class: 'field-help' }, def.ayuda) : null,
          error);
      }

      return {
        def,
        entrada,
        contenedor,
        obtener: () => (tipo === 'booleano' ? (entrada.checked ? 1 : 0) : entrada.value.trim()),
        poner: (nuevo) => { entrada.value = valorParaInput(nuevo, tipo); entrada.dispatchEvent(new Event('input', { bubbles: true })); },
        enfocar: () => entrada.focus(),
        validar: () => {
          if (bloqueado) return true;
          if (requerido && tipo !== 'booleano' && entrada.value.trim() === '') {
            contenedor.classList.add('invalid'); error.textContent = `${def.etiqueta} es obligatorio.`; error.hidden = false;
            return false;
          }
          if (entrada.validity && !entrada.validity.valid) {
            contenedor.classList.add('invalid'); error.textContent = entrada.validationMessage; error.hidden = false;
            return false;
          }
          return true;
        },
        marcarError: (mensaje) => {
          contenedor.classList.toggle('invalid', !!mensaje);
          error.textContent = mensaje || '';
          error.hidden = !mensaje;
        },
      };
    }

    /** Campo de detalle: tabla de líneas (servicios/productos de un ticket, desglose de un retiro). */
    campoDetalle(campo, def, lineas, modo) {
      const soloLectura = modo === 'ver';
      const columnas = Object.entries(def.columnas);
      const cuerpo = el('tbody');
      const totalNodo = el('div', { class: 'detail-total' });
      const error = el('small', { class: 'field-error', hidden: true });
      const filas = [];

      const recalcular = () => {
        if (!def.total) return;
        const total = filas.reduce((suma, fila) => {
          const valores = def.total.map((c) => Number(String(fila.controles[c].obtener()).replace(',', '.')) || 0);
          return suma + valores.reduce((a, b) => a * b, 1);
        }, 0);
        totalNodo.replaceChildren(el('span', {}, 'Total: '), `${config.moneda} ${decimal.format(total)}`);
      };

      const agregarFila = (linea = {}) => {
        const controles = {};
        const tr = el('tr');
        columnas.forEach(([nombre, defColumna]) => {
          const valor = linea[nombre] ?? (defColumna.defecto ?? '');
          const control = this.campo(nombre, defColumna, valor, soloLectura ? 'ver' : 'nuevo', true);
          controles[nombre] = control;
          control.entrada.addEventListener('input', recalcular);
          control.entrada.addEventListener('change', recalcular);
          tr.append(el('td', { class: ['entero', 'decimal'].includes(defColumna.tipo) ? 'col-num' : null }, control.contenedor));
        });
        columnas.forEach(([, defColumna]) => {
          const regla = defColumna.autollenar;
          if (!regla || soloLectura) return;
          const origen = Object.values(controles).find((c) => c.def === defColumna);
          origen.entrada.addEventListener('change', () => {
            const dato = origen.entrada.selectedOptions?.[0]?.dataset[regla.dato];
            if (dato !== undefined && dato !== '' && controles[regla.campo]) controles[regla.campo].poner(dato);
            recalcular();
          });
        });
        const fila = { tr, controles };
        tr.append(el('td', {}, soloLectura ? null : el('button', {
          class: 'icon-button danger', type: 'button', title: 'Quitar línea', 'aria-label': 'Quitar línea',
          onclick: () => { filas.splice(filas.indexOf(fila), 1); tr.remove(); recalcular(); },
        }, icono('fa-minus-circle'))));
        filas.push(fila);
        cuerpo.append(tr);
        recalcular();
        return fila;
      };

      (Array.isArray(lineas) && lineas.length ? lineas : (soloLectura ? [] : [{}])).forEach((linea) => agregarFila(linea));

      const tabla = el('table', { class: 'detail-table' },
        el('thead', {}, el('tr', {}, columnas.map(([, d]) => el('th', {}, d.etiqueta)), el('th', {}, ''))),
        cuerpo);
      const botonAgregar = soloLectura ? el('span') : el('button', {
        class: 'btn btn-secondary', type: 'button',
        onclick: () => { const fila = agregarFila(); Object.values(fila.controles)[0].enfocar(); },
      }, icono('fa-plus'), 'Agregar línea');

      const contenedor = el('div', { class: 'field full' },
        el('span', { class: 'field-label' }, def.etiqueta, def.requerido ? el('span', { class: 'req' }, ' *') : null),
        el('div', { class: 'table-wrap' }, tabla),
        el('div', { class: 'detail-actions' }, botonAgregar, totalNodo),
        error);

      return {
        def,
        entrada: tabla,
        contenedor,
        obtener: () => filas.map((fila) => Object.fromEntries(Object.entries(fila.controles).map(([n, c]) => [n, c.obtener()]))),
        poner: () => {},
        enfocar: () => (filas[0] ? Object.values(filas[0].controles)[0].enfocar() : botonAgregar.focus?.()),
        validar: () => {
          if (soloLectura) return true;
          if (def.requerido && filas.length === 0) {
            error.textContent = `Agrega al menos una línea en ${def.etiqueta}.`; error.hidden = false;
            return false;
          }
          return filas.every((fila) => Object.values(fila.controles).every((c) => c.validar()));
        },
        marcarError: (mensaje) => { error.textContent = mensaje || ''; error.hidden = !mensaje; },
      };
    }
  }

  new ModuloCrud(config);
})();
