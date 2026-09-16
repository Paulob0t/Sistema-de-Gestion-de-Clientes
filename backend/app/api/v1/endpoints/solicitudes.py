import json
import re
from datetime import datetime, date
from typing import Optional, List, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc, asc, cast, String, case

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.models.solicitud import Solicitud
from app.models.solicitud_nota import SolicitudNota
from app.models.agente import Agente
from app.models.cliente import Cliente
from app.schemas.solicitud import (
    AgenteSimple,
    SolicitudItem,
    SolicitudDetail,
    SolicitudCreate,
    SolicitudUpdate,
    SolicitudStatusUpdate,
    SolicitudAssignUpdate,
    SolicitudNotaItem,
    SolicitudNotaCreate,
    SolicitudesKpis,
    SolicitudesListResponse,
)

router = APIRouter()


def _parse_descripcion(raw: Optional[str]) -> tuple[str, List[str], List[str]]:
    """
    Decodifica la descripción del ticket tolerando JSON estructurado o texto plano legacy.
    """
    if not raw:
        return "", [], []

    raw_str = raw.strip()
    text = raw_str
    images: List[str] = []
    files: List[str] = []

    try:
        data = json.loads(raw_str)
        if isinstance(data, dict):
            text = str(data.get("text", ""))
            raw_imgs = data.get("images") or data.get("imagenes") or []
            if isinstance(raw_imgs, list):
                images = [
                    (x.get("ruta") or x.get("url") or str(x)).strip() if isinstance(x, dict) else str(x).strip()
                    for x in raw_imgs if x
                ]
            raw_fls = data.get("files") or data.get("archivos") or []
            if isinstance(raw_fls, list):
                files = [
                    (x.get("ruta") or x.get("url") or str(x)).strip() if isinstance(x, dict) else str(x).strip()
                    for x in raw_fls if x
                ]
    except Exception:
        # Texto plano: extraer posibles imágenes en markdown ![alt](url)
        matches = re.findall(r'!\[.*?\]\((https?://[^\s)]+|\/[^\s)]+)\)', raw_str)
        if matches:
            images = list(dict.fromkeys(matches))

    return text, images, files


def _get_agentes_dict(db: Session) -> Dict[int, AgenteSimple]:
    """Retorna un diccionario en memoria con todos los agentes activos."""
    agentes = db.query(Agente).all()
    return {a.id: AgenteSimple(id=a.id, nombre=a.nombre, correo=a.correo) for a in agentes}


def _resolve_agentes(assigned_str: Optional[str], agentes_map: Dict[int, AgenteSimple]) -> List[AgenteSimple]:
    """Convierte un string CSV de IDs ('1,3,5') en una lista de objetos AgenteSimple."""
    if not assigned_str:
        return []
    result = []
    parts = assigned_str.split(",")
    for p in parts:
        p_clean = p.strip()
        if p_clean.isdigit():
            aid = int(p_clean)
            if aid in agentes_map:
                result.append(agentes_map[aid])
    return result


def _format_datetime(val: Optional[Any], fmt: str = "%Y-%m-%d %H:%M") -> Optional[str]:
    """Formatea de manera segura un datetime, date o string de base de datos."""
    if not val:
        return None
    if isinstance(val, (datetime, date)):
        return val.strftime(fmt)
    if isinstance(val, str):
        v = val.strip()
        if not v or v.startswith("0000-00-00") or v.lower() in ("none", "null"):
            return None
        clean_v = v.replace("T", " ")
        if len(clean_v) >= 16:
            return clean_v[:16]
        return clean_v
    return None


def _format_date(val: Optional[Any], fmt: str = "%Y-%m-%d") -> Optional[str]:
    """Formatea de manera segura una fecha a YYYY-MM-DD."""
    if not val:
        return None
    if isinstance(val, (datetime, date)):
        return val.strftime(fmt)
    if isinstance(val, str):
        v = val.strip()
        if not v or v.startswith("0000-00-00") or v.lower() in ("none", "null"):
            return None
        if len(v) >= 10:
            return v[:10]
        return v
    return None


