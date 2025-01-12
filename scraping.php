<?php
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = :dbname";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['dbname' => $dbname]);

    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $table) {
        echo $table['table_name'] . "\n";
    }
} catch (PDOException $e) {
    echo "Помилка: " . $e->getMessage();
}
