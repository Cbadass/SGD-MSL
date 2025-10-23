<?php
// includes/roles.php
// ============================================================================
// SISTEMA DE ROLES Y PERMISOS - SGD MULTISENLUZ
// ============================================================================
// Roles disponibles: ADMIN, DIRECTOR, PROFESIONAL
// Alcance: ADMIN (global), DIRECTOR (por escuela), PROFESIONAL (por asignación)
// ============================================================================

// -----------------------------
// Utilidades de normalización
// -----------------------------

/**
 * Normaliza una cadena: minúsculas, sin acentos ni ñ
 */
function _norm_str(string $s): string {
  $s = mb_strtolower(trim($s), 'UTF-8');
  // normaliza acentos y ñ
  $s = strtr($s, [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
    'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'
  ]);
  // colapsa "(a)" y espacios
  $s = preg_replace('/\s*\(a\)\s*/u', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

// -----------------------------
// Roles válidos y helpers simples
// -----------------------------

/**
 * Verifica si un rol es válido
 */
function rolValido(string $rol): bool {
  $r = strtoupper(trim($rol));
  return in_array($r, ['ADMIN','DIRECTOR','PROFESIONAL'], true);
}

/**
 * Asegura que el rol sea válido, si no devuelve PROFESIONAL por defecto
 */
function ensureRole(string $rol): string {
  $r = strtoupper(trim($rol));
  return rolValido($r) ? $r : 'PROFESIONAL';
}

/**
 * Verifica si el rol es ADMIN
 */
function isAdmin(string $rol): bool {
  return ensureRole($rol) === 'ADMIN';
}

/**
 * Verifica si el rol es DIRECTOR
 */
function isDirector(string $rol): bool {
  return ensureRole($rol) === 'DIRECTOR';
}

/**
 * Verifica si el rol es PROFESIONAL
 */
function isProfesional(string $rol): bool {
  return ensureRole($rol) === 'PROFESIONAL';
}

// -----------------------------
// Derivar rol desde el CARGO
// -----------------------------

/**
 * Deriva el rol automáticamente desde el cargo del profesional
 * - "Administrador(a)", "Administrador General", etc. → ADMIN
 * - "Director(a)", "Directora Académica", etc.       → DIRECTOR
 * - Cualquier otro                                    → PROFESIONAL
 */
function rolDesdeCargo(string $cargo): string {
  $c = _norm_str($cargo);

  // ADMIN: "admin" o "administrador/administradora" como palabra
  if (preg_match('/\badmin(?:istrador(?:a)?)?\b/u', $c)) {
    return 'ADMIN';
  }

  // DIRECTOR: "director/directora" como palabra
  if (preg_match('/\bdirector(?:a)?\b/u', $c)) {
    return 'DIRECTOR';
  }

  // Default del proyecto: cualquier otro cargo es PROFESIONAL
  return 'PROFESIONAL';
}

// -----------------------------
// Autorización por alcance
// -----------------------------

/**
 * ¿Puede ver el módulo de usuarios?
 * ADMIN y DIRECTOR: sí. PROFESIONAL: no (salvo su propio perfil en "Perfil").
 */
function canSeeUsers(string $rol): bool {
  $r = ensureRole($rol);
  return $r === 'ADMIN' || $r === 'DIRECTOR';
}

/**
 * ¿Puede editar datos laborales? (cargo, tipo, escuela, horas, fecha_ingreso, previsión/banco)
 * Solo ADMIN.
 */
function canEditLabor(string $rol): bool {
  return ensureRole($rol) === 'ADMIN';
}

/**
 * ¿Puede editar el perfil target?
 * - ADMIN: siempre.
 * - DIRECTOR: solo si actorSchoolId == targetSchoolId.
 * - PROFESIONAL: solo si actorUserId == targetUserId.
 */
function canEditProfile(
  string $rol,
  ?int $actorUserId,
  ?int $targetUserId,
  ?int $actorSchoolId,
  ?int $targetSchoolId
): bool {
  $r = ensureRole($rol);
  if ($r === 'ADMIN') return true;
  if ($r === 'DIRECTOR') {
    return $actorSchoolId !== null && $targetSchoolId !== null && (int)$actorSchoolId === (int)$targetSchoolId;
  }
  // PROFESIONAL
  return $actorUserId !== null && $targetUserId !== null && (int)$actorUserId === (int)$targetUserId;
}

/**
 * ¿Puede restablecer contraseñas?
 * - ADMIN: cualquier usuario.
 * - DIRECTOR: solo usuarios de su escuela.
 * - PROFESIONAL: no.
 */
function canResetPassword(
  string $rol,
  ?int $actorSchoolId,
  ?int $targetSchoolId
): bool {
  $r = ensureRole($rol);
  if ($r === 'ADMIN') return true;
  if ($r === 'DIRECTOR') {
    return $actorSchoolId !== null && $targetSchoolId !== null && (int)$actorSchoolId === (int)$targetSchoolId;
  }
  return false;
}

// ============================================================================
// HELPERS DE ALCANCE Y FILTRADO POR ROL
// ============================================================================

/**
 * Obtiene el ID de escuela de un profesional
 */
function getEscuelaDelProfesional(PDO $conn, int $idProfesional): ?int {
  if ($idProfesional <= 0) return null;
  
  $stmt = $conn->prepare("SELECT Id_escuela_prof FROM profesionales WHERE Id_profesional = ?");
  $stmt->execute([$idProfesional]);
  $id = $stmt->fetchColumn();
  
  return $id ? (int)$id : null;
}

/**
 * Obtiene el ID de escuela del usuario actual desde sesión
 */
function getEscuelaDelUsuario(PDO $conn, array $usuario): ?int {
  $idProf = (int)($usuario['id_profesional'] ?? 0);
  return getEscuelaDelProfesional($conn, $idProf);
}

/**
 * Obtiene los IDs de estudiantes según el rol del usuario
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $rol Rol del usuario (ADMIN/DIRECTOR/PROFESIONAL)
 * @param int $idProfesional ID del profesional (para PROFESIONAL)
 * @param int|null $idEscuela ID de la escuela (para DIRECTOR)
 * 
 * @return array|null 
 *   - null: sin restricción (ADMIN)
 *   - []: sin acceso (PROFESIONAL sin asignaciones)
 *   - [1,2,3]: IDs permitidos (PROFESIONAL con asignaciones o DIRECTOR)
 */
function getEstudiantesPermitidos(PDO $conn, string $rol, int $idProfesional, ?int $idEscuela): ?array {
  $rolNorm = ensureRole($rol);
  
  // ADMIN: sin restricción
  if ($rolNorm === 'ADMIN') {
    return null;
  }
  
  // DIRECTOR: todos los estudiantes de su escuela
  if ($rolNorm === 'DIRECTOR') {
    if (!$idEscuela) return [0]; // Sin escuela asignada = bloquear todo
    
    $stmt = $conn->prepare("
      SELECT DISTINCT e.Id_estudiante 
      FROM estudiantes e
      INNER JOIN cursos c ON e.Id_curso = c.Id_curso
      WHERE c.Id_escuela = ?
    ");
    $stmt->execute([$idEscuela]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $ids ?: [0]; // Si no hay estudiantes en la escuela = bloquear
  }
  
  // PROFESIONAL: solo sus asignados
  if ($rolNorm === 'PROFESIONAL') {
    $stmt = $conn->prepare("
      SELECT DISTINCT Id_estudiante 
      FROM Asignaciones 
      WHERE Id_profesional = ? AND Id_estudiante IS NOT NULL
    ");
    $stmt->execute([$idProfesional]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $ids ?: [0]; // Sin asignaciones = bloquear todo
  }
  
  return [0]; // Rol desconocido = bloquear
}

/**
 * Obtiene los IDs de profesionales según el rol del usuario
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $rol Rol del usuario
 * @param int|null $idEscuela ID de la escuela (para DIRECTOR)
 * 
 * @return array|null 
 *   - null: sin restricción (ADMIN)
 *   - []: sin acceso
 *   - [1,2,3]: IDs permitidos (DIRECTOR = profesionales de su escuela)
 */
function getProfesionalesPermitidos(PDO $conn, string $rol, ?int $idEscuela): ?array {
  $rolNorm = ensureRole($rol);
  
  // ADMIN: sin restricción
  if ($rolNorm === 'ADMIN') {
    return null;
  }
  
  // DIRECTOR: profesionales de su escuela
  if ($rolNorm === 'DIRECTOR') {
    if (!$idEscuela) return [0];
    
    $stmt = $conn->prepare("
      SELECT Id_profesional 
      FROM profesionales 
      WHERE Id_escuela_prof = ?
    ");
    $stmt->execute([$idEscuela]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $ids ?: [0];
  }
  
  // PROFESIONAL: no gestiona profesionales (pero si necesita verse a sí mismo)
  return [0];
}

/**
 * Obtiene los IDs de cursos según el rol del usuario
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param string $rol Rol del usuario
 * @param int|null $idEscuela ID de la escuela (para DIRECTOR)
 * 
 * @return array|null
 *   - null: sin restricción
 *   - []: sin acceso
 *   - [1,2,3]: IDs permitidos
 */
function getCursosPermitidos(PDO $conn, string $rol, ?int $idEscuela): ?array {
  $rolNorm = ensureRole($rol);
  
  // ADMIN: sin restricción
  if ($rolNorm === 'ADMIN') {
    return null;
  }
  
  // DIRECTOR: cursos de su escuela
  if ($rolNorm === 'DIRECTOR') {
    if (!$idEscuela) return [0];
    
    $stmt = $conn->prepare("SELECT Id_curso FROM cursos WHERE Id_escuela = ?");
    $stmt->execute([$idEscuela]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $ids ?: [0];
  }
  
  // PROFESIONAL: todos los cursos (solo lectura)
  return null;
}

/**
 * Obtiene los IDs de apoderados según el rol del usuario
 * Los apoderados se filtran según los estudiantes permitidos
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array|null $idsEstudiantes IDs de estudiantes permitidos
 * 
 * @return array|null
 *   - null: sin restricción
 *   - []: sin acceso
 *   - [1,2,3]: IDs permitidos
 */
function getApoderadosPermitidos(PDO $conn, ?array $idsEstudiantes): ?array {
  // Si no hay restricción de estudiantes, no hay restricción de apoderados
  if ($idsEstudiantes === null) {
    return null;
  }
  
  // Si no hay estudiantes permitidos, no hay apoderados permitidos
  if (empty($idsEstudiantes) || $idsEstudiantes === [0]) {
    return [0];
  }
  
  // Obtener apoderados de los estudiantes permitidos
  $placeholders = implode(',', array_fill(0, count($idsEstudiantes), '?'));
  $stmt = $conn->prepare("
    SELECT DISTINCT Id_apoderado 
    FROM estudiantes 
    WHERE Id_estudiante IN ($placeholders) AND Id_apoderado IS NOT NULL
  ");
  $stmt->execute($idsEstudiantes);
  $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
  
  return $ids ?: [0];
}

/**
 * Genera cláusula WHERE para filtrar por IDs
 * 
 * @param array|null $ids null = sin restricción, [] o [0] = bloquear todo
 * @param string $columna Nombre completo de la columna (ej: 'e.Id_estudiante')
 * @return string Cláusula SQL
 */
function filtrarPorIDs(?array $ids, string $columna): string {
  if ($ids === null) {
    return '1=1'; // Sin restricción
  }
  
  if (empty($ids) || $ids === [0]) {
    return '0=1'; // Bloquear todo
  }
  
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  return "$columna IN ($placeholders)";
}

/**
 * Agrega parámetros de filtrado a un array existente
 * 
 * @param array &$params Array de parámetros PDO (por referencia)
 * @param array|null $ids IDs a agregar
 */
function agregarParametrosFiltro(array &$params, ?array $ids): void {
  if ($ids !== null && !empty($ids) && $ids !== [0]) {
    $params = array_merge($params, $ids);
  }
}

/**
 * Wrapper unificado: obtiene todos los filtros de alcance para el usuario actual
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array $usuario Array de sesión del usuario ($_SESSION['usuario'])
 * 
 * @return array [
 *   'estudiantes' => array|null,      // IDs de estudiantes permitidos
 *   'profesionales' => array|null,    // IDs de profesionales permitidos
 *   'cursos' => array|null,           // IDs de cursos permitidos
 *   'apoderados' => array|null,       // IDs de apoderados permitidos
 *   'escuela_id' => int|null,         // ID de escuela del usuario
 *   'rol' => string,                  // Rol normalizado (ADMIN/DIRECTOR/PROFESIONAL)
 *   'id_profesional' => int           // ID del profesional del usuario
 * ]
 */
function getAlcanceUsuario(PDO $conn, array $usuario): array {
  $rol = ensureRole($usuario['permisos'] ?? 'GUEST');
  $idProf = (int)($usuario['id_profesional'] ?? 0);
  $idEscuela = getEscuelaDelUsuario($conn, $usuario);

  $diagnosticos = [];
  $userId = (int)($usuario['id'] ?? $usuario['Id_usuario'] ?? 0);
  $userLogin = $usuario['Nombre_usuario'] ?? $usuario['usuario'] ?? '';

  if ($rol === 'DIRECTOR' && !$idEscuela) {
    error_log(sprintf('[SGD] Director sin escuela asociada (usuario %d, login "%s").', $userId, $userLogin));
    $diagnosticos[] = 'Tu cuenta no está asociada a ninguna escuela. Comunícate con soporte para regularizar el vínculo.';
  }

  if ($rol === 'PROFESIONAL' && $idProf <= 0) {
    error_log(sprintf('[SGD] Profesional sin Id_profesional vinculado (usuario %d, login "%s").', $userId, $userLogin));
    $diagnosticos[] = 'Tu cuenta no está vinculada a un profesional activo. Solicita asistencia a soporte.';
  }

  // Obtener estudiantes permitidos
  $idsEstudiantes = getEstudiantesPermitidos($conn, $rol, $idProf, $idEscuela);

  if ($rol === 'PROFESIONAL' && $idProf > 0 && ($idsEstudiantes === [0])) {
    error_log(sprintf('[SGD] Profesional sin asignaciones activas (usuario %d, profesional %d).', $userId, $idProf));
    $diagnosticos[] = 'No tienes estudiantes asignados actualmente. Contacta a la coordinación para revisar tus asignaciones.';
  }

  // Obtener apoderados basados en estudiantes permitidos
  $idsApoderados = getApoderadosPermitidos($conn, $idsEstudiantes);

  return [
    'estudiantes' => $idsEstudiantes,
    'profesionales' => getProfesionalesPermitidos($conn, $rol, $idEscuela),
    'cursos' => getCursosPermitidos($conn, $rol, $idEscuela),
    'apoderados' => $idsApoderados,
    'escuela_id' => $idEscuela,
    'rol' => $rol,
    'id_profesional' => $idProf,
    'diagnosticos' => $diagnosticos,
  ];
}

// ============================================================================
// HELPERS DE VALIDACIÓN DE ACCESO
// ============================================================================

/**
 * Requiere que el usuario tenga uno de los roles especificados
 * Si no cumple, envía 403 y termina ejecución
 * 
 * @param array $rolesPermitidos Array de roles permitidos ['ADMIN', 'DIRECTOR']
 */
function requireAnyRole(array $rolesPermitidos): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  
  $rolActual = strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
  
  if (!in_array($rolActual, $rolesPermitidos, true)) {
    http_response_code(403);
    die('Acceso denegado: No tienes permisos para acceder a esta sección.');
  }
}

/**
 * Verifica si el usuario puede acceder a un estudiante específico
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array $alcance Array de alcance del usuario (de getAlcanceUsuario)
 * @param int $idEstudiante ID del estudiante a verificar
 * @return bool True si tiene acceso, false si no
 */
function puedeAccederEstudiante(PDO $conn, array $alcance, int $idEstudiante): bool {
  $idsPermitidos = $alcance['estudiantes'];
  
  // ADMIN: sin restricción
  if ($idsPermitidos === null) {
    return true;
  }
  
  // Verificar si está en la lista de permitidos
  return in_array($idEstudiante, $idsPermitidos, true);
}

/**
 * Verifica si el usuario puede acceder a un profesional específico
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array $alcance Array de alcance del usuario
 * @param int $idProfesional ID del profesional a verificar
 * @return bool True si tiene acceso, false si no
 */
function puedeAccederProfesional(PDO $conn, array $alcance, int $idProfesional): bool {
  $idsPermitidos = $alcance['profesionales'];
  
  // ADMIN: sin restricción
  if ($idsPermitidos === null) {
    return true;
  }
  
  // Verificar si está en la lista de permitidos o es él mismo
  return in_array($idProfesional, $idsPermitidos, true) 
      || $idProfesional === $alcance['id_profesional'];
}

/**
 * Verifica si el usuario puede acceder a un curso específico
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array $alcance Array de alcance del usuario
 * @param int $idCurso ID del curso a verificar
 * @return bool True si tiene acceso, false si no
 */
function puedeAccederCurso(PDO $conn, array $alcance, int $idCurso): bool {
  $idsPermitidos = $alcance['cursos'];
  
  // Sin restricción (ADMIN o PROFESIONAL en lectura)
  if ($idsPermitidos === null) {
    return true;
  }
  
  // Verificar si está en la lista de permitidos
  return in_array($idCurso, $idsPermitidos, true);
}

/**
 * Verifica si el usuario puede acceder a un apoderado específico
 * 
 * @param PDO $conn Conexión a la base de datos
 * @param array $alcance Array de alcance del usuario
 * @param int $idApoderado ID del apoderado a verificar
 * @return bool True si tiene acceso, false si no
 */
function puedeAccederApoderado(PDO $conn, array $alcance, int $idApoderado): bool {
  $idsPermitidos = $alcance['apoderados'];
  
  // ADMIN: sin restricción
  if ($idsPermitidos === null) {
    return true;
  }
  
  // Verificar si está en la lista de permitidos
  return in_array($idApoderado, $idsPermitidos, true);
}

// ============================================================================
// HELPERS DE VALIDACIÓN DE DATOS
// ============================================================================

/**
 * Valida que un RUT chileno sea válido (verifica dígito verificador)
 * 
 * @param string $rut RUT a validar (puede incluir puntos y guión)
 * @return bool True si es válido, false si no
 */
function validarRutChileno(string $rut): bool {
  // Limpiar RUT
  $rut = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
  
  if (strlen($rut) < 2) {
    return false;
  }
  
  $cuerpo = substr($rut, 0, -1);
  $dv = substr($rut, -1);
  
  // Calcular dígito verificador
  $suma = 0;
  $multiplo = 2;
  
  for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
    $suma += (int)$cuerpo[$i] * $multiplo;
    $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
  }
  
  $dvEsperado = 11 - ($suma % 11);
  
  if ($dvEsperado == 11) {
    $dvEsperado = '0';
  } elseif ($dvEsperado == 10) {
    $dvEsperado = 'K';
  } else {
    $dvEsperado = (string)$dvEsperado;
  }
  
  return $dv === $dvEsperado;
}

/**
 * Formatea un RUT chileno con puntos y guión (12.345.678-9)
 * 
 * @param string $rut RUT a formatear
 * @return string RUT formateado
 */
function formatearRutChileno(string $rut): string {
  // Limpiar RUT
  $rut = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
  
  if (strlen($rut) < 2) {
    return $rut;
  }
  
  $cuerpo = substr($rut, 0, -1);
  $dv = substr($rut, -1);
  
  // Formatear con separadores de miles
  $cuerpoFormateado = number_format((int)$cuerpo, 0, '', '.');
  
  return $cuerpoFormateado . '-' . $dv;
}

/**
 * Limpia un RUT chileno dejando solo números y K
 * 
 * @param string $rut RUT a limpiar
 * @return string RUT limpio
 */
function limpiarRutChileno(string $rut): string {
  return preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
}

/**
 * Valida un email
 * 
 * @param string $email Email a validar
 * @return bool True si es válido, false si no
 */
function validarEmail(string $email): bool {
  return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida un teléfono chileno móvil (+56 9 XXXX XXXX)
 * 
 * @param string $telefono Teléfono a validar
 * @return bool True si es válido, false si no
 */
function validarTelefonoChileno(string $telefono): bool {
  $telefono = trim($telefono);
  
  // Permitir formatos: +56912345678, 56912345678, 912345678, +56 9 1234 5678, etc.
  $pattern = '/^\+?56[\s\-]?9[\s\-]?\d{4}[\s\-]?\d{4}$/';
  
  return preg_match($pattern, $telefono) === 1;
}

/**
 * Normaliza un teléfono chileno al formato estándar (+56912345678)
 * 
 * @param string $telefono Teléfono a normalizar
 * @return string Teléfono normalizado
 */
function normalizarTelefonoChileno(string $telefono): string {
  // Eliminar todo excepto números
  $telefono = preg_replace('/[^0-9]/', '', trim($telefono));
  
  // Si empieza con 56, agregar +
  if (substr($telefono, 0, 2) === '56') {
    return '+' . $telefono;
  }
  
  // Si empieza con 9 (formato corto), agregar +56
  if (substr($telefono, 0, 1) === '9' && strlen($telefono) === 9) {
    return '+56' . $telefono;
  }
  
  return $telefono;
}

// ============================================================================
// HELPERS DE AUDITORÍA
// ============================================================================

/**
 * Obtiene un resumen legible de los cambios entre dos arrays
 * Útil para mostrar en logs de auditoría
 * 
 * @param array|null $antes Datos antes del cambio
 * @param array|null $despues Datos después del cambio
 * @return array Array de cambios ['campo' => ['antes' => X, 'despues' => Y]]
 */
function obtenerCambios(?array $antes, ?array $despues): array {
  if ($antes === null) {
    return []; // Inserción, no hay "antes"
  }
  
  if ($despues === null) {
    return []; // Eliminación, no hay "después"
  }
  
  $cambios = [];
  
  // Verificar campos modificados
  foreach ($despues as $campo => $valorNuevo) {
    $valorAnterior = $antes[$campo] ?? null;
    
    // Comparar valores (normalizar nulls y strings vacíos)
    $valAnt = $valorAnterior === '' ? null : $valorAnterior;
    $valNue = $valorNuevo === '' ? null : $valorNuevo;
    
    if ($valAnt != $valNue) {
      $cambios[$campo] = [
        'antes' => $valAnt,
        'despues' => $valNue
      ];
    }
  }
  
  return $cambios;
}

/**
 * Formatea cambios para mostrar en interfaz
 * 
 * @param array $cambios Array de cambios de obtenerCambios()
 * @return string HTML con los cambios formateados
 */
function formatearCambiosHTML(array $cambios): string {
  if (empty($cambios)) {
    return '<em>Sin cambios</em>';
  }
  
  $html = '<ul style="margin:0; padding-left:20px;">';
  
  foreach ($cambios as $campo => $valores) {
    $antes = $valores['antes'] === null ? '<em>vacío</em>' : htmlspecialchars((string)$valores['antes']);
    $despues = $valores['despues'] === null ? '<em>vacío</em>' : htmlspecialchars((string)$valores['despues']);
    
    $html .= "<li><strong>" . htmlspecialchars($campo) . ":</strong> ";
    $html .= "<span style='color:#dc3545;'>$antes</span> → ";
    $html .= "<span style='color:#28a745;'>$despues</span></li>";
  }
  
  $html .= '</ul>';
  
  return $html;
}

// ============================================================================
// HELPERS DE PERMISOS POR ACCIÓN
// ============================================================================

/**
 * Verifica si el usuario puede crear registros en una entidad
 * 
 * @param string $rol Rol del usuario
 * @param string $entidad Nombre de la entidad (estudiantes, cursos, etc.)
 * @return bool True si puede crear, false si no
 */
function puedeCrear(string $rol, string $entidad): bool {
  $rolNorm = ensureRole($rol);
  
  // ADMIN puede crear todo
  if ($rolNorm === 'ADMIN') {
    return true;
  }
  
  // DIRECTOR puede crear en su escuela
  if ($rolNorm === 'DIRECTOR') {
    $entidadesPermitidas = ['estudiantes', 'cursos', 'apoderados', 'usuarios', 'documentos', 'asignaciones'];
    return in_array(strtolower($entidad), $entidadesPermitidas, true);
  }
  
  // PROFESIONAL solo puede crear documentos
  if ($rolNorm === 'PROFESIONAL') {
    return strtolower($entidad) === 'documentos';
  }
  
  return false;
}

/**
 * Verifica si el usuario puede editar registros en una entidad
 * 
 * @param string $rol Rol del usuario
 * @param string $entidad Nombre de la entidad
 * @return bool True si puede editar, false si no
 */
function puedeEditar(string $rol, string $entidad): bool {
  $rolNorm = ensureRole($rol);
  
  // ADMIN puede editar todo
  if ($rolNorm === 'ADMIN') {
    return true;
  }
  
  // DIRECTOR puede editar en su escuela (excepto datos laborales)
  if ($rolNorm === 'DIRECTOR') {
    $entidadesPermitidas = ['estudiantes', 'cursos', 'apoderados', 'usuarios', 'documentos', 'asignaciones'];
    return in_array(strtolower($entidad), $entidadesPermitidas, true);
  }
  
  // PROFESIONAL solo puede editar su propio perfil
  return false;
}

/**
 * Verifica si el usuario puede eliminar registros en una entidad
 * 
 * @param string $rol Rol del usuario
 * @param string $entidad Nombre de la entidad
 * @return bool True si puede eliminar, false si no
 */
function puedeEliminar(string $rol, string $entidad): bool {
  $rolNorm = ensureRole($rol);
  
  // Solo ADMIN puede eliminar
  if ($rolNorm === 'ADMIN') {
    return true;
  }
  
  // DIRECTOR puede eliminar asignaciones de su escuela
  if ($rolNorm === 'DIRECTOR' && strtolower($entidad) === 'asignaciones') {
    return true;
  }
  
  return false;
}

// ============================================================================
// HELPERS DE SESIÓN Y SEGURIDAD
// ============================================================================

/**
 * Genera un token CSRF para formularios
 * 
 * @return string Token CSRF
 */
function generarCSRFToken(): string {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  
  return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF
 * 
 * @param string $token Token a validar
 * @return bool True si es válido, false si no
 */
function validarCSRFToken(string $token): bool {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  
  $tokenSesion = $_SESSION['csrf_token'] ?? '';
  
  return $tokenSesion !== '' && hash_equals($tokenSesion, $token);
}

/**
 * Requiere un token CSRF válido para continuar
 * Si no es válido, envía 400 y termina ejecución
 */
function requireCSRF(): void {
  $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
  
  if (!validarCSRFToken($token)) {
    http_response_code(400);
    die('Solicitud inválida: Token CSRF no válido o faltante.');
  }
}

/**
 * Genera una contraseña temporal segura
 * 
 * @param int $longitud Longitud de la contraseña (mínimo 8)
 * @return string Contraseña generada
 */
function generarPasswordTemporal(int $longitud = 12): string {
  if ($longitud < 8) {
    $longitud = 8;
  }
  
  $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
  $password = '';
  $maxIndex = strlen($caracteres) - 1;
  
  for ($i = 0; $i < $longitud; $i++) {
    $password .= $caracteres[random_int(0, $maxIndex)];
  }
  
  return $password;
}

/**
 * Valida la fortaleza de una contraseña
 * 
 * @param string $password Contraseña a validar
 * @return array ['valida' => bool, 'errores' => array]
 */
function validarPasswordFortaleza(string $password): array {
  $errores = [];
  
  // Longitud mínima
  if (strlen($password) < 8) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres';
  }
  
  // Al menos una letra minúscula
  if (!preg_match('/[a-z]/', $password)) {
    $errores[] = 'Debe contener al menos una letra minúscula';
  }
  
  // Al menos una letra mayúscula
  if (!preg_match('/[A-Z]/', $password)) {
    $errores[] = 'Debe contener al menos una letra mayúscula';
  }
  
  // Al menos un número
  if (!preg_match('/[0-9]/', $password)) {
    $errores[] = 'Debe contener al menos un número';
  }
  
  return [
    'valida' => empty($errores),
    'errores' => $errores
  ];
}

// ============================================================================
// HELPERS DE LOGGING
// ============================================================================

/**
 * Registra un evento de seguridad en el log
 * 
 * @param string $tipo Tipo de evento (login_success, login_fail, access_denied, etc.)
 * @param string $mensaje Mensaje descriptivo
 * @param array $contexto Contexto adicional (usuario, IP, etc.)
 */
function logEventoSeguridad(string $tipo, string $mensaje, array $contexto = []): void {
  $logDir = __DIR__ . '/../logs';
  
  // Crear directorio si no existe
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
  }
  
  $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
  
  $entrada = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tipo' => $tipo,
    'mensaje' => $mensaje,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'contexto' => $contexto
  ];
  
  $linea = json_encode($entrada, JSON_UNESCAPED_UNICODE) . PHP_EOL;
  
  @file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Registra un intento de login fallido
 * 
 * @param string $username Usuario que intentó ingresar
 * @param string $razon Razón del fallo
 */
function logLoginFallido(string $username, string $razon): void {
  logEventoSeguridad('login_fail', "Intento fallido de login: $razon", [
    'username' => $username,
    'razon' => $razon
  ]);
}

/**
 * Registra un login exitoso
 * 
 * @param string $username Usuario que ingresó
 * @param string $rol Rol del usuario
 */
function logLoginExitoso(string $username, string $rol): void {
  logEventoSeguridad('login_success', "Login exitoso", [
    'username' => $username,
    'rol' => $rol
  ]);
}

/**
 * Registra un acceso denegado
 * 
 * @param string $recurso Recurso al que intentó acceder
 * @param string $razon Razón de la denegación
 */
function logAccesoDenegado(string $recurso, string $razon): void {
  $usuario = $_SESSION['usuario'] ?? null;
  
  logEventoSeguridad('access_denied', "Acceso denegado a: $recurso", [
    'recurso' => $recurso,
    'razon' => $razon,
    'usuario' => $usuario ? $usuario['nombre'] : 'desconocido',
    'rol' => $usuario ? $usuario['permisos'] : 'guest'
  ]);
}

// ============================================================================
// HELPERS DE FORMATEO
// ============================================================================

/**
 * Formatea un nombre completo (Primera letra mayúscula en cada palabra)
 * 
 * @param string $nombre Nombre a formatear
 * @return string Nombre formateado
 */
function formatearNombre(string $nombre): string {
  return mb_convert_case(trim($nombre), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Formatea una fecha en formato chileno (DD-MM-YYYY)
 * 
 * @param string $fecha Fecha en formato SQL (YYYY-MM-DD)
 * @return string Fecha formateada o cadena vacía si es inválida
 */
function formatearFechaChilena(string $fecha): string {
  if (empty($fecha) || $fecha === '0000-00-00') {
    return '';
  }
  
  $timestamp = strtotime($fecha);
  
  if ($timestamp === false) {
    return '';
  }
  
  return date('d-m-Y', $timestamp);
}

/**
 * Formatea una fecha y hora en formato chileno (DD-MM-YYYY HH:MM)
 * 
 * @param string $fechaHora Fecha y hora en formato SQL
 * @return string Fecha y hora formateada
 */
function formatearFechaHoraChilena(string $fechaHora): string {
  if (empty($fechaHora) || $fechaHora === '0000-00-00 00:00:00') {
    return '';
  }
  
  $timestamp = strtotime($fechaHora);
  
  if ($timestamp === false) {
    return '';
  }
  
  return date('d-m-Y H:i', $timestamp);
}

/**
 * Calcula la edad a partir de una fecha de nacimiento
 * 
 * @param string $fechaNacimiento Fecha de nacimiento (YYYY-MM-DD)
 * @return int|null Edad en años o null si la fecha es inválida
 */
function calcularEdad(string $fechaNacimiento): ?int {
  if (empty($fechaNacimiento) || $fechaNacimiento === '0000-00-00') {
    return null;
  }
  
  $nacimiento = new DateTime($fechaNacimiento);
  $hoy = new DateTime();
  
  $edad = $hoy->diff($nacimiento)->y;
  
  return $edad;
}

/**
 * Trunca un texto a una longitud específica agregando "..."
 * 
 * @param string $texto Texto a truncar
 * @param int $longitud Longitud máxima
 * @return string Texto truncado
 */
function truncarTexto(string $texto, int $longitud = 100): string {
  $texto = trim($texto);
  
  if (mb_strlen($texto) <= $longitud) {
    return $texto;
  }
  
  return mb_substr($texto, 0, $longitud) . '...';
}

// ============================================================================
// HELPERS DE MENSAJES FLASH
// ============================================================================

/**
 * Establece un mensaje flash para mostrar en la siguiente página
 * 
 * @param string $tipo Tipo de mensaje (success, error, warning, info)
 * @param string $mensaje Mensaje a mostrar
 */
function setFlashMessage(string $tipo, string $mensaje): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  
  $_SESSION['flash_message'] = [
    'tipo' => $tipo,
    'mensaje' => $mensaje
  ];
}

/**
 * Obtiene y elimina el mensaje flash
 * 
 * @return array|null ['tipo' => string, 'mensaje' => string] o null si no hay mensaje
 */
function getFlashMessage(): ?array {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  
  if (!isset($_SESSION['flash_message'])) {
    return null;
  }
  
  $mensaje = $_SESSION['flash_message'];
  unset($_SESSION['flash_message']);
  
  return $mensaje;
}

/**
 * Muestra un mensaje flash como HTML Bootstrap
 * 
 * @return string HTML del mensaje o cadena vacía si no hay mensaje
 */
function displayFlashMessage(): string {
  $flash = getFlashMessage();
  
  if ($flash === null) {
    return '';
  }
  
  $clasesBootstrap = [
    'success' => 'alert-success',
    'error' => 'alert-danger',
    'warning' => 'alert-warning',
    'info' => 'alert-info'
  ];
  
  $clase = $clasesBootstrap[$flash['tipo']] ?? 'alert-info';
  $mensaje = htmlspecialchars($flash['mensaje']);
  
  return "<div class='alert $clase alert-dismissible fade show' role='alert'>
            $mensaje
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}

// ============================================================================
// FIN DEL ARCHIVO roles.php
// ============================================================================