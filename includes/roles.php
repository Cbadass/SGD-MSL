<?php
// includes/roles.php
// Estándar del proyecto: 3 roles (ADMIN, DIRECTOR, PROFESIONAL).
// - Rol se deriva automáticamente desde el CARGO (ver rolDesdeCargo).
// - Alcances:
//    * ADMIN: total (todas las escuelas / usuarios / datos laborales).
//    * DIRECTOR: su escuela (resto restringido).
//    * PROFESIONAL: solo su propio perfil; sin datos laborales.

// -----------------------------
// Utilidades de normalización
// -----------------------------
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
function rolValido(string $rol): bool {
  $r = strtoupper(trim($rol));
  return in_array($r, ['ADMIN','DIRECTOR','PROFESIONAL'], true);
}

function ensureRole(string $rol): string {
  $r = strtoupper(trim($rol));
  return rolValido($r) ? $r : 'PROFESIONAL';
}

function isAdmin(string $rol): bool      { return ensureRole($rol) === 'ADMIN'; }
function isDirector(string $rol): bool   { return ensureRole($rol) === 'DIRECTOR'; }
function isProfesional(string $rol): bool{ return ensureRole($rol) === 'PROFESIONAL'; }

// -----------------------------
// Derivar rol desde el CARGO
//   - "Administrador(a)", "Administrador General", etc. → ADMIN
//   - "Director(a)", "Directora Académica", etc.       → DIRECTOR
//   - Cualquier otro                                    → PROFESIONAL
// -----------------------------
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
// Autorización por alcance (reglas del proyecto)
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
