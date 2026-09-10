from fastapi import APIRouter
from app.api.v1.endpoints import auth, dashboard, clientes, dominios, hostings

api_router = APIRouter()
api_router.include_router(auth.router, prefix="/auth", tags=["Autenticación"])
api_router.include_router(dashboard.router, prefix="/dashboard", tags=["Dashboard"])
api_router.include_router(clientes.router, prefix="/clientes", tags=["Clientes"])
api_router.include_router(dominios.router, prefix="/dominios", tags=["Dominios"])
api_router.include_router(hostings.router, prefix="/hostings", tags=["Hostings"])



