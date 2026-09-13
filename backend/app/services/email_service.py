import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from typing import Optional, Tuple, Dict, Any
import logging
from app.core.config import settings

logger = logging.getLogger(__name__)


def send_email(
    to_email: str,
    subject: str,
    html_content: str,
    text_content: Optional[str] = None,
    from_name: Optional[str] = None,
    from_email: Optional[str] = None,
) -> Tuple[bool, str]:
    """
    Envía un correo electrónico HTML/Texto vía SMTP.
    """
    if not to_email or "@" not in to_email:
        return False, "Dirección de correo de destinatario inválida"

    sender_email = from_email or settings.SMTP_FROM_EMAIL or settings.SMTP_USER
    sender_name = from_name or settings.SMTP_FROM_NAME or "ConlineWeb CRM"

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{sender_name} <{sender_email}>"
    msg["To"] = to_email

    # Versión de texto plano de respaldo
    if not text_content:
        text_content = "Por favor visualice este mensaje en un cliente de correo compatible con HTML."

    part_text = MIMEText(text_content, "plain", "utf-8")
    part_html = MIMEText(html_content, "html", "utf-8")

    msg.attach(part_text)
    msg.attach(part_html)

    try:
        server = smtplib.SMTP(settings.SMTP_HOST, settings.SMTP_PORT, timeout=15)
        if settings.SMTP_SECURE.lower() in ("tls", "starttls") or settings.SMTP_PORT == 587:
            server.starttls()

        if settings.SMTP_AUTH and settings.SMTP_USER and settings.SMTP_PASS:
            server.login(settings.SMTP_USER, settings.SMTP_PASS)

        server.sendmail(sender_email, [to_email], msg.as_string())
        server.quit()
        return True, f"Correo enviado exitosamente a {to_email}"
    except smtplib.SMTPAuthenticationError as e:
        logger.error(f"Error de autenticación SMTP: {e}")
        return False, "Error de autenticación con el servidor de correo"
    except Exception as e:
        logger.error(f"Error al enviar correo a {to_email}: {e}")
        return False, f"Error al enviar correo: {str(e)}"


def test_smtp_connection() -> Dict[str, Any]:
    """
    Verifica la conexión y autenticación con el servidor SMTP.
    """
    try:
        server = smtplib.SMTP(settings.SMTP_HOST, settings.SMTP_PORT, timeout=10)
        if settings.SMTP_SECURE.lower() in ("tls", "starttls") or settings.SMTP_PORT == 587:
            server.starttls()

        if settings.SMTP_AUTH and settings.SMTP_USER and settings.SMTP_PASS:
            server.login(settings.SMTP_USER, settings.SMTP_PASS)

        server.quit()
        return {
            "success": True,
            "message": "Conexión SMTP exitosa",
            "host": settings.SMTP_HOST,
            "port": settings.SMTP_PORT,
            "user": settings.SMTP_USER,
        }
    except Exception as e:
        return {
            "success": False,
            "message": f"Error de conexión SMTP: {str(e)}",
            "host": settings.SMTP_HOST,
            "port": settings.SMTP_PORT,
            "user": settings.SMTP_USER,
        }


def _email_base_wrapper(title: str, content_html: str, badge_text: str = "Recordatorio Oficial") -> str:
    """
    Envoltura base responsiva y con diseño moderno para todos los correos.
    """
    return f"""<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #e2e8f0;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #0b1120; padding: 40px 15px;">
    <tr>
      <td align="center">
        <!-- Contenedor Principal -->
        <table width="100%" max-width="600" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #111827; border: 1px solid #1f2937; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);">
          
          <!-- Encabezado / Branding -->
          <tr>
            <td style="padding: 30px 35px 20px; background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%); border-bottom: 1px solid #1f2937;">
              <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td>
                    <span style="display: inline-block; font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                      NEXUS<span style="color: #3b82f6;">BOT</span>
                    </span>
                    <span style="display: block; font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 2px;">
                      Cloud CRM & Hosting Services
                    </span>
                  </td>
                  <td align="right">
                    <span style="display: inline-block; padding: 4px 10px; background-color: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 20px; font-size: 11px; font-weight: 600; color: #60a5fa;">
                      {badge_text}
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Contenido Central -->
          <tr>
            <td style="padding: 35px;">
              {content_html}
            </td>
          </tr>

          <!-- Pie de Página -->
          <tr>
            <td style="padding: 24px 35px; background-color: #0b0f19; border-top: 1px solid #1f2937; text-align: center;">
              <p style="margin: 0 0 8px; font-size: 12px; color: #64748b; line-height: 1.5;">
                Este es un mensaje automático generado por nuestro sistema de gestión.
              </p>
              <p style="margin: 0; font-size: 11px; color: #475569;">
                &copy; 2026 ConlineWeb & NexusBot. Todos los derechos reservados.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>"""


