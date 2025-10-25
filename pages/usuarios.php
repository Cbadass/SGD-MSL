<?php
// pages/usuarios.php
require_once 'includes/db.php';
require_once 'includes/roles.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// ========== Obtener alcance ==========
$alcance = getAlcanceUsuario($conn, $_SESSION['usuario']);
$idsProfesionalesPermitidos = $alcance['profesionales'];
$puedeGestionarContrasenas = in_array($alcance['rol'], ['ADMIN', 'DIRECTOR'], true);
// ====================================

// 1) Recoger filtros
$escuela_filtro = $_GET['escuela'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$cargo_filtro = $_GET['cargo'] ?? '';
$filtro_prof_id = intval($_GET['Id_profesional'] ?? 0);

$allowed_cargos = [
    'Administradora','Directora',
    'Profesor(a) Diferencial','Profesor(a)',
    'Asistentes de la educación','Especialistas',
    'Docente','Psicologa','Fonoaudiologo',
    'Kinesiologo','Terapeuta Ocupacional'
];

// 2) Formulario de búsqueda
echo "<div class='d-flex align-items-center justify-content-between mb-4'>";
echo "  <h2 class='mb-0'>Visualización de Profesionales</h2>";
echo "</div>";
echo "<form method='GET' style='display:flex; gap:8rem ; margin: 2rem 0; align-items: flex-end;'>
        <input type='hidden' name='seccion' value='usuarios'>";

// Escuela (solo ADMIN)
if ($alcance['rol'] === 'ADMIN') {
    echo "<div>
            <label>Escuela</label>
            <select name='escuela' class='form-select'>
              <option value=''>Todas</option>
              <option value='1'".($escuela_filtro=='1'?' selected':'').">Sendero</option>
              <option value='2'".($escuela_filtro=='2'?' selected':'').">Multiverso</option>
              <option value='3'".($escuela_filtro=='3'?' selected':'').">Luz de Luna</option>
            </select>
          </div>";
}

// Estado
echo "<div>
        <label>Estado</label>
        <select name='estado' class='form-select'>
          <option value=''>Todos</option>
          <option value='1'".($estado_filtro=='1'?' selected':'').">Activo</option>
          <option value='0'".($estado_filtro=='0'?' selected':'').">Inactivo</option>
        </select>
      </div>";

// Cargo
echo "<div>
        <label>Cargo</label>
        <select name='cargo' class='form-select'>
          <option value=''>Todos</option>";
foreach ($allowed_cargos as $c) {
    $s = $cargo_filtro === $c ? ' selected' : '';
    echo "<option value=\"".htmlspecialchars($c)."\"{$s}>".htmlspecialchars($c)."</option>";
}
echo "  </select>
      </div>";

// Autocomplete Profesional
echo "<div style='flex:1; position:relative;'>
        <label>Profesional</label>
        <input type='text' id='buscar_profesional' class='form-control' placeholder='RUT o Nombre'>
        <input type='hidden' name='Id_profesional' id='Id_profesional' value='".htmlspecialchars($filtro_prof_id)."'>
        <div id='resultados_profesional' class='border mt-1' style='position:absolute; width:100%; z-index:10; background:#fff;'></div>
      </div>";

echo "<button type='submit' class='btn btn-primary btn-height mt-4'>Buscar</button>
      <button type='button' class='btn btn-secondary btn-height mt-4' onclick=\"window.location='?seccion=usuarios'\">Limpiar filtros</button>
      </form>";

// 3) Construir consulta con filtro de alcance
$sql = "
  SELECT
    u.Id_usuario,
    u.Nombre_usuario,
    u.Permisos,
    u.Estado_usuario,
    p.Id_profesional,
    p.Nombre_profesional,
    p.Apellido_profesional,
    p.Rut_profesional,
    p.Cargo_profesional,
    p.Correo_profesional,
    p.Id_escuela_prof,
    e.Nombre_escuela
  FROM usuarios u
  LEFT JOIN profesionales p ON u.Id_profesional = p.Id_profesional
  LEFT JOIN escuelas      e ON p.Id_escuela_prof = e.Id_escuela
  WHERE " . filtrarPorIDs($idsProfesionalesPermitidos, 'p.Id_profesional');

$params = [];
agregarParametrosFiltro($params, $idsProfesionalesPermitidos);

if ($escuela_filtro !== '' && $alcance['rol'] === 'ADMIN') {
    $sql .= " AND p.Id_escuela_prof = ?";
    $params[] = $escuela_filtro;
}
if ($estado_filtro !== '') {
    $sql .= " AND u.Estado_usuario = ?";
    $params[] = $estado_filtro;
}
if ($cargo_filtro !== '') {
    $sql .= " AND p.Cargo_profesional = ?";
    $params[] = $cargo_filtro;
}
if ($filtro_prof_id > 0) {
    $sql .= " AND p.Id_profesional = ?";
    $params[] = $filtro_prof_id;
}

$sql .= " ORDER BY u.Id_usuario DESC
          OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ... resto del código de la tabla sin cambios ...
// 5) Mostrar tabla
echo "<div style='max-height:400px; overflow-y:auto; border-radius:10px;'>
        <table class='table table-striped table-bordered'>
          <thead class='table-dark'>
            <tr>
              <th>RUT</th>
              <th>Usuario</th>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Cargo</th>
              <th>Escuela</th>
              <th>Permisos</th>
              <th>Estado</th>
              <th>Edición</th>
            </tr>
          </thead>
          <tbody>";

if ($usuarios) {
    foreach ($usuarios as $row) {
        echo "<tr>
                <td>".htmlspecialchars($row['Rut_profesional']   ?? '-')."</td>
                <td>".htmlspecialchars($row['Nombre_usuario'])."</td>
                <td>".htmlspecialchars($row['Nombre_profesional']?? '-')."</td>
                <td>".htmlspecialchars($row['Apellido_profesional']?? '-')."</td>
                <td>".htmlspecialchars($row['Cargo_profesional']  ?? '-')."</td>
                <td>".htmlspecialchars($row['Nombre_escuela']     ?? 'Otra')."</td>
                <td>".htmlspecialchars($row['Permisos']           ?? 'USER')."</td>
                <td>".($row['Estado_usuario']==1 ? 'Activo':'Inactivo')."</td>
                <td style='text-align:center;'>";

        if ($puedeGestionarContrasenas) {
            $btnAttrs = [
                'class' => 'btn btn-sm btn-outline-danger me-1 btn-reset-password',
                'data-id-usuario' => (string)$row['Id_usuario'],
                'data-id-profesional' => (string)($row['Id_profesional'] ?? ''),
                'data-nombre' => $row['Nombre_profesional'] ?? '',
                'data-apellido' => $row['Apellido_profesional'] ?? '',
                'data-rut' => $row['Rut_profesional'] ?? '',
                'data-escuela' => $row['Nombre_escuela'] ?? '',
                'data-correo' => $row['Correo_profesional'] ?? '',
                'data-usuario' => $row['Nombre_usuario'] ?? '',
            ];

            $attrStrings = [];
            foreach ($btnAttrs as $attr => $value) {
                if ($value === '') {
                    continue;
                }
                $attrStrings[] = sprintf(
                    '%s="%s"',
                    $attr,
                    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
                );
            }

            echo "<button type='button' " . implode(' ', $attrStrings) . ">Restablecer</button>";
        }

        echo "
                    <a href='index.php?seccion=modificar_profesional&Id_profesional=" . htmlspecialchars($row['Id_profesional']) . "' class='btn btn-sm btn-warning me-1 link-text'>Editar</a>
                    <a href='index.php?seccion=documentos&id_prof=" . htmlspecialchars($row['Id_profesional']) . "&sin_estudiante=1' class='btn btn-sm btn-info link-text'>Docs libres</a>
                    <a href=\"index.php?seccion=perfil&Id_profesional={$row['Id_profesional']}\"
                    class=\"btn btn-sm btn-primary link-text\">Ver perfil</a>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='9'>No se encontraron usuarios.</td></tr>";
}

echo "    </tbody>
        </table>
      </div>";
?>

<script>
// Autocomplete para Profesionales
function buscarProfesional(endpoint, query, cont, idInput) {
  if (query.length < 3) {
    cont.innerHTML = '';
    return;
  }
  fetch(endpoint + '?q=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => {
      cont.innerHTML = '';
      if (!data.length) {
        cont.innerHTML = '<div class="p-2 text-muted">Sin resultados</div>';
        return;
      }
      data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'resultado';
        div.textContent = `${item.rut} — ${item.nombre} ${item.apellido}`;
        div.onclick = () => {
          document.getElementById(idInput).value = item.id;
          cont.innerHTML = `<div class="resultado seleccionado">${div.textContent} (Seleccionado)</div>`;
        };
        cont.appendChild(div);
      });
    });
}
document.getElementById('buscar_profesional')
  .addEventListener('input', e => {
    buscarProfesional('buscar_profesionales.php', e.target.value.trim(),
                      document.getElementById('resultados_profesional'),
                      'Id_profesional');
  });
