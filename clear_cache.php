<?php
header('Content-Type: application/json');

$file = sys_get_temp_dir() . '/fm_listone_v3.json';

if (file_exists($file)) {
    unlink($file);
    echo json_encode(['status' => 'ok', 'message' => 'Cache invalidata']);
} else {
    echo json_encode(['status' => 'ok', 'message' => 'Nessuna cache presente']);
}
?>