def build_pago_email(
    cliente_nombre: str,
    concepto: str,
    monto: float,
    currency: str = "MXN",
    fecha_limite: Optional[str] = None,
    folio: Optional[str] = None,
    session_url: Optional[str] = None,
) -> Tuple[str, str]:
    """
    Genera el asunto y el HTML para el recordatorio de pago pendiente.
    """
    subject = f"Recordatorio de Pago: {concepto} ({currency} ${monto:,.2f})"
    fecha_limite_fmt = fecha_limite or "A la brevedad"

    content = f"""
      <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #ffffff;">
        Estimado/a {cliente_nombre},
      </h2>
      <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1;">
        Le enviamos un cordial recordatorio respecto a su cobro pendiente por concepto de <strong>{concepto}</strong>. A continuación le compartimos los detalles para su liquidación:
      </p>

      <!-- Tarjeta de Resumen -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; margin-bottom: 24px;">
        <tr>
          <td style="padding: 16px 20px; border-bottom: 1px solid #334155;">
            <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Concepto del Servicio</span>
            <div style="font-size: 15px; font-weight: 600; color: #f8fafc; margin-top: 4px;">{concepto}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 16px 20px; border-bottom: 1px solid #334155;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Monto Total</span>
                  <div style="font-size: 22px; font-weight: 800; color: #38bdf8; margin-top: 4px;">
                    ${monto:,.2f} <span style="font-size: 13px; font-weight: 600; color: #94a3b8;">{currency}</span>
                  </div>
                </td>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Fecha Límite</span>
                  <div style="font-size: 15px; font-weight: 600; color: #f59e0b; margin-top: 4px;">{fecha_limite_fmt}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        {"<tr><td style='padding: 12px 20px;'><span style='font-size: 12px; color: #94a3b8;'>Folio de Control: <strong>" + folio + "</strong></span></td></tr>" if folio else ""}
      </table>

      <!-- Botón de Pago / Portal si aplica -->
      {f'''
      <div style="text-align: center; margin: 30px 0 20px;">
        <a href="{session_url}" target="_blank" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 700; border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.4);">
          Pagar en Línea Ahora &rarr;
        </a>
      </div>
      ''' if session_url else ""}

      <p style="margin: 24px 0 0; font-size: 13px; line-height: 1.6; color: #94a3b8;">
        Si ya realizó su pago en las últimas 24 horas, por favor haga caso omiso de este mensaje o envíenos su comprobante de transferencia para acreditarlo a la brevedad.
      </p>
    """

    html = _email_base_wrapper(subject, content, badge_text="Recordatorio de Cobro")
    return subject, html


