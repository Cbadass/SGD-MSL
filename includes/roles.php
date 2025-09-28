<?php
// includes/roles.php
const ALLOWED_ROLES = ['PROFESIONAL','ADMIN','DIRECTOR'];

function normalizaRol(?string $r): string {
  return strtoupper(trim((string)$r));
}

function rolValido(string $r): bool {
  return in_array($r, ALLOWED_ROLES, true);
}

function requireAnyRole(array $rolesPermitidos): void {
  $rol = $_SESSION['usuario']['permisos'] ?? '';
  if (!in_array($rol, $rolesPermitidos, true)) {
    http_response_code(403);
    exit('No autorizado');
  }
}
// includes/roles.php
const ALLOWED_ROLES = ['PROFESIONAL','ADMIN','DIRECTOR'];

function rolActual(): string {
  return $_SESSION['usuario']['permisos'] ?? 'GUEST';
}
function esAdminODirector(): bool {
  $r = rolActual();
  return $r === 'ADMIN' || $r === 'DIRECTOR';
}
function esDuenioPerfil(int $idProfesional): bool {
  return (int)($_SESSION['usuario']['id_profesional'] ?? 0) === $idProfesional;
}
function nullIfEmpty(?string $v) {
  $v = isset($v) ? trim($v) : null;
  return ($v === '' ? null : $v);
}

