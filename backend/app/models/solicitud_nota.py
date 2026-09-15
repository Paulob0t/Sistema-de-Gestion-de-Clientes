from sqlalchemy import Column, Integer, String, Text, DateTime
from datetime import datetime
from app.core.database import Base


class SolicitudNota(Base):
    __tablename__ = "solicitudes_notas"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    solicitud_id = Column(Integer, nullable=False, index=True)
    autor = Column(String(50), nullable=False)
    nota = Column(Text, nullable=False)
    fecha_creacion = Column(DateTime, nullable=True, default=datetime.utcnow)

    def __repr__(self) -> str:
        return f"<SolicitudNota(id={self.id}, solicitud_id={self.solicitud_id}, autor='{self.autor}')>"
