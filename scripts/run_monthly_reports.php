<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get the absolute root path of the project
$root_dir = dirname(__DIR__); // Goes up one level from scripts directory

// Verify dependencies exist before loading
$db_path = $root_dir . '/includes/db.php';
$vendor_path = $root_dir . '/vendor/autoload.php';

if (!file_exists($db_path)) {
    die("Error: DB file missing at: $db_path");
}
if (!file_exists($vendor_path)) {
    die("Error: Vendor autoload missing at: $vendor_path");
}

require_once $db_path;
require_once $vendor_path;

date_default_timezone_set('Asia/Manila');

// Configuration - using absolute paths
$reports = [
    'mswd' => [
        'script' => $root_dir . '/scripts/scheduled_reports/mayor/monthly_mswd_report.php',
        'output_dir' => $root_dir . '/reports/mayor/mswd/scheduled/'
    ],
    'animal' => [
        'script' => $root_dir . '/scripts/scheduled_reports/mayor/monthly_animal_report.php',
        'output_dir' => $root_dir . '/reports/mayor/animal/scheduled/'
    ],
    'assistance' => [
        'script' => $root_dir . '/scripts/scheduled_reports/vice_mayor/monthly_assistance_report.php',
        'output_dir' => $root_dir . '/reports/vice_mayor/assistance/scheduled/'
    ],
    // 'ambulance' => [
    //     'script' => $root_dir . '/scripts/scheduled_reports/vice_mayor/monthly_ambulance_report.php',
    //     'output_dir' => $root_dir . '/reports/vice_mayor/ambulance/scheduled/'
    // ]
];

// Initialize log
$logContent = "=== Report Generation Started ===\n";
$logContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
$logContent .= "Root Directory: " . $root_dir . "\n\n";

$successCount = 0;

foreach ($reports as $reportName => $config) {
    $logContent .= "Processing $reportName report...\n";
    $logContent .= "Script Path: {$config['script']}\n";
    $logContent .= "Output Dir: {$config['output_dir']}\n";
    
    try {
        // Verify and create output directory with strict permissions
        if (!file_exists($config['output_dir'])) {
            if (!mkdir($config['output_dir'], 0755, true)) {
                throw new Exception("Failed to create directory: {$config['output_dir']}");
            }
            $logContent .= "Created directory: {$config['output_dir']}\n";
        }
        
        // Verify directory is writable
        if (!is_writable($config['output_dir'])) {
            throw new Exception("Directory not writable: {$config['output_dir']}");
        }

        // Verify script exists
        if (!file_exists($config['script'])) {
            throw new Exception("Script not found: {$config['script']}");
        }

        // Include the report script
        ob_start();
        include $config['script'];
        $output = ob_get_clean();
        
        if (empty(trim($output))) {
            throw new Exception("Script produced no output");
        }
        
        $logContent .= "SUCCESS: $reportName report generated\n";
        $logContent .= "Output: " . trim($output) . "\n\n";
        $successCount++;
        
    } catch (Exception $e) {
        if (ob_get_level() > 0) ob_end_clean();
        $logContent .= "ERROR: Failed to generate $reportName report\n";
        $logContent .= "Exception: " . $e->getMessage() . "\n\n";
        continue;
    }
}

// Final summary
$logContent .= "\n=== Summary ===\n";
$logContent .= "Successful reports: $successCount\n";
$logContent .= "Failed reports: " . (count($reports) - $successCount) . "\n";

// Save log to reports directory
$log_path = $root_dir . '/scripts/report_log.txt';
if (!file_put_contents($log_path, $logContent, FILE_APPEND)) {
    $logContent .= "\nWARNING: Could not write to log file at $log_path\n";
}

// Output
header('Content-Type: text/plain');
echo $logContent;