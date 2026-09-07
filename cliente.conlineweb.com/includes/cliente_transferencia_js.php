<?php
/**
 * JS compartido: solicitud de código de transferencia EPP.
 */
?>
<script>
function solicitarCodigo(dominioId, dominio) {
    Swal.fire({
        title: '¿Solicitar código de transferencia?',
        html: `
            <p style="margin:0 0 12px;color:#374151">
                Vas a solicitar el <strong>código de autorización (EPP)</strong> para el dominio:
            </p>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 16px;font-size:16px;font-weight:700;color:#0369a1;word-break:break-all">
                🌐 ${dominio}
            </div>
            <p style="margin:12px 0 0;font-size:13px;color:#6b7280">
                Se notificará al equipo de Conlineweb por correo y WhatsApp. Recibirás confirmación en tu correo registrado.
            </p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, solicitar código',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#000147',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(function(result) {
        if (!result.isConfirmed) return;

        var btn = document.querySelector('[data-transfer-btn="' + dominioId + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando…';
        }

        fetch('solicitar_codigo_transferencia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: 'dominio_id=' + encodeURIComponent(dominioId)
        })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(data) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-key-fill"></i> Solicitar código de transferencia';
            }
            if (data.success) {
                Swal.fire({
                    title: 'Solicitud enviada',
                    html: `
                        <p style="color:#374151;margin:0 0 14px">
                            Tu solicitud para <strong>${dominio}</strong> fue registrada correctamente.
                        </p>
                        <ul style="text-align:left;font-size:14px;color:#374151;padding-left:20px;margin:0 0 14px">
                            <li>Confirmación enviada a tu correo</li>
                            <li>El equipo fue notificado</li>
                            <li>Recibirás el código en <strong>1 a 3 días hábiles</strong></li>
                        </ul>
                        <a href="${data.wa_link}" target="_blank" rel="noopener"
                           style="display:inline-block;background:#25d366;color:#fff;border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;text-decoration:none">
                            Seguimiento por WhatsApp
                        </a>`,
                    icon: 'success',
                    confirmButtonColor: '#000147',
                    confirmButtonText: 'Entendido'
                });
            } else {
                Swal.fire('Error', data.message || 'No se pudo procesar la solicitud.', 'error');
            }
        })
        .catch(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-key-fill"></i> Solicitar código de transferencia';
            }
            Swal.fire('Error de red', 'No se pudo conectar al servidor. Intenta de nuevo.', 'error');
        });
    });
}
</script>
