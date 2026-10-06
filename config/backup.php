<?php

return [
    'disk' => env('BACKUP_DISK', 'backup'),
    'prefix' => trim(env('BACKUP_PREFIX', 'database-backups'), '/'),
    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 30)),
    'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
];
