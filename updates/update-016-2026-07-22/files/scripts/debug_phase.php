<?php
// Quick debug: show start_date, current week, and first 5 phase week ranges
$data = json_decode(file_get_contents(__DIR__ . '/../storage/app/roadmap/AI_Engineering_Roadmap_v3_1.json'), true);
$startDate = $data['meta']['start_date'];
$start = new DateTime($startDate);
$now = new DateTime();
$diffDays = (int)$start->diff($now)->days;
$currentWeek = max(1, intdiv($diffDays, 7) + 1);

echo "start_date : $startDate\n";
echo "today      : " . $now->format('Y-m-d') . "\n";
echo "diff days  : $diffDays\n";
echo "currentWeek: $currentWeek\n\n";

foreach (array_slice($data['phases'], 0, 5) as $p) {
    echo "Phase {$p['phase']}: {$p['title']} | weeks: {$p['weeks']}\n";
}
