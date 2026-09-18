from typing import Optional
from pydantic import BaseModel, Field


ROLE_NAMES = {
    0: "Cliente",
    1: "Super Administrador",
    2: "Solicitudes",
    3: "Agente / Desarrollador",
    4: "Empresa Externa",
    5: "Leads"
}

ROLE_REDIRECTS = {
    0: "/portal",
    1: "/dashboard",
    2: "/solicitudes",
    3: "/solicitudes",
    4: "/solicitudes",
    5: "/dashboard"
}


class LoginRequest(BaseModel):
    usuario: str = Field(..., min_length=1, description="Nombre de usuario o correo")
    contrasena: str = Field(..., min_length=1, description="Contraseña de acceso")


class UserProfile(BaseModel):
    id: int
    usuario: str
    id_tipo_usuario: int
    rol: str
    nombre: Optional[str] = None
    correo: Optional[str] = None
    empresa: Optional[str] = None
    agente_id: Optional[int] = None

    class Config:
        from_attributes = True


class LoginResponse(BaseModel):
    status: str = "ok"
    access_token: str
    token_type: str = "bearer"
    expires_in_minutes: int
    redirect: str
    user: UserProfile


class LogoutResponse(BaseModel):
    status: str = "ok"
    message: str
