<?php
  if (!function_exists('normalizeAppStatusValue')) {
    function normalizeAppStatusValue(?string $status): string {
      $normalizedStatus = strtoupper(trim((string) $status));
      $normalizedStatus = preg_replace('/[^A-Z0-9]+/', '_', $normalizedStatus);
      $normalizedStatus = trim((string) $normalizedStatus, '_');

      return match ($normalizedStatus) {
        'DEPLOYED', 'DEPLOYED_MODE' => 'DEPLOYED_MODE',
        'MAINTENANCE', 'MAINTENANCE_MODE' => 'MAINTENANCE_MODE',
        default => 'MAINTENANCE_MODE',
      };
    }
  }

  $db2 = connectDatabase('DB2', PDO::ERRMODE_EXCEPTION);
  $appStatus = checkAppStatus($db2) ?? [
    'parameter' => 'app_status',
    'value' => 'MAINTENANCE_MODE',
  ];

  $appStatus['value'] = normalizeAppStatusValue($appStatus['value'] ?? null);

  $currentScriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  $currentScriptDirectory = str_replace('\\', '/', dirname($currentScriptPath));
  $isMaintenancePage = checkForEquality(basename($currentScriptDirectory), 'maintenance', 'strict');

  $logo_href = 'https://careerinstitute.co.in/';
  $logo_text = 'Career Institute';

  if (checkForEquality($appStatus['value'], 'MAINTENANCE_MODE', 'strict')) {
    if (!$isMaintenancePage) {
      redirectTo('../maintenance/', 0);
    }

    if (!headers_sent()) {
      http_response_code(503);
      header('Retry-After: 3600');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
  }
  elseif ($isMaintenancePage) {
    redirectTo('../dashboard/', 0);
  }
?>
