<?php
declare(strict_types=1);

function run_auto_backup(): string {
    $source = __DIR__ . '/../data/finance.db';
    $backupDir = __DIR__ . '/../data/backups';

    if (!file_exists($source)) return "No DB to backup.";
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

    // Check last backup
    $files = glob($backupDir . '/*.db');
    $today = date('Y-m-d');
    
    foreach($files as $f) {
        if(strpos($f, $today) !== false) {
            return "Backup already exists for today.";
        }
    }

    // Perform Backup
    $dest = $backupDir . '/finance_backup_' . $today . '.db';
    if (copy($source, $dest)) {
        // Cleanup old backups (Keep last 7 days)
        $allBackups = glob($backupDir . '/*.db');
        if(count($allBackups) > 7) {
            // Sort by modified time, oldest first
            array_multisort(array_map('filemtime', $allBackups), SORT_NUMERIC, SORT_ASC, $allBackups);
            // Delete oldest until we have 7
            while(count($allBackups) > 7) {
                unlink(array_shift($allBackups));
            }
        }
        return "Backup created: " . basename($dest);
    }
    
    return "Backup failed.";
}
?>