</script>

<?php if ($puedeGestionarContrasenas): ?>
<style>
.reset-password-dialog {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.reset-password-dialog[hidden] {
  display: none;
}

.reset-password-dialog__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
}

.reset-password-dialog__content {
  position: relative;
  background: #fff;
  border-radius: 10px;
  padding: 24px;
  width: min(480px, calc(100% - 32px));
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.2);
}

.reset-password-dialog__content h4 {
  margin-top: 0;
  margin-bottom: 12px;
}

.reset-password-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}

.reset-password-dialog__error {
  color: #b91c1c;
  background: #fee2e2;
  border: 1px solid #fecaca;
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 12px;
  display: none;
}

.reset-password-dialog__error[aria-hidden="false"] {
  display: block;
}

.reset-password-dialog__result dl {
  margin: 16px 0 24px;
}

.reset-password-dialog__result dt {
  font-weight: 600;
  font-size: 0.95rem;
}

.reset-password-dialog__result dd {
  margin: 0 0 10px 0;
  word-break: break-word;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>

<div id="resetPasswordDialog" class="reset-password-dialog" hidden>
  <div class="reset-password-dialog__backdrop" role="presentation"></div>
  <div class="reset-password-dialog__content" role="dialog" aria-modal="true" aria-labelledby="resetPasswordDialogTitle">
    <div id="resetPasswordDialogError" class="reset-password-dialog__error" aria-hidden="true"></div>

    <div id="resetPasswordDialogConfirm" data-step="confirm">
      <h4 id="resetPasswordDialogTitle">Restablecer contraseña</h4>
      <p id="resetPasswordDialogMessage"></p>
      <div class="reset-password-dialog__actions">
        <button type="button" class="btn btn-sm btn-secondary" id="resetPasswordCancel">Cancelar</button>
        <button type="button" class="btn btn-sm btn-danger" id="resetPasswordAccept">Restablecer</button>
      </div>
    </div>

    <div id="resetPasswordDialogResult" class="reset-password-dialog__result" data-step="result" hidden>
      <h4>Credenciales temporales generadas</h4>
      <p>Entrega estos datos al usuario y solicita el cambio al iniciar sesión.</p>
      <dl>
        <dt>Nombre</dt>
        <dd data-field="nombre"></dd>
        <dt>Apellido</dt>
        <dd data-field="apellido"></dd>
        <dt>Correo electrónico</dt>
        <dd data-field="correo"></dd>
        <dt>RUT</dt>
        <dd data-field="rut"></dd>
        <dt>Colegio</dt>
        <dd data-field="escuela"></dd>
        <dt>Usuario</dt>
        <dd data-field="usuario"></dd>
        <dt>Contraseña temporal</dt>
        <dd data-field="password"></dd>
      </dl>
      <div class="reset-password-dialog__actions">
        <button type="button" class="btn btn-sm btn-outline-primary" id="resetPasswordCopy">Copiar datos</button>
        <button type="button" class="btn btn-sm btn-primary" id="resetPasswordClose">Entendido</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const dialog = document.getElementById('resetPasswordDialog');
  if (!dialog) { return; }

  const confirmStep = document.getElementById('resetPasswordDialogConfirm');
  const resultStep = document.getElementById('resetPasswordDialogResult');
  const messageEl = document.getElementById('resetPasswordDialogMessage');
  const errorEl = document.getElementById('resetPasswordDialogError');

  const btnCancel = document.getElementById('resetPasswordCancel');
  const btnAccept = document.getElementById('resetPasswordAccept');
  const btnCopy = document.getElementById('resetPasswordCopy');
  const btnClose = document.getElementById('resetPasswordClose');
  const backdrop = dialog.querySelector('.reset-password-dialog__backdrop');

  const fieldMap = {
    nombre: resultStep.querySelector('[data-field="nombre"]'),
    apellido: resultStep.querySelector('[data-field="apellido"]'),
    correo: resultStep.querySelector('[data-field="correo"]'),
    rut: resultStep.querySelector('[data-field="rut"]'),
    escuela: resultStep.querySelector('[data-field="escuela"]'),
    usuario: resultStep.querySelector('[data-field="usuario"]'),
    password: resultStep.querySelector('[data-field="password"]'),
  };

  let currentTarget = null;
  let lastResponse = null;
  let isSubmitting = false;

  function setError(message) {
    if (!errorEl) { return; }
    if (message) {
      errorEl.textContent = message;
      errorEl.setAttribute('aria-hidden', 'false');
    } else {
      errorEl.textContent = '';
      errorEl.setAttribute('aria-hidden', 'true');
    }
  }

  function showStep(step) {
    if (step === 'confirm') {
      confirmStep.hidden = false;
      resultStep.hidden = true;
    } else {
      confirmStep.hidden = true;
      resultStep.hidden = false;
    }
  }

  function resetCopyButton() {
    if (!btnCopy) { return; }
    btnCopy.textContent = 'Copiar datos';
    btnCopy.disabled = false;
  }

  function openDialog(target) {
    currentTarget = target;
    lastResponse = null;
    setError('');
    showStep('confirm');
    const nombre = (target.nombre || '').trim();
    const apellido = (target.apellido || '').trim();
    const nombreVisible = [nombre, apellido].filter(Boolean).join(' ').trim() || nombre || 'este profesional';
    const rut = target.rut ? target.rut : 'sin RUT';
    const escuela = target.escuela ? target.escuela : 'sin escuela';
    messageEl.textContent = `¿Seguro quiere restablecer la contraseña de ${nombreVisible} - ${rut} - ${escuela}?`;
    dialog.removeAttribute('hidden');
    btnAccept.disabled = false;
    btnCancel.disabled = false;
    resetCopyButton();
  }

  function closeDialog() {
    if (!dialog.hasAttribute('hidden')) {
      dialog.setAttribute('hidden', 'hidden');
    }
    currentTarget = null;
    lastResponse = null;
    isSubmitting = false;
    btnAccept.disabled = false;
    btnCancel.disabled = false;
    resetCopyButton();
  }

  function fillResult(data) {
    Object.entries(fieldMap).forEach(([key, el]) => {
      if (!el) { return; }
      const value = data[key] || '—';
      el.textContent = value;
    });
  }

  function handleAccept() {
    if (!currentTarget || isSubmitting) { return; }
    isSubmitting = true;
    btnAccept.disabled = true;
    btnCancel.disabled = true;
    setError('');

    const formData = new FormData();
    formData.append('Id_usuario', currentTarget.idUsuario);

    fetch('ajax/reset_password_profesional.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then(res => res.json())
      .then((json) => {
        if (!json || !json.ok) {
          const msg = json && json.msg ? json.msg : 'No fue posible restablecer la contraseña.';
          setError(msg);
          btnAccept.disabled = false;
          btnCancel.disabled = false;
          isSubmitting = false;
          return;
        }

        lastResponse = json.data || null;
        fillResult(lastResponse || {});
        showStep('result');
        isSubmitting = false;
      })
      .catch(() => {
        setError('Error de red al intentar restablecer la contraseña.');
        btnAccept.disabled = false;
        btnCancel.disabled = false;
        isSubmitting = false;
      });
  }

  function handleCopy() {
    if (!lastResponse) { return; }
    const lines = [
      lastResponse.nombre ? `Nombre: ${lastResponse.nombre}` : null,
      lastResponse.apellido ? `Apellido: ${lastResponse.apellido}` : null,
      lastResponse.correo ? `Correo electrónico: ${lastResponse.correo}` : null,
      lastResponse.rut ? `RUT: ${lastResponse.rut}` : null,
      lastResponse.escuela ? `Colegio: ${lastResponse.escuela}` : null,
      lastResponse.usuario ? `Usuario: ${lastResponse.usuario}` : null,
      lastResponse.password ? `Contraseña temporal: ${lastResponse.password}` : null,
    ].filter(Boolean);

    if (!lines.length) { return; }

    navigator.clipboard.writeText(lines.join('\n')).then(() => {
      const originalText = btnCopy.textContent;
      btnCopy.textContent = '¡Datos copiados!';
      btnCopy.disabled = true;
      setTimeout(() => {
        btnCopy.textContent = originalText;
        btnCopy.disabled = false;
      }, 1800);
    }).catch(() => {
      setError('No se pudieron copiar los datos al portapapeles.');
    });
  }

  document.querySelectorAll('.btn-reset-password').forEach((btn) => {
    btn.addEventListener('click', () => {
      const dataset = btn.dataset || {};
      const target = {
        idUsuario: dataset.idUsuario || '',
        nombre: dataset.nombre || 'Sin nombre',
        apellido: dataset.apellido || '',
        rut: dataset.rut || '',
        escuela: dataset.escuela || '',
      };

      if (!target.idUsuario) {
        return;
      }

      openDialog({
        ...target,
        correo: dataset.correo || '',
        usuario: dataset.usuario || '',
      });
    });
  });

  if (btnAccept) btnAccept.addEventListener('click', handleAccept);
  if (btnCancel) btnCancel.addEventListener('click', closeDialog);
  if (btnClose) btnClose.addEventListener('click', closeDialog);
  if (btnCopy) btnCopy.addEventListener('click', handleCopy);
  if (backdrop) backdrop.addEventListener('click', closeDialog);
})();
</script>
<?php endif; ?>
