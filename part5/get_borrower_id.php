<?php
session_start();

// Ensure the correct path to your config file
require_once __DIR__ . '/config.php';  // Adjust the path as necessary

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get borrower details from the request
    $first_name = strtoupper(trim($_POST['first_name']));
    $middle_name = strtoupper(trim($_POST['middle_name']));
    $last_name = strtoupper(trim($_POST['last_name']));

    // Query to fetch borrower ID based on name
    $stmt = $conn->prepare("SELECT borrower_id FROM borrower WHERE first_name = ? AND middle_name = ? AND last_name = ?");
    $stmt->bind_param("sss", $first_name, $middle_name, $last_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo $row['borrower_id']; // Return the borrower ID
    } else {
        echo ''; // Return an empty string if no match found
    }

    $stmt->close();
}
?>
