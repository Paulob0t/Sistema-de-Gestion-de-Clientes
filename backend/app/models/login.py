from sqlalchemy import Column, Integer, String, DateTime
from datetime import datetime
from app.core.database import Base


class Login(Base):
    __tablename__ = "login"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    usuario = Column(String(50), nullable=False, unique=True, index=True)
    contrasena = Column(String(120), nullable=False)
    contrasena_normal = Column(String(255), nullable=True)
    id_tipo_usuario = Column(Integer, nullable=False, default=0)
    cambio_contrasena = Column(Integer, nullable=False, default=0)
    fecha_actualizacion = Column(DateTime, nullable=True, default=datetime.utcnow, onupdate=datetime.utcnow)

    def __repr__(self) -> str:
        return f"<Login(id={self.id}, usuario='{self.usuario}', tipo={self.id_tipo_usuario})>"
