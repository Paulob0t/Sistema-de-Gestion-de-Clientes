import hashlib
import hmac
from datetime import datetime, timedelta, timezone
from typing import Optional, Any, Dict
import bcrypt
import jwt
from app.core.config import settings


def hash_password(plain_password: str) -> str:
    """Genera hash bcrypt estándar compatible con PHP y sistemas modernos."""
    salt = bcrypt.gensalt(rounds=10)
    hashed = bcrypt.hashpw(plain_password.encode("utf-8"), salt)
    return hashed.decode("utf-8")


def verify_password(plain_password: str, stored_hash: str) -> bool:
    """
    Verifica contraseñas soportando:
    1. Bcrypt ($2y$, $2a$, $2b$)
    2. MD5 legacy (32 caracteres hexadecimales)
    3. Texto plano legacy (fallback de compatibilidad)
    """
    if not plain_password or not stored_hash:
        return False

    stored = stored_hash.strip()

    # 1. Bcrypt
    if stored.startswith(("$2y$", "$2a$", "$2b$")):
        normalized_hash = stored.replace("$2y$", "$2b$").replace("$2a$", "$2b$")
        try:
            return bcrypt.checkpw(
                plain_password.encode("utf-8"),
                normalized_hash.encode("utf-8")
            )
        except Exception:
            return False

    # 2. Legacy MD5
    if len(stored) == 32 and all(c in "0123456789abcdefABCDEF" for c in stored):
        md5_hash = hashlib.md5(plain_password.encode("utf-8")).hexdigest()
        return hmac.compare_digest(stored.lower(), md5_hash.lower())

    # 3. Fallback de texto plano
    return hmac.compare_digest(stored, plain_password)


def password_needs_rehash(stored_hash: str) -> bool:
    """Determina si una contraseña debe actualizarse a un hash bcrypt moderno."""
    if not stored_hash:
        return True
    stored = stored_hash.strip()
    if stored.startswith(("$2y$", "$2b$")):
        return False
    return True


def create_access_token(
    data: Dict[str, Any],
    expires_delta: Optional[timedelta] = None
) -> str:
    """Genera un token JWT firmado con expiración."""
    to_encode = data.copy()
    now = datetime.now(timezone.utc)
    if expires_delta:
        expire = now + expires_delta
    else:
        expire = now + timedelta(minutes=settings.JWT_ACCESS_TOKEN_EXPIRE_MINUTES)
    
    to_encode.update({
        "exp": expire,
        "iat": now
    })
    
    encoded_jwt = jwt.encode(
        to_encode,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM
    )
    return encoded_jwt


def decode_access_token(token: str) -> Optional[Dict[str, Any]]:
    """Decodifica y valida un token JWT."""
    try:
        payload = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )
        return payload
    except (jwt.PyJWTError, Exception):
        return None
