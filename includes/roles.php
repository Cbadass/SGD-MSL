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
