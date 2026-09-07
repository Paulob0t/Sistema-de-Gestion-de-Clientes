from sqlalchemy import Column, Integer, String, Text, Date, Time, Numeric, ForeignKey
from app.core.database import Base


class Pago(Base):
    __tablename__ = "pagos"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    id_clie = Column(Integer, index=True, nullable=False)
    id_servicio = Column(Integer, nullable=False, default=0)
    fecha = Column(Date, nullable=False)
    hora = Column(Time, nullable=False)
    fecha_pago = Column(Date, nullable=True)
    hora_pago = Column(Time, nullable=True)
    monto = Column(Numeric(10, 2), nullable=False)
    currency = Column(String(10), nullable=False, default="MXN")
    concepto = Column(Text, nullable=False)
    forma_pago = Column(Integer, nullable=False, default=0)
    estatus = Column(Integer, nullable=False, default=0, index=True)  # 0: Pendiente, 1: Pagado
    id_pago = Column(String(100), nullable=True, default="")
    session_id = Column(Text, nullable=True)
    pago_grupal_id = Column(String(255), nullable=True)
    tipo_pago = Column(String(20), nullable=True, default="individual")
    id_cuenta = Column(Integer, nullable=False, default=0)
    tipo_servicio = Column(Integer, nullable=False, default=0)  # 1: Hosting, 2: Dominio, 0: Manual
    fecha_limite_pago = Column(Date, nullable=True)
    manual = Column(Integer, nullable=False, default=0)
    frecuencia_pago = Column(Integer, nullable=False, default=0)
    pago_recurrente = Column(Integer, nullable=False, default=0)
    Registro = Column(Integer, nullable=False, default=0)
    sistema = Column(String(20), nullable=True, default="conlineweb")

    def __repr__(self) -> str:
        return f"<Pago(id={self.id}, cliente={self.id_clie}, monto={self.monto}, estatus={self.estatus})>"
