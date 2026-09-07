from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.orm import Session
from datetime import datetime

from app.core.database import get_db
from app.core.config import settings
from app.core.security import (
    verify_password,
    hash_password,
    password_needs_rehash,
    create_access_token,
    decode_access_token
)
from app.models.login import Login
from app.models.cliente import Cliente
from app.models.agente import Agente
from app.schemas.auth import (
    LoginRequest,
    LoginResponse,
    LogoutResponse,
    UserProfile,
    ROLE_NAMES,
    ROLE_REDIRECTS
)

router = APIRouter()
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/v1/auth/login")


def get_current_user(
    token: str = Depends(oauth2_scheme),
    db: Session = Depends(get_db)
) -> UserProfile:
    """Dependency para validar el token JWT y retornar el perfil del usuario autenticado."""
    payload = decode_access_token(token)
    if not payload:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token inválido o expirado",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    user_id = payload.get("sub")
    if not user_id:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credenciales de token no válidas",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    user = db.query(Login).filter(Login.id == int(user_id)).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Usuario no encontrado",
        )

    # Buscar información asociada según el rol
    nombre = user.usuario
    correo = None
    empresa = None
    agente_id = None

    if user.id_tipo_usuario == 0:
        cliente = db.query(Cliente).filter(Cliente.id == user.id).first()
        if cliente:
            nombre = cliente.nombre_contacto or user.usuario
            correo = cliente.correo
            empresa = cliente.empresa
    elif user.id_tipo_usuario in (1, 2, 3, 4, 5):
        agente = db.query(Agente).filter(Agente.Idusu == user.id).first()
        if agente:
            nombre = agente.nombre or user.usuario
            correo = agente.correo
            agente_id = agente.id

    return UserProfile(
        id=user.id,
        usuario=user.usuario,
        id_tipo_usuario=user.id_tipo_usuario,
        rol=ROLE_NAMES.get(user.id_tipo_usuario, "Usuario"),
        nombre=nombre,
        correo=correo,
        empresa=empresa,
        agente_id=agente_id
    )


@router.post("/login", response_model=LoginResponse, summary="Iniciar Sesión")
def login(
    request: LoginRequest,
    db: Session = Depends(get_db)
):
    """
    Autenticación compatible con el monolito de PHP:
    - Valida credenciales contra tabla `login`
    - Soporta contraseñas bcrypt, MD5 legacy y texto plano
    - Actualiza hashes legacy automáticamente a bcrypt seguro
    - Genera token JWT de sesión
    - Retorna rol, datos de perfil y ruta de redirección
    """
    username = request.usuario.strip()
    if not username or not request.contrasena:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Usuario y contraseña son requeridos"
        )

    # Buscar usuario en tabla login
    user = db.query(Login).filter(Login.usuario == username).first()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Usuario o contraseña incorrectos"
        )

    # Verificar contraseña (bcrypt, md5 o plain)
    if not verify_password(request.contrasena, user.contrasena):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Usuario o contraseña incorrectos"
        )

    # Re-hash automático si la contraseña estaba en MD5 o plain text
    if password_needs_rehash(user.contrasena):
        try:
            user.contrasena = hash_password(request.contrasena)
            user.fecha_actualizacion = datetime.utcnow()
            db.commit()
            db.refresh(user)
        except Exception:
            db.rollback()

    # Obtener detalles del perfil (Cliente o Agente)
    nombre = user.usuario
    correo = None
    empresa = None
    agente_id = None

    if user.id_tipo_usuario == 0:
        cliente = db.query(Cliente).filter(Cliente.id == user.id).first()
        if cliente:
            nombre = cliente.nombre_contacto or user.usuario
            correo = cliente.correo
            empresa = cliente.empresa
    else:
        agente = db.query(Agente).filter(Agente.Idusu == user.id).first()
        if agente:
            nombre = agente.nombre or user.usuario
            correo = agente.correo
            agente_id = agente.id

    # Generar JWT Token
    token_data = {
        "sub": str(user.id),
        "usuario": user.usuario,
        "tipo": user.id_tipo_usuario,
        "agente_id": agente_id
    }
    access_token = create_access_token(token_data)

    user_profile = UserProfile(
        id=user.id,
        usuario=user.usuario,
        id_tipo_usuario=user.id_tipo_usuario,
        rol=ROLE_NAMES.get(user.id_tipo_usuario, "Usuario"),
        nombre=nombre,
        correo=correo,
        empresa=empresa,
        agente_id=agente_id
    )

    redirect_path = ROLE_REDIRECTS.get(user.id_tipo_usuario, "/portal/clientes")

    return LoginResponse(
        status="ok",
        access_token=access_token,
        token_type="bearer",
        expires_in_minutes=settings.JWT_ACCESS_TOKEN_EXPIRE_MINUTES,
        redirect=redirect_path,
        user=user_profile
    )


@router.get("/me", response_model=UserProfile, summary="Perfil del usuario actual")
def get_me(current_user: UserProfile = Depends(get_current_user)):
    """Obtiene los datos del usuario conectado mediante su token JWT."""
    return current_user


@router.post("/logout", response_model=LogoutResponse, summary="Cerrar Sesión")
def logout():
    """Confirma el cierre de sesión."""
    return LogoutResponse(
        status="ok",
        message="Sesión cerrada correctamente"
    )
