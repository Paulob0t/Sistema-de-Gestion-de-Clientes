# ConlineWeb CRM - Migración de Monolito a Stack Moderno

Proyecto de modernización arquitectónica del CRM y Portal de Clientes de ConlineWeb, migrado desde un monolito PHP legacy hacia una arquitectura desacoplada y de alto rendimiento.

---

## 🚀 Stack Tecnológico

| Capa | Herramienta | Utilidad |
| :--- | :--- | :--- |
| **Backend** | **FastAPI + Uvicorn** | API asíncrona, rápida y validación de esquemas con Pydantic v2. |
| **ORM / DB Driver** | **SQLAlchemy + PyMySQL** | Mapea las tablas MariaDB/MySQL existentes sin alterar nombres, columnas ni claves. |
| **Frontend** | **Vue 3 (Vite) + TypeScript** | Entorno de desarrollo ultra rápido con tipado seguro para modelos y contratos de API. |
| **UI Kit / Componentes** | **PrimeVue (Aura Preset)** | Componentes avanzados para CRM: formularios, inputs con validación, modales y tablas. |
| **Estilos** | **Tailwind CSS** | Maquetado moderno, responsivo y paleta corporativa de ConlineWeb. |
| **Manejo de Estado** | **Pinia** | Store oficial reactivo para sesión de usuario, tokens JWT y estado compartido. |

---

## 🔒 Seguridad y Manejo de Claves

1. **Variables de Entorno Centralizadas (`.env`)**:
   - Se consolidaron todas las credenciales sensibles (bases de datos MySQL/MariaDB, HostingPro, SMTP Gmail, claves secretas de Stripe, Google reCAPTCHA v2, Facebook Graph API y secretos JWT).
   - El archivo `.env` está estrictamente ignorado en Git.
   - Se incluye un archivo de plantilla [`.env.example`](./.env.example) libre de secretos para facilitar el despliegue.

2. **Sanitización del Monolito Legacy**:
   - Se removieron las contraseñas hardcodeadas de los archivos PHP (`conn.php`, `conn_hostingpro.php`, `conn_old.php`, `smtp_config_helper.php`, `facebook/index.php`, etc.), sustituyéndolas por lectura dinámica desde `getenv(...)` y `$_ENV[...]`.

3. **Protección Git (`.gitignore`)**:
   - Ignora automáticamente archivos `.env`, backups gigantes (`.zip`, `.tar.gz`), logs del servidor, carpetas temporales de uploads/sesiones, `backend/venv/`, y `frontend/node_modules/`.

---

## 🔑 Módulo de Autenticación (Login Migrado)

### Características del Login
- **Compatibilidad 100% con contraseñas existentes**:
  - Verifica hashes **Bcrypt** (`$2y$`, `$2a$`, `$2b$`).
  - Soporta hashes **MD5 legacy** de 32 caracteres.
  - Soporta contraseñas históricas en texto plano.
- **Auto Re-hashing Seguro**: Cuando un usuario con contraseña MD5 o texto plano inicia sesión con éxito, el sistema actualiza su hash en MariaDB automáticamente a **Bcrypt**, elevando progresivamente la seguridad de la base de datos sin afectar a los usuarios.
- **Tokens JWT (Bearer)**: Emisión de tokens firmados (HS256) con expiración configurable (por defecto 24 horas).
- **Mapeo de Roles y Redirección Inteligente**:
  - `0`: Cliente (Portal de Clientes)
  - `1`: Super Administrador (Panel General)
  - `2`: Solicitudes
  - `3`: Agente / Desarrollador
  - `4`: Empresa Externa
  - `5`: Leads

---

## 💻 Instrucciones de Ejecución

### 1. Backend (FastAPI)

```bash
# Entrar al directorio del backend
cd backend

# Activar el entorno virtual
source venv/bin/activate

# Iniciar servidor de desarrollo
python run.py
# O con uvicorn directamente:
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

- **Swagger UI (Documentación interactiva)**: `http://localhost:8000/docs`
- **Redoc**: `http://localhost:8000/redoc`
- **Health check**: `http://localhost:8000/api/health`

### 2. Frontend (Vue 3 + Vite + PrimeVue)

```bash
# Entrar al directorio del frontend
cd frontend

# Iniciar servidor de desarrollo con Vite
npm run dev
```

- **Acceso Local**: `http://localhost:5173/login`
- El servidor Vite incluye un proxy configurado hacia `http://localhost:8000/api` para evitar problemas de CORS durante el desarrollo local.

---

## 🗺️ Roadmap de Migración de Módulos

- [x] **Fase 1**: Aislamiento de secretos (.env, .env.example, .gitignore) y saneamiento de monolito PHP.
- [x] **Fase 2**: Módulo de Login y Autenticación con soporte Bcrypt/MD5 legacy y emisión de JWT en FastAPI + Vue 3 / PrimeVue / Pinia.
- [ ] **Fase 3**: Módulo de Clientes (listado, filtros, creación, edición, vinculación con `login`).
- [ ] **Fase 4**: Módulo de Dominios & Hosting (vencimientos, alertas de renovación, planes HostingPro).
- [ ] **Fase 5**: Módulo de Pagos & Webhooks Stripe (generación de checkout, confirmación automática y notas de pago).
- [ ] **Fase 6**: Sistema de Solicitudes y Tickets para Agentes/Empresas.
