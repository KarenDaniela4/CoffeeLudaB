-- =========================================================================
-- CoffeeLuda — Schema y datos iniciales
-- =========================================================================
-- Reconstruido a partir de los modelos del proyecto PHP original.
-- Uso: importar este archivo desde phpMyAdmin o desde la línea de comandos.
--
--   Línea de comandos:
--     mysql -u root -p < database.sql
--
--   phpMyAdmin:
--     1. Crear la base de datos "coffee" (si no existe)
--     2. Pestaña "Importar" → seleccionar este archivo → Continuar
-- =========================================================================

--CREATE DATABASE IF NOT EXISTS coffee
--  CHARACTER SET utf8mb4
--  COLLATE utf8mb4_unicode_ci;

--USE coffee;

-- -------------------------------------------------------------------------
-- Tabla: roles
-- Define los tipos de usuario del sistema
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id     INT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (id, nombre) VALUES
    (1, 'Administrador'),
    (2, 'Cliente');

-- -------------------------------------------------------------------------
-- Tabla: productos
-- Catálogo de la cafetería
-- -------------------------------------------------------------------------
CREATE TABLE productos (
    IdProducto INT AUTO_INCREMENT PRIMARY KEY,
    Producto   VARCHAR(100)   NOT NULL,
    Precio     DECIMAL(10, 2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO productos (IdProducto, Producto, Precio) VALUES
    (1, 'Espresso',  7000.00),
    (2, 'Capuccino', 9000.00),
    (3, 'Latte',     8000.00);

-- -------------------------------------------------------------------------
-- Tabla: usuarios
-- Cuentas del sistema (admin + clientes)
-- ⚠️  password se guarda en texto plano por compatibilidad con el proyecto
--     original. En producción real, usar password_hash().
-- -------------------------------------------------------------------------
CREATE TABLE usuarios (
    id              BIGINT       PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    apellido        VARCHAR(100) NOT NULL,
    telefono        VARCHAR(10),
    email           VARCHAR(150) NOT NULL UNIQUE,
    password        VARCHAR(100) NOT NULL,
    confirmpassword VARCHAR(100),
    pregunta        VARCHAR(255),
    respuesta       VARCHAR(255),
    id_rol          INT          NOT NULL DEFAULT 2,
    FOREIGN KEY (id_rol) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario administrador de prueba
-- Login: admin@coffeeluda.com / admin123
INSERT INTO usuarios (id, nombre, apellido, telefono, email, password, confirmpassword, pregunta, respuesta, id_rol) VALUES
    (1, 'Admin', 'Coffee', '3000000000', 'admin@coffeeluda.com', 'admin123', 'admin123', 'Color favorito', 'cafe', 1);

-- Usuario cliente de prueba
-- Login: cliente@test.com / cliente123
INSERT INTO usuarios (id, nombre, apellido, telefono, email, password, confirmpassword, pregunta, respuesta, id_rol) VALUES
    (1000000000, 'Cliente', 'Demo', '3001112233', 'cliente@test.com', 'cliente123', 'cliente123', 'Mascota', 'gato', 2);

-- -------------------------------------------------------------------------
-- Tabla: pedidos
-- Cada fila es UNA LÍNEA de un pedido.
-- Varias líneas con el mismo IdPedido = una compra completa.
-- -------------------------------------------------------------------------
CREATE TABLE pedidos (
    IdPedido   INT      NOT NULL,
    IdProducto INT      NOT NULL,
    Cantidad   INT      NOT NULL,
    Total      DECIMAL(10, 2) NOT NULL,
    Fecha      DATETIME NOT NULL,
    id_usuario BIGINT   NOT NULL,
    PRIMARY KEY (IdPedido, IdProducto),
    FOREIGN KEY (IdProducto) REFERENCES productos(IdProducto),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sin datos semilla en pedidos: la app los crea al hacer compras de prueba.