def _calculate_dias_restantes(due_val: Optional[Any]) -> Optional[int]:
    """Calcula días restantes hasta la fecha límite."""
    if not due_val:
        return None
    try:
        if isinstance(due_val, datetime):
            return (due_val.date() - date.today()).days
        if isinstance(due_val, date):
            return (due_val - date.today()).days
        if isinstance(due_val, str):
            v = due_val.strip()
            if not v or v.startswith("0000-00-00") or v.lower() in ("none", "null"):
                return None
            parsed = datetime.strptime(v[:10], "%Y-%m-%d").date()
            return (parsed - date.today()).days
    except Exception:
        pass
    return None


@router.get("/agentes", response_model=List[AgenteSimple], summary="Listar agentes disponibles")
def get_agentes_list(
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Retorna lista de todos los agentes registrados para asignar tickets."""
    agentes = db.query(Agente).order_by(Agente.nombre.asc()).all()
    return [AgenteSimple(id=a.id, nombre=a.nombre, correo=a.correo) for a in agentes]


@router.get("/kpis", response_model=SolicitudesKpis, summary="Obtener KPIs de solicitudes")
def get_solicitudes_kpis(
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Retorna métricas globales y personalizadas del módulo de solicitudes."""
    total = db.query(func.count(Solicitud.id)).scalar() or 0
    pendientes = db.query(func.count(Solicitud.id)).filter(Solicitud.estado == "Pendiente").scalar() or 0
    en_proceso = db.query(func.count(Solicitud.id)).filter(Solicitud.estado == "En Proceso").scalar() or 0
    finalizadas = db.query(func.count(Solicitud.id)).filter(Solicitud.estado == "Finalizado").scalar() or 0
    alta_prioridad = db.query(func.count(Solicitud.id)).filter(
        and_(Solicitud.prioridad == "Alta", Solicitud.estado != "Finalizado")
    ).scalar() or 0

    asignadas_a_mi = 0
    if current_user.agente_id:
        aid_str = str(current_user.agente_id)
        asignadas_a_mi = db.query(func.count(Solicitud.id)).filter(
            and_(
                or_(
                    Solicitud.usuario_asignado == aid_str,
                    Solicitud.usuario_asignado.like(f"{aid_str},%"),
                    Solicitud.usuario_asignado.like(f"%,{aid_str},%"),
                    Solicitud.usuario_asignado.like(f"%,{aid_str}")
                ),
                Solicitud.estado != "Finalizado"
            )
        ).scalar() or 0

    return SolicitudesKpis(
        total=total,
        pendientes=pendientes,
        en_proceso=en_proceso,
        finalizadas=finalizadas,
        asignadas_a_mi=asignadas_a_mi,
        alta_prioridad=alta_prioridad,
    )


@router.get("", response_model=SolicitudesListResponse, summary="Listar solicitudes con filtros")
def list_solicitudes(
    search: Optional[str] = Query(None, description="Búsqueda por título, descripción o cliente"),
    estado: Optional[str] = Query("todos", description="Filtro de estado: todos, Pendiente, En Proceso, Finalizado"),
    prioridad: Optional[str] = Query("todos", description="Filtro de prioridad: todos, Alta, Media, Baja"),
    agente_id: Optional[int] = Query(None, description="Filtrar por ID de agente asignado"),
    solo_mias: Optional[bool] = Query(False, description="Mostrar solo solicitudes asignadas al usuario actual"),
    cliente_id: Optional[int] = Query(None, description="Filtrar por cliente"),
    page: int = Query(1, ge=1),
    limit: int = Query(25, ge=1, le=100),
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Lista las solicitudes con paginación, filtros avanzados y conteo de notas.
    Soporta asignación múltiple de agentes y perfiles de Agente/Desarrollador.
    """
    agentes_map = _get_agentes_dict(db)

    # Subquery para contar notas por solicitud
    notas_count_subq = (
        db.query(
            SolicitudNota.solicitud_id,
            func.count(SolicitudNota.id).label("total_notas")
        )
        .group_by(SolicitudNota.solicitud_id)
        .subquery()
    )

    query = (
        db.query(
            Solicitud,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            func.coalesce(notas_count_subq.c.total_notas, 0).label("total_notas"),
        )
        .outerjoin(Cliente, Solicitud.id_cliente == Cliente.id)
        .outerjoin(notas_count_subq, Solicitud.id == notas_count_subq.c.solicitud_id)
    )

    # Filtro por estado
    if estado and estado != "todos":
        query = query.filter(Solicitud.estado == estado)

    # Filtro por prioridad
    if prioridad and prioridad != "todos":
        query = query.filter(Solicitud.prioridad == prioridad)

    # Filtro por cliente
    if cliente_id:
        query = query.filter(Solicitud.id_cliente == cliente_id)

    # Filtro por agente
    target_agente_id = None
    if solo_mias and current_user.agente_id:
        target_agente_id = current_user.agente_id
    elif agente_id:
        target_agente_id = agente_id

    if target_agente_id:
        aid_str = str(target_agente_id)
        query = query.filter(
            or_(
                Solicitud.usuario_asignado == aid_str,
                Solicitud.usuario_asignado.like(f"{aid_str},%"),
                Solicitud.usuario_asignado.like(f"%,{aid_str},%"),
                Solicitud.usuario_asignado.like(f"%,{aid_str}")
            )
        )

    # Filtro por búsqueda de texto
    if search:
        s = f"%{search.strip()}%"
        query = query.filter(
            or_(
                Solicitud.titulo.ilike(s),
                Solicitud.descripcion.ilike(s),
                Cliente.nombre_contacto.ilike(s),
                Cliente.empresa.ilike(s),
            )
        )

    # Ordenamiento: Pendientes y En Proceso primero, luego por id descendente
    query = query.order_by(
        case(
            (Solicitud.estado == "En Proceso", 1),
            (Solicitud.estado == "Pendiente", 2),
            (Solicitud.estado == "Finalizado", 3),
            else_=4,
        ),
        Solicitud.id.desc(),
    )

    # Total general filtrado
    total_count = query.count()

    # Paginación
    offset = (page - 1) * limit
    results = query.offset(offset).limit(limit).all()

    items: List[SolicitudItem] = []
    for sol, c_nom, c_emp, n_count in results:
        t_desc, imgs, fls = _parse_descripcion(sol.descripcion)
        agentes_list = _resolve_agentes(sol.usuario_asignado, agentes_map)
        dias_rest = _calculate_dias_restantes(sol.fecha_lim)

        items.append(
            SolicitudItem(
                id=sol.id,
                titulo=sol.titulo,
                descripcion_texto=t_desc,
                imagenes=imgs,
                archivos=fls,
                fecha_solicitud=sol.fecha_solicitud.strftime("%Y-%m-%d %H:%M") if sol.fecha_solicitud else None,
                estado=sol.estado,
                usuario_asignado=sol.usuario_asignado,
                agentes=agentes_list,
                prioridad=sol.prioridad or "Media",
                id_cliente=sol.id_cliente,
                cliente_nombre=c_nom,
                cliente_empresa=c_emp,
                fecha_lim=sol.fecha_lim.strftime("%Y-%m-%d") if sol.fecha_lim else None,
                fecha_termina=sol.fecha_termina.strftime("%Y-%m-%d %H:%M") if sol.fecha_termina else None,
                dias_restantes=dias_rest,
                total_notas=int(n_count),
            )
        )

    # KPIs rápidos
    kpis = get_solicitudes_kpis(db, current_user)

    return SolicitudesListResponse(
        items=items,
        total=total_count,
        page=page,
        limit=limit,
        kpis=kpis,
    )


@router.get("/{id}", response_model=SolicitudDetail, summary="Detalle de una solicitud")
def get_solicitud(
    id: int,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Retorna la información completa de una solicitud incluyendo notas e historial."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    agentes_map = _get_agentes_dict(db)
    t_desc, imgs, fls = _parse_descripcion(sol.descripcion)
    agentes_list = _resolve_agentes(sol.usuario_asignado, agentes_map)
    dias_rest = _calculate_dias_restantes(sol.fecha_lim)

    # Cliente
    cliente = db.query(Cliente).filter(Cliente.id == sol.id_cliente).first() if sol.id_cliente else None

    # Notas
    notas_raw = (
        db.query(SolicitudNota)
        .filter(SolicitudNota.solicitud_id == id)
        .order_by(SolicitudNota.fecha_creacion.desc())
        .all()
    )

    notas_list: List[SolicitudNotaItem] = []
    for n in notas_raw:
        n_text, n_imgs, _ = _parse_descripcion(n.nota)
        notas_list.append(
            SolicitudNotaItem(
                id=n.id,
                solicitud_id=n.solicitud_id,
                autor=n.autor,
                nota_texto=n_text,
                imagenes=n_imgs,
                fecha_creacion=n.fecha_creacion.strftime("%Y-%m-%d %H:%M") if n.fecha_creacion else None,
            )
        )

    return SolicitudDetail(
        id=sol.id,
        titulo=sol.titulo,
        descripcion_texto=t_desc,
        imagenes=imgs,
        archivos=fls,
        fecha_solicitud=sol.fecha_solicitud.strftime("%Y-%m-%d %H:%M") if sol.fecha_solicitud else None,
        estado=sol.estado,
        usuario_asignado=sol.usuario_asignado,
        agentes=agentes_list,
        prioridad=sol.prioridad or "Media",
        id_cliente=sol.id_cliente,
        cliente_nombre=cliente.nombre_contacto if cliente else None,
        cliente_empresa=cliente.empresa if cliente else None,
        fecha_lim=sol.fecha_lim.strftime("%Y-%m-%d") if sol.fecha_lim else None,
        fecha_termina=sol.fecha_termina.strftime("%Y-%m-%d %H:%M") if sol.fecha_termina else None,
        dias_restantes=dias_rest,
        total_notas=len(notas_list),
        notas=notas_list,
    )


@router.post("", response_model=SolicitudDetail, status_code=status.HTTP_201_CREATED, summary="Crear solicitud")
def create_solicitud(
    payload: SolicitudCreate,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Crea una nueva solicitud con soporte de múltiples agentes asignados."""
    # Convertir lista de agentes a CSV
    assigned_csv = ",".join(str(aid) for aid in payload.agentes_ids) if payload.agentes_ids else None

    # Parsear fecha límite
    fecha_limite = None
    if payload.fecha_lim:
        try:
            fecha_limite = datetime.strptime(payload.fecha_lim[:10], "%Y-%m-%d")
        except Exception:
            pass

    # Almacenar descripción en formato JSON estructurado
    desc_json = json.dumps({"text": payload.descripcion or "", "images": [], "files": []}, ensure_ascii=False)

    nueva = Solicitud(
        titulo=payload.titulo.strip(),
        descripcion=desc_json,
        id_cliente=payload.id_cliente,
        creado_por_login_id=current_user.id,
        usuario_asignado=assigned_csv,
        prioridad=payload.prioridad or "Media",
        fecha_lim=fecha_limite,
        repetir=payload.repetir or 0,
        estado="Pendiente",
        fecha_solicitud=datetime.now(),
    )

    db.add(nueva)
    db.commit()
    db.refresh(nueva)

    return get_solicitud(nueva.id, db, current_user)


@router.put("/{id}", response_model=SolicitudDetail, summary="Actualizar solicitud")
def update_solicitud(
    id: int,
    payload: SolicitudUpdate,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Actualiza los datos generales de una solicitud."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    if payload.titulo is not None:
        sol.titulo = payload.titulo.strip()

    if payload.descripcion is not None:
        _, curr_imgs, curr_fls = _parse_descripcion(sol.descripcion)
        sol.descripcion = json.dumps({
            "text": payload.descripcion,
            "images": curr_imgs,
            "files": curr_fls
        }, ensure_ascii=False)

    if payload.id_cliente is not None:
        sol.id_cliente = payload.id_cliente

    if payload.prioridad is not None:
        sol.prioridad = payload.prioridad

    if payload.estado is not None:
        sol.estado = payload.estado
        if payload.estado == "Finalizado" and not sol.fecha_termina:
            sol.fecha_termina = datetime.now()
        elif payload.estado != "Finalizado":
            sol.fecha_termina = None

    if payload.agentes_ids is not None:
        sol.usuario_asignado = ",".join(str(aid) for aid in payload.agentes_ids) if payload.agentes_ids else None

    if payload.fecha_lim is not None:
        try:
            sol.fecha_lim = datetime.strptime(payload.fecha_lim[:10], "%Y-%m-%d") if payload.fecha_lim else None
        except Exception:
            pass

    db.commit()
    db.refresh(sol)

    return get_solicitud(sol.id, db, current_user)


@router.patch("/{id}/estado", response_model=SolicitudDetail, summary="Cambiar estado de solicitud")
def update_solicitud_status(
    id: int,
    payload: SolicitudStatusUpdate,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Cambia el estado del ticket (Pendiente -> En Proceso -> Finalizado)."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    sol.estado = payload.estado
    if payload.estado == "Finalizado":
        sol.fecha_termina = datetime.now()
    else:
        sol.fecha_termina = None

    db.commit()
    db.refresh(sol)

    return get_solicitud(sol.id, db, current_user)


@router.patch("/{id}/asignar", response_model=SolicitudDetail, summary="Asignar agentes a la solicitud")
def assign_solicitud_agents(
    id: int,
    payload: SolicitudAssignUpdate,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Asigna uno o varios agentes a una solicitud existente."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    sol.usuario_asignado = ",".join(str(aid) for aid in payload.agentes_ids) if payload.agentes_ids else None
    db.commit()
    db.refresh(sol)

    return get_solicitud(sol.id, db, current_user)


@router.delete("/{id}", status_code=status.HTTP_200_OK, summary="Eliminar solicitud")
def delete_solicitud(
    id: int,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Elimina una solicitud y sus notas asociadas."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    db.query(SolicitudNota).filter(SolicitudNota.solicitud_id == id).delete()
    db.delete(sol)
    db.commit()

    return {"status": "ok", "message": f"Solicitud #{id} eliminada correctamente"}


@router.post("/{id}/notas", response_model=SolicitudNotaItem, status_code=status.HTTP_201_CREATED, summary="Agregar nota a la solicitud")
def add_solicitud_nota(
    id: int,
    payload: SolicitudNotaCreate,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """Agrega un comentario / nota interna a una solicitud."""
    sol = db.query(Solicitud).filter(Solicitud.id == id).first()
    if not sol:
        raise HTTPException(status_code=404, detail="Solicitud no encontrada")

    autor_nombre = current_user.nombre or current_user.usuario or "Agente"
    nota_json = json.dumps({
        "text": payload.nota.strip(),
        "images": payload.imagenes or []
    }, ensure_ascii=False)

    nueva_nota = SolicitudNota(
        solicitud_id=id,
        autor=autor_nombre,
        nota=nota_json,
        fecha_creacion=datetime.now(),
    )

    db.add(nueva_nota)
    db.commit()
    db.refresh(nueva_nota)

    return SolicitudNotaItem(
        id=nueva_nota.id,
        solicitud_id=nueva_nota.solicitud_id,
        autor=nueva_nota.autor,
        nota_texto=payload.nota.strip(),
        imagenes=payload.imagenes or [],
        fecha_creacion=nueva_nota.fecha_creacion.strftime("%Y-%m-%d %H:%M") if nueva_nota.fecha_creacion else None,
    )
