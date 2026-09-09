# 🚀 NexusBot CRM - Suite Cloud Empresarial

Sistema de Gestión de Clientes, Cobranza e Infraestructura Cloud, modernizado hacia una arquitectura desacoplada de alto rendimiento y diseño corporativo de última generación.

---

## 🛠️ Stack Tecnológico

| Capa | Herramienta | Utilidad |
| :--- | :--- | :--- |
| **Backend** | **FastAPI + Uvicorn** | API asíncrona de alto rendimiento, documentación interactiva OpenAPI y esquemas Pydantic v2. |
| **ORM / DB Driver** | **SQLAlchemy + PyMySQL** | Mapeo relacional de base de datos MariaDB/MySQL con soporte multi-sistema y pool de conexiones optimizado. |
| **Frontend** | **Vue 3 (Vite) + TypeScript** | Entorno ultra rápido con tipado estricto y componentes reactivos. |
| **UI Kit / Componentes** | **PrimeVue (Aura Theme) + PrimeIcons** | Componentes avanzados de CRM: tablas interactivas, modales, filtros, badges y acordeones. |
| **Estilos & Diseño** | **Tailwind CSS** | Diseño ejecutivo oscuro (*Executive Dark UI*), transiciones fluidas y 100% responsivo para móviles, tablets y monitores ultra-wide. |
| **Manejo de Estado** | **Pinia** | Store centralizado para sesión, permisos de usuario y tokens JWT. |

---

## 🔒 Seguridad y Configuración de Entorno

1. **Variables de Entorno Centralizadas (`.env`)**:
   - Todas las credenciales sensibles (bases de datos, pasarelas de pago Stripe, SMTP transaccional, APIs de mensajería y llaves JWT) están centralizadas en el archivo `.env`.
   - El repositorio incluye un archivo seguro [`.env.example`](./.env.example) para despliegues en nuevos entornos.

2. **Autenticación Híbrida & Segura**:
   - Soporte para hashes **Bcrypt** (`$2y$`, `$2a$`, `$2b$`) con compatibilidad legacy y auto-actualización progresiva (*auto-rehash*).
   - Tokens de acceso **JWT Bearer** (HS256) con expiración controlada y validación de roles en cada endpoint.

3. **Control de Acceso por Roles (RBAC)**:
   - `0`: Cliente (Portal de Autoservicio)
   - `1`: Super Administrador (Acceso Total a la Suite)
   - `2`: Solicitudes & Operaciones
   - `3`: Agente / Desarrollador
   - `4`: Empresa Externa / Partner
   - `5`: Ventas & Leads

---

## 💻 Instrucciones de Ejecución

### 1. Backend (FastAPI)

```bash
# Entrar al directorio del backend
cd backend

# Activar el entorno virtual
source venv/bin/activate

# Iniciar servidor de desarrollo
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

- **Documentación Swagger UI**: `http://localhost:8000/docs`
- **Documentación Redoc**: `http://localhost:8000/redoc`
- **Health Check**: `http://localhost:8000/api/health`

### 2. Frontend (Vue 3 + Vite)

```bash
# Entrar al directorio del frontend
cd frontend

# Iniciar servidor de desarrollo
npm run dev -- --host 0.0.0.0
```

- **Acceso Local**: `http://localhost:5180`
- Configurado con puerto dedicado `5180` y proxy API integrado.

---

## 🗺️ Roadmap de Módulos

- [x] **Fase 1**: Aislamiento de secretos y sanitización de seguridad (`.env`, `.env.example`, `.gitignore`).
- [x] **Fase 2**: Módulo de Autenticación & Login Ejecutivo (FastAPI + JWT + Bcrypt + Vue 3 / Pinia).
- [x] **Fase 3**: Dashboard Ejecutivo (KPIs, Cobranza Pendiente, Historial Financiero, Distribución de Servicios).
- [x] **Fase 4**: Barra de Navegación Lateral (*Sidebar*) colapsable y responsiva.
- [x] **Fase 5**: Módulo de Consulta de Clientes con filtros avanzados, métricas agregadas (Dominios, Hosting, Pagos) y diseño adaptativo para móviles y escritorio.
- [ ] **Fase 6**: Gestión de Dominios & Planes de Hosting (alertas de expiración y renovación).
- [ ] **Fase 7**: Registro de Pagos & Conciliación con Pasarelas.
- [ ] **Fase 8**: Módulo de Tickets & Mesa de Soporte.
