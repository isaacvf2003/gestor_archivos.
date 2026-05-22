// frontend/app.js
// al principio de app.js
const API_BASE_URL = (window.API_BASE_URL || '').replace(/\/$/, '');

function apiPath(path) {
  const cleanPath = String(path || '').replace(/^\/+/, '');
  return API_BASE_URL ? `${API_BASE_URL}/${cleanPath}` : `../backend/${cleanPath}`;
}

function api(url, options = {}) {
  return fetch(url, { credentials: 'same-origin', ...options });
}

new Vue({
  el: "#app",
  data: {
    user: null,
    heartbeatId: null,
    login: { usuario: '', clave: '' },
    loginError: false,
    usuarios: [],
    showRenameForm: false,
    renameCarpetaNombre: '',
    renameCarpetaSel: null,
    carpetas: [],
    archivos: [],
    archivosTotal: 0,
    ultimosArchivos: [],
    breadcrumb: [],
    usuarioSeleccionado: null,
    carpetaSeleccionada: null,
    carpetaPadre: null,
    showUserForm: false,
    nuevoUsuario: { nombre: '', usuario: '', clave: '', es_admin: false },
    showFolderForm: false,
    nuevaCarpeta: { nombre: '' },
    descripcionArchivo: '',
    page: 'carpetas',
    menuAbierto: false, // Menú hamburguesa móvil
    archivosSeleccionados: [],
    progreso: 0,
    seleccionados: [],



  },
  created() {
    // Mantener sesión tras recarga
   api(apiPath('login.php'), {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ check: true })
  })
      .then(r => r.json()).then(d => {
        if (d.ok) {
          this.user = {nombre: d.nombre, admin: d.admin, id: d.usuario_id};
          this.page = this.user.admin ? 'dashboard' : 'carpetas';
          this.cargarTodo();
          this.startHeartbeat();               // 👈 ARRANCAR heartbeat al restaurar sesión
        }
      });
  },

  mounted() {
    // Cerrar menú si clic fuera del sidebar o menú hamburguesa
    document.addEventListener('click', this.handleClickOutsideMenu);
    // Cerrar con escape
    document.addEventListener('keyup', this.handleEscapeMenu);
  },
  beforeDestroy() {
    document.removeEventListener('click', this.handleClickOutsideMenu);
    document.removeEventListener('keyup', this.handleEscapeMenu);
  },
  methods: {
    

    downloadZipHref() {
      if (!this.carpetaSeleccionada) return '#';
      const params = new URLSearchParams({ id_carpeta: this.carpetaSeleccionada.id });
      if (this.user && this.user.admin && this.usuarioSeleccionado) {
        params.set('id_usuario', this.usuarioSeleccionado.id);
      }
      return `${apiPath('download_zip.php')}?${params.toString()}`;
    },

    downloadFileHref(archivoId) {
      return `${apiPath('download.php')}?id=${encodeURIComponent(archivoId)}`;
    },

    cambiarPagina(p) {
      this.page = p;
      this.menuAbierto = false; // Cerrar menú al navegar
      if (p === 'carpetas') {
        this.carpetaPadre = null;
        this.carpetaSeleccionada = null;
        this.usuarioSeleccionado = null;
        this.breadcrumb = [];
        this.cargarCarpetas();
        this.archivos = [];
      }
      if (p === 'dashboard') this.cargarTodo();
      if (p === 'usuarios') this.cargarUsuarios();
    },

    loginUser() {
     api(apiPath('login.php'), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(this.login)
    })
      .then(r => r.json()).then(d => {
        if (d.ok) {
          this.user = {nombre: d.nombre, admin: d.admin, id: d.usuario_id};
          this.loginError = false;
          this.page = this.user.admin ? 'dashboard' : 'carpetas';
          this.cargarTodo();
          this.startHeartbeat();               // 👈 ARRANCAR heartbeat al loguearse
        } else {
          this.loginError = true;
        }
      });
    },


    logout() {
      api(apiPath('logout.php')).then(() => {
        this.stopHeartbeat();                 // 👈 DETENER heartbeat al salir
        this.user = null;
        this.carpetas = [];
        this.usuarios = [];
        this.archivos = [];
        this.usuarioSeleccionado = null;
        this.carpetaSeleccionada = null;
        this.carpetaPadre = null;
        this.breadcrumb = [];
        this.page = 'carpetas';
        this.menuAbierto = false;
      });
    },

    startHeartbeat() {
      // Un ping cada 5 minutos para mantener viva la sesión mientras la pestaña esté abierta
      if (this.heartbeatId) clearInterval(this.heartbeatId);
      this.heartbeatId = setInterval(() => {
        // Evitamos ruido si no hay usuario logueado
        if (!this.user) return;
        api(apiPath('ping.php'), { method: "POST" })
          .then(r => r.json())
          .then(d => {
            if (!d || d.expired) {
              // Expirada en el servidor
              this.stopHeartbeat();
              this.user = null;
              this.page = 'carpetas';
              alert("Tu sesión expiró por inactividad.");
            }
          })
          .catch(() => {}); // silencioso
      }, 5 * 60 * 1000);
    },
    
    stopHeartbeat() {
      if (this.heartbeatId) {
        clearInterval(this.heartbeatId);
        this.heartbeatId = null;
      }
    },

    cargarTodo() {
      if (this.user.admin) this.cargarUsuarios();
      this.carpetaPadre = null;
      this.breadcrumb = [];
      this.cargarCarpetas();
      this.cargarStats();
      this.cargarUltimosArchivos();
      this.usuarioSeleccionado = null;
      this.archivos = [];
    },

    cargarUsuarios() {
      fetch(apiPath('usuarios.php'))
        .then(r => r.json())
        .then(d => this.usuarios = d);
    },

   cargarCarpetas() {
  let params = [];
  if (this.carpetaPadre && this.carpetaPadre.id) params.push('id_padre='+this.carpetaPadre.id);

  // Si el admin está viendo a un usuario concreto, pásalo.
  if (this.user && this.user.admin && this.usuarioSeleccionado) {
    params.push('id_usuario='+this.usuarioSeleccionado.id);
  }
  const qs = params.length ? '?'+params.join('&') : '';

  fetch(apiPath('carpetas.php')+qs, { credentials:'same-origin' })
    .then(r => r.json())
    .then(d => this.carpetas = d);
},

    cargarStats() {
      fetch(`${apiPath('archivos.php')}?stats=1`)
        .then(r => r.json()).then(d => {
          this.archivosTotal = d.total || 0;
        });
    },

    cargarUltimosArchivos() {
      fetch(`${apiPath('archivos.php')}?ultimos=1`)
        .then(r => r.json()).then(d => {
          this.ultimosArchivos = d || [];
        });
    },
