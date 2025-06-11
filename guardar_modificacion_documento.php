<?php
require_once 'includes/db.php';

// Obtener ID del documento
$id = intval($_GET['id_documento'] ?? 0);
if ($id <= 0) {
    die("ID inválido.");
}

// Obtener datos del documento
$stmt = $conn->prepare("SELECT * FROM documentos WHERE Id_documento = ?");
$stmt->execute([$id]);
$documento = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$documento) {
    die("Documento no encontrado.");
}
?>

<h2>Modificar Documento</h2>

<form action="guardar_modificacion.php" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="id_documento" value="<?= htmlspecialchars($documento['Id_documento']) ?>">

  <div class="mb-3">
    <label for="nombre">Nombre del Documento</label>
    <input type="text" name="nombre" id="nombre" class="form-control" required value="<?= htmlspecialchars($documento['Nombre_documento']) ?>">
  </div>

  <div class="mb-3">
    <label for="tipo_documento">Tipo de Documento</label>
    <select name="tipo_documento" id="tipo_documento" class="form-select" required>
      <option value="">Seleccione tipo</option>
      <?php
      $tipos = [
        "Certificado de Nacimiento", "Ficha de Matrícula", "Informe Psicológico", "Informe Pedagógico 1er semestre",
        "Recetas médicas", "Curriculum", "Contrato de trabajo", "Certificados de perfeccionamientos"
        // Agrega todos los tipos usados en tu sistema aquí
      ];
      foreach ($tipos as $tipo) {
          $selected = ($tipo === $documento['Tipo_documento']) ? 'selected' : '';
          echo "<option value=\"$tipo\" $selected>$tipo</option>";
      }
      ?>
    </select>
  </div>

  <div class="mb-3">
    <label for="descripcion">Descripción</label>
    <textarea name="descripcion" id="descripcion" class="form-control"><?= htmlspecialchars($documento['Descripcion']) ?></textarea>
  </div>

  <div class="mb-3">
    <label for="archivo">Reemplazar archivo (opcional)</label>
    <input type="file" name="archivo" id="archivo" class="form-control">
    <small class="form-text text-muted">Archivo actual: <a href="<?= htmlspecialchars($documento['Url_documento']) ?>" target="_blank">Ver</a></small>
  </div>

  <div class="mb-3">
    <label for="id_estudiante">ID Estudiante (si aplica)</label>
    <input type="number" name="id_estudiante" id="id_estudiante" class="form-control" value="<?= htmlspecialchars($documento['Id_estudiante_doc']) ?>">
  </div>

  <div class="mb-3">
    <label for="id_profesional">ID Profesional (si aplica)</label>
    <input type="number" name="id_profesional" id="id_profesional" class="form-control" value="<?= htmlspecialchars($documento['Id_prof_doc']) ?>">
  </div>

  <button type="submit" class="btn btn-success">Guardar Cambios</button>
  <a href="index.php?seccion=documentos" class="btn btn-secondary">Cancelar</a>
</form>
