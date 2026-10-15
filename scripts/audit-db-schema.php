<?php
require_once __DIR__ . '/../api/db.php';

echo "=== AUDIT USERS TABLE ===\n";
$q = $pdo->query("DESCRIBE users");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Def:{$r['Default']}\n";
}

echo "\n=== AUDIT STUDENT_RECORDS TABLE ===\n";
$q = $pdo->query("DESCRIBE student_records");
while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['Field']} | {$r['Type']} | Null:{$r['Null']} | Def:{$r['Default']}\n";
}

echo "\n=== CHECK UNLINKED STUDENT RECORDS ===\n";
$unlinked = $pdo->query("SELECT id, student_id, name, user_id FROM student_records WHERE user_id IS NULL OR user_id = ''")->fetchAll(PDO::FETCH_ASSOC);
echo "Unlinked student records count: " . count($unlinked) . "\n";
foreach (array_slice($unlinked, 0, 5) as $u) {
    echo "  {$u['student_id']} - {$u['name']}\n";
}

echo "\n=== CHECK DUPLICATE USERS BY STUDENT_ID ===\n";
$dups = $pdo->query("SELECT student_id, COUNT(*) as cnt FROM users WHERE student_id IS NOT NULL AND student_id != '' GROUP BY student_id HAVING cnt > 1")->fetchAll(PDO::FETCH_ASSOC);
echo "Duplicate student accounts count: " . count($dups) . "\n";
foreach ($dups as $d) {
    echo "  {$d['student_id']} appears {$d['cnt']} times\n";
}

echo "\n=== TEST CURRENT SURNAME EXTRACTION ===\n";
$names = ['Juan Dela Cruz', 'Roberto Dela Cruz Jr.', 'Antonio De Los Santos', 'Juan Santos III', 'Maria De Jesus', 'Mark Smith-Cruz', 'Cherilyn', 'Dela Cruz, Juan M. Jr.'];
foreach ($names as $n) {
    echo $n . ' => ' . ncst_extract_surname($n) . "\n";
}

