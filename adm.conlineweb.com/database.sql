CREATE DATABASE proyectos;
USE proyectos;

CREATE TABLE solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente', 'En Proceso', 'Finalizado') DEFAULT 'Pendiente',
    usuario_asignado VARCHAR(100),
    prioridad ENUM('Alta', 'Media', 'Baja') DEFAULT 'Media'
);
