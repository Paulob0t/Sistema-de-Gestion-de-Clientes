from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.core.config import settings
from app.api.v1.api import api_router

app = FastAPI(
    title="ConlineWeb CRM API",
    description="Backend API moderno para Gestión de Clientes, Dominios, Hosting y Tickets (Migración FastAPI)",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Configuración de CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS or ["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inclusión de Rutas
app.include_router(api_router, prefix="/api/v1")


@app.get("/api/health", tags=["Estado"])
def health_check():
    return {
        "status": "healthy",
        "env": settings.APP_ENV,
        "database": settings.DB_NAME,
        "api_version": "1.0.0"
    }


@app.get("/", tags=["Root"])
def root():
    return {
        "message": "Bienvenido a ConlineWeb API",
        "docs": "/docs",
        "health": "/api/health"
    }
