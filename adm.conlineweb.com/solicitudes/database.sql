CREATE DATABASE proyectos;
USE proyectos;

CREATE TABLE solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente', 'En Proceso', 'Finalizado') DEFAULT 'Pendiente',
    cliente_id INT NULL,
    usuario_asignado VARCHAR(100),
    prioridad ENUM('Alta', 'Media', 'Baja') DEFAULT 'Media'
);

-- Notas por solicitud
CREATE TABLE IF NOT EXISTS solicitudes_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    autor VARCHAR(50) NOT NULL, -- 'desarrollador' | 'jefe' u otro identificador
    nota TEXT NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (solicitud_id)
);
