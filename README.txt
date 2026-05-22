# Gestor documental con flujo de revisión

## Ejecutar **sin Laragon** (solo VS Code + PHP + MySQL)

Esta es la forma recomendada si quieres correr todo directamente desde VS Code.

### 1) Requisitos mínimos

- PHP 8+ instalado (`php -v`)
- Extensión PDO MySQL activa en PHP
- MySQL/MariaDB corriendo
- VS Code

### 2) Abrir el proyecto

1. VS Code → **File > Open Folder**
2. Abrir la carpeta del repo `gestor_archivos.`
3. Abrir terminal integrada en VS Code

### 3) Crear base de datos y tablas

Importa el archivo SQL:

```bash
mysql -u root -p < backup/estructura.sql
```

Ese script crea la DB `gestor_archivos` y tablas (`usuarios`, `carpetas`, `archivos`).

### 4) Configurar conexión DB

Revisa `backend/db.php`:

- host: `localhost`
- db: `gestor_archivos`
- user: `root`
- pass: `""` (vacío)

Si tu entorno usa otra clave/usuario, cámbialo ahí.

### 5) Levantar backend y frontend juntos (un solo comando)

Desde la raíz del repo, en VS Code:

```bash
php -S 127.0.0.1:8000
```

Este comando sirve todo el proyecto (frontend + backend) desde el mismo host/puerto.

### 6) Abrir app en navegador

Usa esta URL:

```text
http://127.0.0.1:8000/frontend/index.html
```

Con la configuración actual, el frontend resuelve automáticamente los endpoints del backend en:

```text
http://127.0.0.1:8000/backend/*.php
```

### 7) Verificación rápida

- Login funciona
- Dashboard admin carga
- Crear carpeta funciona
- Subir archivo funciona
- Descargar archivo/ZIP funciona
- Revisar archivo (`ok/mal/revisando`) funciona

### 8) Problemas comunes

- **`Error de conexión`**: revisar credenciales en `backend/db.php`
- **`php: command not found`**: instalar PHP o agregarlo al PATH
- **Pantalla en blanco / 500**: revisar logs de la terminal donde corre `php -S`
- **No carga datos**: confirmar que MySQL está arriba y `estructura.sql` importado

---

## Nota sobre `API_BASE_URL`

Solo necesitas `window.API_BASE_URL` cuando frontend y backend están en dominios distintos (ej. Vercel + backend remoto).

Para correr local en VS Code **no hace falta** configurarlo.