def build_dominio_email(
    cliente_nombre: str,
    url_dominio: str,
    costo: float = 0.0,
    currency: str = "MXN",
    fecha_vencimiento: Optional[str] = None,
    proveedor: Optional[str] = None,
) -> Tuple[str, str]:
    """
    Genera el asunto y el HTML para el recordatorio de renovación de dominio.
    """
    subject = f"Recordatorio de Renovación de Dominio: {url_dominio}"
    fecha_fmt = fecha_vencimiento or "Próximamente"

    content = f"""
      <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #ffffff;">
        Estimado/a {cliente_nombre},
      </h2>
      <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1;">
        Le informamos que su dominio web <strong>{url_dominio}</strong> se encuentra próximo a su fecha de renovación. Es fundamental mantener su registro activo para evitar la suspensión del sitio web y cuentas de correo vinculadas.
      </p>

      <!-- Tarjeta de Dominio -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; margin-bottom: 24px;">
        <tr>
          <td style="padding: 16px 20px; border-bottom: 1px solid #334155;">
            <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Nombre de Dominio</span>
            <div style="font-size: 17px; font-weight: 700; color: #38bdf8; margin-top: 4px;">{url_dominio}</div>
          </td>
        </tr>
        <tr>
          <td style="padding: 16px 20px;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Fecha de Vencimiento</span>
                  <div style="font-size: 15px; font-weight: 600; color: #f59e0b; margin-top: 4px;">{fecha_fmt}</div>
                </td>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Costo de Renovación</span>
                  <div style="font-size: 17px; font-weight: 700; color: #10b981; margin-top: 4px;">
                    ${costo:,.2f} <span style="font-size: 12px; color: #94a3b8;">{currency}</span>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <p style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: #94a3b8;">
        Para proceder con la renovación o solicitar su factura, por favor comuníquese con nosotros o responda a este correo electrónico.
      </p>
    """

    html = _email_base_wrapper(subject, content, badge_text="Renovación de Dominio")
    return subject, html


def build_hosting_email(
    cliente_nombre: str,
    plan_nombre: str,
    dominio_asociado: str,
    costo: float = 0.0,
    currency: str = "MXN",
    fecha_renovacion: Optional[str] = None,
    servidor: Optional[str] = None,
) -> Tuple[str, str]:
    """
    Genera el asunto y el HTML para el recordatorio de renovación de servicio de Hosting.
    """
    subject = f"Recordatorio de Renovación de Hosting: {dominio_asociado} ({plan_nombre})"
    fecha_fmt = fecha_renovacion or "Próximamente"

    content = f"""
      <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #ffffff;">
        Estimado/a {cliente_nombre},
      </h2>
      <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #cbd5e1;">
        Le recordamos que su servicio de alojamiento web (Hosting) para <strong>{dominio_asociado}</strong> tiene programada su fecha de renovación próximamente.
      </p>

      <!-- Tarjeta de Hosting -->
      <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; margin-bottom: 24px;">
        <tr>
          <td style="padding: 16px 20px; border-bottom: 1px solid #334155;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Plan Contratado</span>
                  <div style="font-size: 15px; font-weight: 700; color: #f8fafc; margin-top: 4px;">{plan_nombre}</div>
                </td>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Dominio Principal</span>
                  <div style="font-size: 15px; font-weight: 600; color: #38bdf8; margin-top: 4px;">{dominio_asociado}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding: 16px 20px;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Fecha de Renovación</span>
                  <div style="font-size: 15px; font-weight: 600; color: #f59e0b; margin-top: 4px;">{fecha_fmt}</div>
                </td>
                <td width="50%">
                  <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Importe</span>
                  <div style="font-size: 17px; font-weight: 700; color: #10b981; margin-top: 4px;">
                    ${costo:,.2f} <span style="font-size: 12px; color: #94a3b8;">{currency}</span>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <p style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: #94a3b8;">
        Para asegurar la continuidad operativa de su plataforma web y cuentas de correo, por favor realice su renovación oportuna.
      </p>
    """

    html = _email_base_wrapper(subject, content, badge_text="Renovación de Hosting")
    return subject, html


def build_custom_email(
    cliente_nombre: str,
    asunto: str,
    cuerpo: str,
    despedida: Optional[str] = None,
) -> Tuple[str, str]:
    """
    Genera el HTML para un correo personalizado libre.
    """
    cuerpo_html = cuerpo.replace("\n", "<br>")
    despedida_html = despedida.replace("\n", "<br>") if despedida else "Atentamente,<br><strong>Equipo de ConlineWeb & NexusBot</strong>"

    content = f"""
      <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #ffffff;">
        Estimado/a {cliente_nombre},
      </h2>
      <div style="font-size: 14px; line-height: 1.7; color: #cbd5e1; margin-bottom: 24px;">
        {cuerpo_html}
      </div>
      <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #334155; font-size: 13px; color: #94a3b8; line-height: 1.6;">
        {despedida_html}
      </div>
    """

    html = _email_base_wrapper(asunto, content, badge_text="Comunicado Oficial")
    return asunto, html
