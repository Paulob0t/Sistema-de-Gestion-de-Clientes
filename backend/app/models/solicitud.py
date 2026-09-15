from sqlalchemy import Column, Integer, String, Text, DateTime
from datetime import datetime
from app.core.database import Base


class Solicitud(Base):
    __tablename__ = "solicitudes"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    id_cliente = Column(Integer, nullable=True, index=True)
    id_proyecto = Column(Integer, nullable=True)
    creado_por_login_id = Column(Integer, nullable=True)
    titulo = Column(String(255), nullable=False)
    descripcion = Column(Text, nullable=True)
    fecha_solicitud = Column(DateTime, nullable=True, default=datetime.utcnow)
    estado = Column(String(50), nullable=False, default="Pendiente", index=True)
    usuario_asignado = Column(String(100), nullable=True)
    prioridad = Column(String(20), nullable=False, default="Media")
    fecha_lim = Column(DateTime, nullable=True)
    fecha_termina = Column(DateTime, nullable=True)
    repetir = Column(Integer, nullable=True, default=0)
    fecha_repeticion = Column(DateTime, nullable=True)
    fecha_ultima_repeticion = Column(DateTime, nullable=True)
    repeticion_original_id = Column(Integer, nullable=True)
    idUsrMSJ = Column(String(100), nullable=True)
    nombreMSJ = Column(String(100), nullable=True)
    validado = Column(Integer, nullable=True, default=0)
    id_solicitud_whatsapp = Column(Integer, nullable=True)

    def __repr__(self) -> str:
        return f"<Solicitud(id={self.id}, titulo='{self.titulo}', estado='{self.estado}', asignado='{self.usuario_asignado}')>"
