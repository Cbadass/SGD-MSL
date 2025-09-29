<?php
// includes/roles.php

// Evita re-definir la constante si se incluye más de una vez
if (!defined('ALLOWED_ROLES')) {
  define('ALLOWED_ROLES', ['PROFESIONAL','ADMIN','DIRECTOR']);
}

function normalizaRol(?string $r): string {
  return strtoupper(trim((string)$r));
}
function rolValido(string $r): bool {
  return in_array(strtoupper($r), ALLOWED_ROLES, true);
}

function rolActual(): string {
  return strtoupper($_SESSION['usuario']['permisos'] ?? 'GUEST');
}

function requireAnyRole(array $rolesPermitidos): void {
  $rol = rolActual();
  if (!in_array($rol, $rolesPermitidos, true)) {
    http_response_code(403);
    exit('No autorizado');
  }
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
