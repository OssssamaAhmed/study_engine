<?php
$data = json_decode(file_get_contents(__DIR__ . '/../AI_Engineering_Roadmap_v3.1.json'), true);

echo "=== PHASES ===\n";
foreach ($data['phases'] as $p) {
    echo "Phase {$p['phase']}: {$p['title']} (Weeks {$p['weeks']})\n";
}

echo "\n=== CHECKPOINTS ===\n";
foreach ($data['checkpoints'] as $i => $cp) {
    $title = $cp['title'] ?? ($cp['name'] ?? 'Untitled');
    $phase = $cp['phase'] ?? 'N/A';
    $week = $cp['week'] ?? ($cp['due_week'] ?? 'N/A');
    echo "Checkpoint " . ($i+1) . ": {$title} (Phase {$phase}, Week {$week})\n";
    print_r(array_keys($cp));
}

echo "\n=== CERTIFICATIONS ===\n";
foreach ($data['certifications'] as $cert) {
    echo "Cert: " . json_encode($cert) . "\n";
}
