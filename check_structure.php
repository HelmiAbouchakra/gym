<?php
require_once __DIR__ . '/includes/db_connect.php';
$pdo = getConnection();

echo "<h2>Database Table Structures</h2>";

$tables = ['users', 'trainers', 'classes', 'class_schedules', 'class_bookings'];

foreach ($tables as $table) {
    try {
        echo "<h3>Table: $table</h3>";
        $result = $pdo->query("DESCRIBE $table");
        echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p>Error checking table $table: " . $e->getMessage() . "</p>";
    }
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
table { width: 100%; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
