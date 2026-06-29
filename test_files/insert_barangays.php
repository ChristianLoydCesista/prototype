<?php
// insert_barangays.php
$host = "localhost";
$user = "root";
$pass = "";
$db = "barangay_ci_system";
$conn = new mysqli($host, $user, $pass, $db);

// 27 Barangays of Arteche with approximate coordinates
$barangays = [
    ['name' => 'Aguinaldo', 'lat' => 12.264, 'lon' => 125.487],
    ['name' => 'Balud', 'lat' => 12.251, 'lon' => 125.501],
    ['name' => 'Bato', 'lat' => 12.267, 'lon' => 125.472],
    ['name' => 'Batalay', 'lat' => 12.293, 'lon' => 125.398],
    ['name' => 'Beri', 'lat' => 12.285, 'lon' => 125.416],
    ['name' => 'Bigo', 'lat' => 12.275, 'lon' => 125.434],
    ['name' => 'Bonifacio', 'lat' => 12.304, 'lon' => 125.383],
    ['name' => 'Buenavista', 'lat' => 12.312, 'lon' => 125.367],
    ['name' => 'Buluan', 'lat' => 12.328, 'lon' => 125.352],
    ['name' => 'Campacion', 'lat' => 12.294, 'lon' => 125.453],
    ['name' => 'Carapdapan', 'lat' => 12.334, 'lon' => 125.505],
    ['name' => 'Casidman', 'lat' => 12.321, 'lon' => 125.489],
    ['name' => 'Catumsan', 'lat' => 12.357, 'lon' => 125.471],
    ['name' => 'Central', 'lat' => 12.265, 'lon' => 125.523],
    ['name' => 'Dantu', 'lat' => 12.347, 'lon' => 125.433],
    ['name' => 'Gamuton', 'lat' => 12.336, 'lon' => 125.418],
    ['name' => 'Inayawan', 'lat' => 12.358, 'lon' => 125.388],
    ['name' => 'Maca-anga', 'lat' => 12.279, 'lon' => 125.545],
    ['name' => 'Magsaysay', 'lat' => 12.324, 'lon' => 125.536],
    ['name' => 'Matin-ab', 'lat' => 12.289, 'lon' => 125.557],
    ['name' => 'Poblacion', 'lat' => 12.263, 'lon' => 125.502],
    ['name' => 'San Isidro', 'lat' => 12.348, 'lon' => 125.457],
    ['name' => 'Santa Cruz', 'lat' => 12.276, 'lon' => 125.518],
    ['name' => 'Tapican', 'lat' => 12.315, 'lon' => 125.426],
    ['name' => 'Tawagan', 'lat' => 12.302, 'lon' => 125.442],
    ['name' => 'Teguis', 'lat' => 12.287, 'lon' => 125.476],
    ['name' => 'Tudela', 'lat' => 12.272, 'lon' => 125.492]
];

foreach ($barangays as $barangay) {
    $name = $barangay['name'];
    $lat = $barangay['lat'];
    $lon = $barangay['lon'];
    
    $sql = "INSERT INTO barangays (name, latitude, longitude) 
            VALUES ('$name', '$lat', '$lon')
            ON DUPLICATE KEY UPDATE latitude='$lat', longitude='$lon'";
    
    if ($conn->query($sql)) {
        echo "Added/Updated: $name<br>";
    } else {
        echo "Error with $name: " . $conn->error . "<br>";
    }
}

echo "<h3>27 Barangays of Arteche added successfully!</h3>";
?>