abrirRenombrar(c) {
  this.renameCarpetaSel = c;
  this.renameCarpetaNombre = c.nombre;
  this.showRenameForm = true;
},

renombrarCarpeta() {
  if (!this.renameCarpetaSel) return;
  const nombre = (this.renameCarpetaNombre || '').trim();
  if (!nombre) { alert("Nombre inválido"); return; }

  // Payload básico
  const payload = { id: this.renameCarpetaSel.id, nombre };

  // Si el admin está dentro de un usuario, renombra solo para ese usuario
  if (this.user?.admin && this.usuarioSeleccionado?.id) {
    payload.id_usuario = this.usuarioSeleccionado.id;
  }

  fetch(apiPath('carpetas.php'), {
    method: "PATCH",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(d => {
    if (!d.ok) { alert(d.msg || "No se pudo renombrar"); return; }
    this.showRenameForm = false;
    this.cargarCarpetas();
    if (this.carpetaSeleccionada) this.cargarArchivos();
  })
  .catch(() => alert("Error de red renombrando carpeta"));
},

    crearUsuario() {
    api(apiPath('usuarios.php'), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(this.nuevoUsuario)
    })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { alert(d.msg || "No se pudo crear el usuario"); return; }
      this.showUserForm = false;
      this.cargarUsuarios();
      this.nuevoUsuario = { nombre: '', usuario: '', clave: '', es_admin: false };
    })
    .catch(() => alert("Error de red creando usuario"));
  },


    eliminarUsuario(u) {
      if (confirm("¿Eliminar usuario?"))
        fetch(`${apiPath('usuarios.php')}?id=${u.id}`, { method: "DELETE" })
          .then(() => this.cargarUsuarios());
    },



    crearCarpeta() {
      let data = Object.assign({}, this.nuevaCarpeta);
      if (this.carpetaPadre && this.carpetaPadre.id) data.id_padre = this.carpetaPadre.id;
      fetch(apiPath('carpetas.php'), {
        method: "POST",
        body: JSON.stringify(data)
      }).then(() => {
        this.showFolderForm = false;
        this.cargarCarpetas();
        this.nuevaCarpeta = { nombre: '' };
      });
    },

    eliminarCarpeta(c) {
      if (confirm("¿Eliminar carpeta?"))
        fetch(`${apiPath('carpetas.php')}?id=${c.id}`, { method: "DELETE" })
          .then(() => this.cargarCarpetas());
    },

    seleccionarCarpeta(c) {
      // Agrega a breadcrumb si es nueva
      if (!this.breadcrumb.length || this.breadcrumb[this.breadcrumb.length-1].id !== c.id) {
        this.breadcrumb.push(c);
      }
      this.carpetaPadre = c;
      this.cargarCarpetas();
      this.carpetaSeleccionada = c;
      this.cargarArchivos();
      this.menuAbierto = false; // Cierra menú (móvil)
    },

    volverCarpetaIndex(idx) {
      this.breadcrumb = this.breadcrumb.slice(0, idx + 1);
      this.carpetaPadre = this.breadcrumb[this.breadcrumb.length - 1] || null;
      this.cargarCarpetas();
      this.carpetaSeleccionada = this.carpetaPadre;
      if (this.carpetaPadre) {
        this.cargarArchivos();
      } else {
        this.archivos = [];
        this.carpetaSeleccionada = null;
      }
      this.menuAbierto = false;
    },

    volverRaiz() {
      this.breadcrumb = [];
      this.carpetaPadre = null;
      this.cargarCarpetas();
      this.carpetaSeleccionada = null;
      this.archivos = [];
      this.menuAbierto = false;
    },

    verArchivosDe(u) {
      this.usuarioSeleccionado = u;
      this.page = 'carpetas';
      this.carpetaPadre = null;
      this.carpetaSeleccionada = null;
      this.breadcrumb = [];
      this.cargarCarpetas();
      this.archivos = [];
      this.menuAbierto = false;
    },

    cargarArchivos() {
      if (!this.carpetaSeleccionada) {
        this.archivos = [];
        return;
      }
      let params = `id_carpeta=${this.carpetaSeleccionada.id}`;
      if (this.user.admin && this.usuarioSeleccionado)
        params += `&id_usuario=${this.usuarioSeleccionado.id}`;
      fetch(`${apiPath('archivos.php')}?${params}`)
        .then(r => r.json()).then(d => this.archivos = d);
    },

   subirArchivo() {
      const files = this.$refs.fileInput.files;
      if (!files.length) {
        alert("Selecciona uno o más archivos");
        return;
      }

      const form = new FormData();
      form.append("id_carpeta", this.carpetaSeleccionada.id);
      form.append("descripcion", this.descripcionArchivo);

      for (let i = 0; i < files.length; i++) {
        form.append("archivos[]", files[i]); // múltiple
      }

      const xhr = new XMLHttpRequest();
      xhr.open("POST", apiPath('upload.php'), true);

      // Progreso
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          this.progreso = Math.round((e.loaded / e.total) * 100);
        }
      };

      xhr.onload = () => {
        if (xhr.status === 200) {
          this.cargarArchivos();
          this.descripcionArchivo = '';
          this.$refs.fileInput.value = '';

          // Mantener la barra unos segundos
          setTimeout(() => { this.progreso = 0; }, 1500);
        } else {
          alert("Error al subir");
        }
      };

      xhr.send(form);
    },
    eliminarArchivo(a) {
      if (confirm("¿Eliminar archivo?")) {
        fetch(`${apiPath('archivos.php')}?id=${a.id}`, { method: "DELETE" })
          .then(() => this.cargarArchivos());
      }
    },

    revisarArchivo(a, estado) {
      fetch(apiPath('revisar_archivo.php'), {
        method: "POST",
        body: JSON.stringify({ id_archivo: a.id, estado: estado, comentario: a.comentario_admin })
      }).then(() => this.cargarArchivos());
    },

    guardarComentario(a) {
      fetch(apiPath('revisar_archivo.php'), {
        method: "POST",
        body: JSON.stringify({ id_archivo: a.id, estado: a.estado_revision, comentario: a.comentario_admin })
      }).then(() => this.cargarArchivos());
    },

    actualizarVista() {
      this.cargarCarpetas();
      if (this.carpetaSeleccionada) {
        this.cargarArchivos();
      }
      if (this.user && this.user.admin) {
        this.cargarUsuarios();
        this.cargarStats();
        this.cargarUltimosArchivos();
      }
    },

    // --- MENÚ HAMBURGUESA / OVERLAY --- //
    abrirMenu() {
      this.menuAbierto = true;
    },
    cerrarMenu() {
      this.menuAbierto = false;
    },
    toggleMenu() {
      this.menuAbierto = !this.menuAbierto;
    },
    handleClickOutsideMenu(e) {
      if (!this.menuAbierto) return;
      // Solo cerrar si está abierto y el click NO es en sidebar ni botón hamburguesa
      const sidebar = this.$el.querySelector('.sidebar');
      const menuBtn = this.$el.querySelector('.menu-btn');
      if (
        sidebar && !sidebar.contains(e.target) &&
        menuBtn && !menuBtn.contains(e.target)
      ) {
        this.menuAbierto = false;
      }
    },
    handleEscapeMenu(e) {
      if (this.menuAbierto && e.key === "Escape") this.menuAbierto = false;
    },
    toggleSeleccionTodos(e) {
  if (e.target.checked) {
    this.seleccionados = this.archivos.map(a => a.id);
  } else {
    this.seleccionados = [];
  }
},
borrarSeleccionados() {
  if (!confirm(`¿Seguro que quieres eliminar ${this.seleccionados.length} archivos?`)) return;

  fetch(apiPath('eliminar_multiple.php'), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ ids: this.seleccionados })
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      this.cargarArchivos();
      this.seleccionados = [];
    } else {
      alert(d.error || "No se pudo eliminar");
    }
  });
}

  },

  watch: {
    carpetaSeleccionada() {
      if (this.carpetaSeleccionada) this.cargarArchivos();
    },
    usuarioSeleccionado() {
      if (this.usuarioSeleccionado && this.carpetaSeleccionada)
        this.cargarArchivos();
    }
  },

  filters: {
    fecha(val) {
      if (!val) return '';
      return val.split(' ')[0].split('-').reverse().join('/');
    },
    fechaHora(val) {
      if (!val) return '';
      const d = val.split(' ');
      return d[0].split('-').reverse().join('/') + " " + (d[1]||'').slice(0,5);
    }
  }
});
