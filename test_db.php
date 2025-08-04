<?php
require_once 'C:\xampp\config\snmp_config.php';
require_once 'C:\xampp\htdocs\snmp_simple\db.php';
try {
    $stmt = $conn->query("DESCRIBE historique_ports");
    echo "Structure de historique_ports :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>