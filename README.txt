# Gestor documental con flujo de revisión

Este proyecto está pensado como un **gestor documental con flujo de revisión**:

- El **admin** crea usuarios y estructura de carpetas.
- El **usuario** sube documentos/fotos a su carpeta asignada.
- El **admin** revisa cada archivo, cambia estado y deja comentarios.

---

## 1) Qué es (y cómo venderlo en portafolio)

No lo presentes como “Dropbox clone”.
Preséntalo como:

> **Plataforma documental con control por roles, trazabilidad de revisión y aprobación administrativa.**

Eso te posiciona mejor porque demuestra:

- arquitectura full-stack,
- autenticación + sesiones,
- permisos por rol,
- procesos de negocio (revisión y feedback),
- gestión de archivos en servidor.

---

## 2) Flujo funcional ideal (tu caso real)

### Flujo principal

1. Admin crea usuario.
2. Admin crea carpetas (raíz y subcarpetas).
3. Usuario inicia sesión.
4. Usuario sube documentos/imágenes.
5. Admin entra al panel, revisa archivos pendientes.
6. Admin marca estado: `revisando`, `ok`, `mal`.
7. Admin deja comentario.
8. Usuario vuelve y ve el resultado de revisión.

### Roles

#### Admin

- Crear/editar usuarios.
- Crear/renombrar carpetas.
- Ver archivos por usuario.
- Revisar archivo y dejar comentario.
- Descargar archivos y ZIP por carpeta.

#### Usuario

- Ver solo su contenido autorizado.
- Subir archivos.
- Descargar sus archivos.
- Ver estado de revisión y comentario admin.

---

## 3) Qué ya tienes en este repo (base actual)

A nivel técnico, ya cuentas con pilares importantes:

- Frontend Vue con login/sesión y navegación de módulos.
- Backend PHP con endpoints para login, carpetas, archivos, revisión, descarga y ZIP.
- Base de datos MySQL con tablas de usuarios, carpetas y archivos.
- Estado de revisión de archivo (`revisando`, `ok`, `mal`) y comentario admin.

---

## 4) Qué falta para que sea “completo” (roadmap realista)

## Fase A — Imprescindible (MVP fuerte)

1. **Permisos blindados en backend**
   - Nunca confiar en el frontend para autorizar.
   - Validar en cada endpoint si el usuario puede operar sobre ese recurso.

2. **Bandeja de pendientes para admin**
   - Vista rápida “archivos sin revisar”.
   - Filtros por usuario, fecha y carpeta.

3. **Búsqueda y filtros funcionales**
   - Por nombre, estado, fecha, usuario.

4. **Vista previa de archivos**
   - PDF e imágenes sin descarga obligatoria.

5. **Cuenta demo segura (admin sandbox)**
   - Bloquear acciones destructivas para demos públicas.

## Fase B — Muy recomendable

6. **Auditoría básica**
   - Quién subió, quién revisó, cuándo.

7. **Reglas de validación de subida**
   - extensiones permitidas,
   - tamaño máximo,
   - sanitización del nombre.

8. **Renombrar/mover archivo con reglas claras**
   - preferentemente admin-only al inicio.

9. **Notificaciones**
   - contador de pendientes para admin.

10. **Mensajería de estado más clara en UI**
   - badges y colores consistentes por estado.

## Fase C — Diferenciadores

11. Versionado de archivo.
12. Exportación de reportes (CSV/PDF).
13. Dashboard KPI (aprobados/rechazados/pendientes por rango).

---

## 5) Publicación en Vercel (sin romper nada)

### Recomendación de despliegue

- **Frontend**: Vercel.
- **Backend**: hosting PHP tradicional (Railway/Render con PHP, VPS, hosting cPanel, etc.).
- **DB**: MySQL gestionada.

### Importante

En el frontend todavía se observan rutas relativas tipo `../backend/...`; para producción conviene usar una variable `API_BASE_URL` para apuntar al backend desplegado.

---

## 6) Demo pública segura (admin + user)

Crea dos cuentas públicas:

1. `demo.user`
2. `demo.admin`

Y para `demo.admin`:

- bloquear borrados destructivos,
- mantener habilitado revisar/comentar,
- resetear datos de demo cada cierto tiempo.

Así todos prueban el producto sin riesgo.

---

## 7) Guion de demo para tu portafolio

1. Login como `demo.user`.
2. Subir archivo y mostrar carpeta.
3. Logout.
4. Login como `demo.admin`.
5. Abrir pendientes, revisar y comentar.
6. Volver como `demo.user` y mostrar resultado.

Con ese guion se entiende perfecto el valor del proyecto.

---

## 8) Cómo correrlo en VS Code (paso a paso)

> Objetivo: correr frontend + backend PHP + MySQL en local.

### 8.1 Requisitos

- VS Code instalado.
- PHP 8+ (o XAMPP/WAMP/MAMP).
- MySQL/MariaDB.
- Navegador.

### 8.2 Abrir el proyecto

1. Abrir VS Code.
2. **File → Open Folder**.
3. Seleccionar la carpeta del repo (`gestor_archivos.`).
4. Abrir terminal integrada en VS Code.

### 8.3 Crear la base de datos

1. En tu MySQL local, ejecutar el SQL de `backup/estructura.sql`.
2. Verificar que se creó la DB `gestor_archivos` y sus tablas.

### 8.4 Configurar conexión de backend

1. Revisar `backend/db.php` / `backend/config.php`.
2. Ajustar host, usuario, password y base de datos según tu entorno local.
3. Confirmar que PHP puede leer/escribir en carpetas de almacenamiento.

### 8.5 Levantar backend

Desde la raíz del repo, en terminal de VS Code:

```bash
php -S 127.0.0.1:8000
```

Si prefieres Apache/XAMPP, también sirve; solo apunta el frontend al backend correcto.

### 8.6 Abrir frontend

Opciones:

- Abrir `frontend/index.html` directamente en navegador.
- O usar una extensión como **Live Server** para servir `frontend/`.

### 8.7 Probar login y flujo

1. Entrar a la app.
2. Login admin.
3. Crear usuario/carpeta.
4. Login usuario.
5. Subir archivo.
6. Volver admin y revisar.

---

## 9) Checklist de verificación local

- [ ] Login funciona.
- [ ] Sesión se mantiene al recargar.
- [ ] Admin ve usuarios y carpetas.
- [ ] Usuario no ve datos de otros.
- [ ] Subida y descarga funcionan.
- [ ] Revisión y comentario se guardan.
- [ ] ZIP por carpeta funciona.

---

## 10) Próximo paso recomendado (si quieres te lo implemento)

Primero haría este cambio técnico sin romper UX:

1. Centralizar `API_BASE_URL` en frontend.
2. Reemplazar llamadas `../backend/...` por `API_BASE_URL + endpoint`.
3. Ajustar CORS/cookies para entorno Vercel + backend externo.

Con eso quedas listo para demo pública profesional.
