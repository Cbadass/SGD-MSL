<?php
try {
    require_once 'includes/db.php';
    require_once 'includes/storage.php';

    // Normaliza un RUT eliminando todo menos dígitos y K
    function normalizarRut($rut) {
        return preg_replace('/[^0-9kK]/', '', $rut);
    }

    $azure      = new AzureBlobStorage();
    $errorMsg   = '';
    $documentos = [];

    // Parámetros de paginación
    $porPagina = 10;
    $pagina    = max((int)($_GET['pagina'] ?? 1), 1);

    // Parámetros de filtro específicos
    $id_prof    = intval($_GET['id_prof']       ?? 0);
    $sin_estud  = isset($_GET['sin_estudiante']) && $_GET['sin_estudiante'] == 1;
    $id_est     = intval($_GET['id_estudiante'] ?? 0);
    $sin_profes = isset($_GET['sin_profesional']) && $_GET['sin_profesional'] == 1;

    // Construcción inicial del WHERE dinámico
    $where  = "1=1";
    $params = [];

    // Helper para LIKE
    function agregarFiltro(&$where, &$params, $campo, $valor) {
        if ($valor !== '') {
            $where   .= " AND $campo LIKE ?";
            $params[] = "%{$valor}%";
        }
    }

    // Filtros básicos
    agregarFiltro($where, $params, 'd.Nombre_documento', $_GET['nombre'] ?? '');
    agregarFiltro($where, $params, 'd.Tipo_documento',   $_GET['tipo_documento'] ?? '');

    // Filtros de fechas
    if (!empty($_GET['fecha_subida_desde'])) {
        $where   .= " AND d.Fecha_subido >= ?";
        $params[] = $_GET['fecha_subida_desde'];
    }
    if (!empty($_GET['fecha_subida_hasta'])) {
        $where   .= " AND d.Fecha_subido <= ?";
        $params[] = $_GET['fecha_subida_hasta'];
    }

    // Opciones de orden
    $ordenOpciones = [
        'subido_desc'     => 'd.Fecha_subido DESC',
        'subido_asc'      => 'd.Fecha_subido ASC',
        'modificado_desc' => 'd.Fecha_modificacion DESC',
        'modificado_asc'  => 'd.Fecha_modificacion ASC',
    ];
    $orden = $ordenOpciones[$_GET['orden'] ?? 'subido_desc']
           ?? $ordenOpciones['subido_desc'];

    // Filtro “desde profesional”
    if ($id_prof > 0) {
        $where   .= " AND d.Id_prof_doc = ?";
        $params[] = $id_prof;
        if ($sin_estud) {
            $where .= " AND d.Id_estudiante_doc IS NULL";
        }
    }
    // Filtro “desde estudiante”
    if ($id_est > 0) {
        $where   .= " AND d.Id_estudiante_doc = ?";
        $params[] = $id_est;
        if ($sin_profes) {
            $where .= " AND d.Id_prof_doc IS NULL";
        }
    }

    // Conteo total para paginación
    $stmtTotal = $conn->prepare("
        SELECT COUNT(*)
          FROM documentos d
     LEFT JOIN estudiantes   e ON d.Id_estudiante_doc = e.Id_estudiante
     LEFT JOIN profesionales p ON d.Id_prof_doc       = p.Id_profesional
        WHERE $where
    ");
    $stmtTotal->execute($params);
    $total    = (int)$stmtTotal->fetchColumn();
    $totalPag = max(1, ceil($total / $porPagina));
    if ($pagina > $totalPag) $pagina = $totalPag;
    $offset = ($pagina - 1) * $porPagina;

    // Consulta principal con joins
    $sql = "
        SELECT
          d.Id_documento,
          d.Nombre_documento,
          d.Tipo_documento,
          d.Fecha_subido,
          d.Fecha_modificacion,
          d.Descripcion,
          CONCAT(e.Nombre_estudiante,' ',e.Apellido_estudiante) AS Nombre_estudiante,
          CONCAT(p.Nombre_profesional,' ',p.Apellido_profesional)  AS Nombre_profesional,
          u.Nombre_usuario AS Usuario_subio
        FROM documentos d
   LEFT JOIN usuarios      u ON d.Id_usuario_subido   = u.Id_usuario
   LEFT JOIN estudiantes   e ON d.Id_estudiante_doc   = e.Id_estudiante
   LEFT JOIN profesionales p ON d.Id_prof_doc         = p.Id_profesional
        WHERE $where
        ORDER BY $orden
        OFFSET $offset ROWS FETCH NEXT $porPagina ROWS ONLY
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para el select de tipos únicos
    $stmtTipos = $conn->query("SELECT DISTINCT Tipo_documento FROM documentos");
    $tiposDb   = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}
?>

<style>
  /* pagination */
.pagination {
    --bs-pagination-padding-x: 0.75rem;
    --bs-pagination-padding-y: 0.375rem;
    --bs-pagination-font-size: 1rem;
    --bs-pagination-color: var(--bs-link-color);
    --bs-pagination-bg: var(--bs-body-bg);
    --bs-pagination-border-width: var(--bs-border-width);
    --bs-pagination-border-color: var(--bs-border-color);
    --bs-pagination-border-radius: var(--bs-border-radius);
    --bs-pagination-hover-color: var(--bs-link-hover-color);
    --bs-pagination-hover-bg: var(--bs-tertiary-bg);
    --bs-pagination-hover-border-color: var(--bs-border-color);
    --bs-pagination-focus-color: var(--bs-link-hover-color);
    --bs-pagination-focus-bg: var(--bs-secondary-bg);
    --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    --bs-pagination-active-color: #fff;
    --bs-pagination-active-bg: #0d6efd;
    --bs-pagination-active-border-color: #0d6efd;
    --bs-pagination-disabled-color: var(--bs-secondary-color);
    --bs-pagination-disabled-bg: var(--bs-secondary-bg);
    --bs-pagination-disabled-border-color: var(--bs-border-color);
    display: flex;
    padding-left: 0;
    list-style: none;
}
dl, ol, ul { margin-top: 0; margin-bottom: 1rem; }
.justify-content-center { justify-content: center !important; }
.page-item:first-child .page-link {
    border-top-left-radius: var(--bs-pagination-border-radius);
    border-bottom-left-radius: var(--bs-pagination-border-radius);
}
@media (prefers-reduced-motion: reduce) { .page-link { transition: none; } }
.page-link {
    position: relative; display: block;
    padding: var(--bs-pagination-padding-y) var(--bs-pagination-padding-x);
    font-size: var(--bs-pagination-font-size);
    color: var(--bs-pagination-color); text-decoration: none;
    background-color: var(--bs-pagination-bg);
    border: var(--bs-pagination-border-width) solid var(--bs-pagination-border-color);
    transition: color .15s ease-in-out, background-color .15s ease-in-out,
                border-color .15s ease-in-out, box-shadow .15s ease-in-out;
}
a { color: rgba(var(--bs-link-color-rgb), var(--bs-link-opacity, 1)); text-decoration: underline; }
*, ::after, ::before { box-sizing: border-box; }

/* Estilos del botón 4 estados */
.doc-filter { display:flex; align-items:center; gap:.75rem; margin:.5rem 0 1rem; }
.filter-btn{
  border:1px solid #ddd; border-radius:999px; padding:.6rem 1rem;
  background:#fff; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.06);
  display:flex; flex-direction:column; align-items:flex-start; min-width:260px;
  transition:transform .06s ease, box-shadow .12s ease;
}
.filter-btn:active{ transform:scale(.99) }
.filter-title{ font-weight:700; line-height:1.1 }
.filter-desc{ opacity:.8; line-height:1.1 }
.filter-btn.state-0{ box-shadow:0 2px 8px rgba(0,128,255,.15); }     /* Todos */
.filter-btn.state-1{ box-shadow:0 2px 8px rgba(0,200,150,.15); }     /* Solo profesionales */
.filter-btn.state-2{ box-shadow:0 2px 8px rgba(255,165,0,.20); }     /* Solo estudiantes */
.filter-btn.state-3{ box-shadow:0 2px 8px rgba(120,120,120,.18); opacity:.95; } /* Ninguno */
</style>

<h2 class="mb-4">
  <?php
    if ($id_prof > 0) {
      echo "Documentos del Profesional #{$id_prof}"
         . ($sin_estud ? " (sin estudiante)" : "");
    } elseif ($id_est > 0) {
      echo "Documentos del Estudiante #{$id_est}"
         . ($sin_profes ? " (sin profesional)" : "");
    } else {
      echo "Lista de Documentos";
    }
    echo " ({$total} encontrados)";
  ?>
</h2>

<!-- Filtro de búsqueda -->
<div class="card profile">
  <form method="GET" class="form-grid">
    <input type="hidden" name="seccion" value="documentos">
    <input type="hidden" name="pagina"      value="<?= $pagina ?>">
    <input type="hidden" name="id_prof"      id="id_prof"      value="<?= htmlspecialchars($id_prof) ?>">
    <input type="hidden" name="sin_estudiante" id="sin_estudiante" value="<?= $sin_estud ? 1 : 0 ?>">
    <input type="hidden" name="id_estudiante" id="id_estudiante" value="<?= htmlspecialchars($id_est) ?>">
    <input type="hidden" name="sin_profesional" id="sin_profesional" value="<?= $sin_profes ? 1 : 0 ?>">

    <div>
      <label>Nombre documento</label>
      <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>" class="form-control">
    </div>
    <div>
      <label>Tipo de documento</label>
      <select name="tipo_documento" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($tiposDb as $t): 
          $sel = ($_GET['tipo_documento'] ?? '') === $t ? 'selected' : '';
        ?>
          <option value="<?= htmlspecialchars($t) ?>" <?= $sel ?>><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Botón de 4 estados (reemplaza los 2 checkboxes) -->
    <div id="docFilter" class="doc-filter">
      <button id="filterToggle" type="button" class="filter-btn" aria-live="polite"
              data-init-sin-est="<?= $sin_estud ? 1 : 0 ?>"
              data-init-sin-prof="<?= $sin_profes ? 1 : 0 ?>">
        <span class="filter-title">Todos</span>
        <small class="filter-desc">“Muestra todo el surtido, sin timidez.”</small>
      </button>
    </div>

    <!-- Buscador Estudiante -->
    <div id="block_est">
      <label>Estudiante</label>
      <input type="text" id="buscar_estudiante" class="form-control" placeholder="RUT o Nombre">
      <div id="resultados_estudiante" class="border mt-1"></div>
    </div>

    <!-- Buscador Profesional -->
    <div id="block_prof">
      <label>Profesional</label>
      <input type="text" id="buscar_profesional" class="form-control" placeholder="RUT o Nombre">
      <div id="resultados_profesional" class="border mt-1"></div>
    </div>

    <div>
      <label>Fecha subida (desde)</label>
      <input type="date" name="fecha_subida_desde" value="<?= htmlspecialchars($_GET['fecha_subida_desde'] ?? '') ?>" class="form-control">
    </div>
    <div>
      <label>Fecha subida (hasta)</label>
      <input type="date" name="fecha_subida_hasta" value="<?= htmlspecialchars($_GET['fecha_subida_hasta'] ?? '') ?>" class="form-control">
    </div>
    <div>
      <label>Ordenar por</label>
      <select name="orden" class="form-select">
        <?php foreach ($ordenOpciones as $key => $o): ?>
          <option value="<?= $key ?>" <?= ($_GET['orden'] ?? '') === $key ? 'selected' : '' ?>>
            <?= ucwords(str_replace(['_','desc','asc'], [' ',' más reciente primero',' más antiguo primero'], $key)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mt-1" style="display:flex; gap:10px; align-items:end;">
      <button type="submit" class="btn btn-primary btn-height">Buscar</button>
      <button class="btn btn-secondary btn-height">
        <a href="?seccion=documentos" class="link-text">Limpiar filtros</a>
      </button>
    </div>
  </form>
</div>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php elseif (empty($documentos)): ?>
  <div class="alert alert-warning">No se encontraron documentos.</div>
<?php else: ?>
  <div class="table-responsive" style="max-height: 400px; overflow-y:auto; border-radius:10px;">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Nombre Documento</th>
          <th>Tipo de documento</th>
          <th>Fecha de Subida</th>
          <th>Modificado</th>
          <th>Descripción</th>
          <th>Estudiante</th>
          <th>Profesional</th>
          <th>Subido Por</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documentos as $d):
          $fs   = new DateTime($d['Fecha_subido']);
          $diff = (new DateTime())->diff($fs);
          if      ($diff->y) $t = $diff->y . ' año' . ($diff->y>1?'s':'');
          elseif  ($diff->m) $t = $diff->m . ' mes' . ($diff->m>1?'es':'');
          elseif  ($diff->d) $t = $diff->d . ' día' . ($diff->d>1?'s':'');
          elseif  ($diff->h) $t = $diff->h . ' hora' . ($diff->h>1?'s':'');
          else               $t = 'momento';
        ?>
        <tr>
          <td><?= htmlspecialchars($d['Nombre_documento']) ?></td>
          <td><?= htmlspecialchars($d['Tipo_documento']) ?></td>
          <td><?= $fs->format('d-m-Y') ?><br><small>Hace <?= $t ?></small></td>
          <td>
            <?= !empty($d['Fecha_modificacion'])
               ? (new DateTime($d['Fecha_modificacion']))->format('d-m-Y')
               : 'No Modificado' ?>
          </td>
          <td><?= htmlspecialchars($d['Descripcion']) ?></td>
          <td><?= htmlspecialchars($d['Nombre_estudiante']  ?: '-') ?></td>
          <td><?= htmlspecialchars($d['Nombre_profesional'] ?: '-') ?></td>
          <td><?= htmlspecialchars($d['Usuario_subio']) ?></td>
          <td style="text-align:center;">
            <a href="index.php?seccion=modificar_documento&id_documento=<?= $d['Id_documento'] ?>"
               class="btn btn-warning btn-sm link-text">Modificar</a>
            <a href="descargar.php?id_documento=<?= $d['Id_documento'] ?>"
               class="btn btn-primary btn-sm link-text">Descargar</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ------------- NUEVO BLOQUE DE PAGINACIÓN ------------- -->
  <nav style="margin-top:1.5rem">
    <ul class="pagination justify-content-center">
    <?php
      $baseURL = 'index.php?seccion=documentos&';
      $params  = $_GET;               // conserva todos los filtros
      unset($params['pagina']);       // lo fijaremos manualmente
      $ventana = 2;                   // nº de páginas a la izquierda y derecha

      // primera / anterior
      if ($pagina > 1) {
          $params['pagina'] = 1;
          echo '<li class="page-item"><a class="page-link" href="'.$baseURL.http_build_query($params).'">« Primera</a></li>';

          $params['pagina'] = $pagina - 1;
          echo '<li class="page-item"><a class="page-link" href="'.$baseURL.http_build_query($params).'">‹ Anterior</a></li>';
      }

      // rango de números
      $inicio = max(1, $pagina - $ventana);
      $fin    = min($totalPag, $pagina + $ventana);
      for ($p = $inicio; $p <= $fin; $p++) {
          $params['pagina'] = $p;
          $act = $p == $pagina ? ' active' : '';
          echo '<li class="page-item'.$act.'"><a class="page-link" href="'.$baseURL.http_build_query($params).'">'.$p.'</a></li>';
      }

      // siguiente / última
      if ($pagina < $totalPag) {
          $params['pagina'] = $pagina + 1;
          echo '<li class="page-item"><a class="page-link" href="'.$baseURL.http_build_query($params).'">Siguiente ›</a></li>';

          $params['pagina'] = $totalPag;
          echo '<li class="page-item"><a class="page-link" href="'.$baseURL.http_build_query($params).'">Última »</a></li>';
      }
    ?>
    </ul>
  </nav>
<?php endif; ?>

<script>
// ===============================
// Botón de filtro de 4 estados
// ===============================
(function(){
  // Estados y descripciones
  // Mapeo a tus flags:
  // - estado 0 (Todos):            sin_est=0, sin_prof=0  -> se ven ambos bloques
  // - estado 1 (Solo profesionales): sin_est=1, sin_prof=0 -> oculta bloque estudiante
  // - estado 2 (Solo estudiantes):   sin_est=0, sin_prof=1 -> oculta bloque profesional
  // - estado 3 (Ninguno):            sin_est=1, sin_prof=1 -> oculta ambos bloques
  const STATES = [
    {title:'Todos', desc:'“Muestra todo el surtido, sin timidez.”', sin_est:0, sin_prof:0},
    {title:'Solo profesionales', desc:'“Modo experto: documentos pro al frente.”', sin_est:1, sin_prof:0},
    {title:'Solo estudiantes', desc:'“Modo aula: solo material estudiantil.”', sin_est:0, sin_prof:1},
    {title:'Zen (ninguno)', desc:'“Silencio documental… respira.”', sin_est:1, sin_prof:1},
  ];

  const key = 'docFilterStateV2';
  const btn  = document.getElementById('filterToggle');
  if (!btn) return;

  const titleEl  = btn.querySelector('.filter-title');
  const descEl   = btn.querySelector('.filter-desc');
  const sinEstIn = document.getElementById('sin_estudiante');
  const sinProIn = document.getElementById('sin_profesional');
  const blockEst = document.getElementById('block_est');
  const blockPro = document.getElementById('block_prof');

  // Estado inicial desde GET (inyectado como data-*), o desde localStorage si no hay GET explícito
  const initSinEst  = Number(btn.getAttribute('data-init-sin-est')  || 0);
  const initSinProf = Number(btn.getAttribute('data-init-sin-prof') || 0);

  function stateFromFlags(se, sp){
    for (let i=0;i<STATES.length;i++){
      if (STATES[i].sin_est===se && STATES[i].sin_prof===sp) return i;
    }
    return 0;
  }

  // Si alguno de los flags viene en la URL, respétalo; si no, recuerda preferencia previa
  let state = null;
  const urlTieneFlags = (btn.getAttribute('data-init-sin-est') !== null) || (btn.getAttribute('data-init-sin-prof') !== null);
  if (urlTieneFlags) {
    state = stateFromFlags(initSinEst, initSinProf);
  } else {
    state = Number(localStorage.getItem(key) ?? 0);
  }

  function applyState(){
    const s = STATES[state];
    titleEl.textContent = s.title;
    descEl.textContent  = s.desc;

    btn.classList.remove('state-0','state-1','state-2','state-3');
    btn.classList.add('state-' + state);

    // Actualiza hidden inputs (para que el backend los lea si envías el form)
    sinEstIn.value = String(s.sin_est);
    sinProIn.value = String(s.sin_prof);

    // Muestra/oculta bloques de búsqueda
    if (blockEst) blockEst.style.display = s.sin_est ? 'none' : '';
    if (blockPro) blockPro.style.display = s.sin_prof ? 'none' : '';

    // Notifica (por si quieres escuchar esto en otra parte)
    document.dispatchEvent(new CustomEvent('docfilter:change', {
      detail: {
        state,
        sin_estudiante: !!s.sin_est,
        sin_profesional: !!s.sin_prof
      }
    }));

    // Persiste preferencia si no vino forzada por URL
    if (!urlTieneFlags) localStorage.setItem(key, String(state));
  }

  btn.addEventListener('click', function(){
    state = (state + 1) % STATES.length;
    applyState();
  });

  applyState();
})();

// ===============================
// Autocomplete genérico
// ===============================
function buscar(endpoint, query, cont, idInput) {
  if (query.length < 3) { cont.innerHTML = ''; return; }
  fetch(endpoint + '?q=' + encodeURIComponent(query))
    .then(r => r.json())
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
    }).catch(()=>{ cont.innerHTML = '<div class="p-2 text-muted">Error de búsqueda</div>'; });
}

document.addEventListener('DOMContentLoaded', () => {
  // autocomplete estudiante
  const estInput = document.getElementById('buscar_estudiante');
  if (estInput){
    estInput.addEventListener('input', e => {
      buscar('buscar_estudiantes.php',
             e.target.value.trim(),
             document.getElementById('resultados_estudiante'),
             'id_estudiante');
    });
  }

  // autocomplete profesional
  const profInput = document.getElementById('buscar_profesional');
  if (profInput){
    profInput.addEventListener('input', e => {
      buscar('buscar_profesionales.php',
             e.target.value.trim(),
             document.getElementById('resultados_profesional'),
             'id_prof');
    });
  }
});
</script>
