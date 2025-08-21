-- Crear la base de datos (si no existe)
CREATE DATABASE IF NOT EXISTS gestor_archivos DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestor_archivos;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  clave_hash VARCHAR(255) NOT NULL,
  es_admin BOOLEAN NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de carpetas (estructura anidada opcional)
CREATE TABLE IF NOT EXISTS carpetas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  id_padre INT DEFAULT NULL,
  FOREIGN KEY (id_padre) REFERENCES carpetas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de archivos
CREATE TABLE IF NOT EXISTS archivos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_archivo VARCHAR(255) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  id_usuario INT NOT NULL,
  id_carpeta INT NOT NULL,
  descripcion TEXT,
  fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
  estado_revision ENUM('revisando','ok','mal') DEFAULT 'revisando',
  comentario_admin TEXT,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  FOREIGN KEY (id_carpeta) REFERENCES carpetas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS carpetas_alias (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  carpeta_id   INT NOT NULL,
  user_id      INT NOT NULL,
  nombre_alias VARCHAR(255) NOT NULL,
  UNIQUE KEY uq_alias (carpeta_id, user_id)
);

