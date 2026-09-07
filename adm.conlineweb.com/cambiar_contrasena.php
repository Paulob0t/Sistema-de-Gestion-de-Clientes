<?php
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $id = $_GET["id"];


}
?>

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <title>Formulario Cliente</title>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
        <style>
        .required-field::after {
            content: " *";
            color: red;
        }
                .btn-primary {
    background-color: #000147;
    border-color: #000147;
}
    </style> 
  </head>

<div id="content-wrapper" class="d-flex flex-column">

    <div id="content">

<div class="container my-4 p-4 bg-white rounded-4 shadow-sm border border-light-subtle">

        <!-- Begin Page Content -->
        <div class="container-fluid">
            <h1 class="h3 mb-0 text-primary">Actualizar contraseña</h1>
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
            </div>
            
<form id="formCambioContrasena">
<input type="hidden" name="id" id="id" value="<?php echo htmlspecialchars($id); ?>">

  <fieldset class="col-12 border p-3 mb-4 rounded">
    <legend class="float-none w-auto px-2" style="color: #000147;">Seguridad</legend>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label for="password" class="form-label required-field">Contraseña</label>
        <div class="input-group">
          <input type="password" class="form-control" name="contrasena" id="password" placeholder="Mínimo 8 caracteres" minlength="8" required>
          <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="form-text">La contraseña debe contener al menos 8 caracteres</div>
      </div>

      <div class="col-md-6 mb-3">
        <label for="confirmPassword" class="form-label required-field">Confirmar Contraseña</label>
        <div class="input-group">
          <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" placeholder="Repite tu contraseña" required>
          <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
    </div>
  </fieldset>

  <div class="col-12 d-flex justify-content-between mt-4">
    <button type="submit" class="btn btn-primary px-4">Guardar</button>
  </div>
</form>
</div>
    <!-- End of Footer -->

</div>

<!-- Bootstrap core JavaScript-->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Core plugin JavaScript-->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="js/sb-admin-2.min.js"></script>

<!-- Page level plugins -->
<script src="vendor/chart.js/Chart.min.js"></script>

<!-- Page level custom scripts -->
<script src="js/demo/chart-area-demo.js"></script>
<script src="js/demo/chart-pie-demo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById('formCambioContrasena').addEventListener('submit', function(e) {
  e.preventDefault();

  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirmPassword').value;

  if (password.length < 8) {
    return Swal.fire('Error', 'La contraseña debe tener al menos 8 caracteres.', 'error');
  }
  if (password !== confirmPassword) {
    return Swal.fire('Error', 'Las contraseñas no coinciden.', 'error');
  }

  // Mostrar los datos del formulario
  const formData = $('#formCambioContrasena').serialize();
  console.log('Datos del formulario:', formData);
  
  // También mostrar como objeto para mejor lectura
  const formDataObject = {};
  $('#formCambioContrasena').serializeArray().forEach(item => {
    formDataObject[item.name] = item.value;
  });
  console.log('Datos como objeto:', formDataObject);

  $.ajax({
    url: 'actualizar_contrasena.php',
    type: 'POST',
    data: $('#formCambioContrasena').serialize(),
    dataType: 'json',
    success: function(data) {
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Éxito',
          text: data.message || 'La contraseña fue actualizada correctamente',
          allowOutsideClick: false
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = 'https://cliente.conlineweb.com/index.php';
          }
        });
      } else {
        Swal.fire({
          icon: 'warning',
          title: 'Atención',
          text: data.message || 'No se pudo actualizar la contraseña'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error(error);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Ocurrió un error al procesar la solicitud'
      });
    }
  });
});

// Mostrar/ocultar contraseña
document.querySelectorAll('.toggle-password').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('bi-eye');
      icon.classList.add('bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('bi-eye-slash');
      icon.classList.add('bi-eye');
    }
  });
});
</script>

</body>

</html>