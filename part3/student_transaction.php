<?php
session_start();

/* ===============================
   DATABASE CONNECTION
================================ */
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "school_inventory";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   ADMIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}


/* ===============================
   FETCH TRANSACTIONS
================================ */
$sql = "
    SELECT 
        t.transaction_id,
        t.date_borrowed,
        t.date_returned,
        b.borrower_name,
        b.borrower_type,
        b.borrower_id,
        b.course,
        b.year,
        bo.Title
    FROM transactions t
    LEFT JOIN borrower b ON t.borrower_id = b.borrower_id
    LEFT JOIN books bo ON t.book_id = bo.book_id
    ORDER BY t.transaction_id DESC
";

$transactions = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrower Transaction</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<aside class="sidebar">
    <h2>BORROWER</h2>
    <ul>
        <li><a href="Books.php">BOOKS</a></li>
        <li><a href="student_transaction.php" class="active">Transaction History</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>


<!-- ===============================
     MAIN CONTENT
================================ -->
<div class="main">
    <h1>Transaction History</h1>

    <input 
        type="text" 
        id="searchInput" 
        placeholder="Search..." 
        onkeyup="searchTable()"
    >

    <table id="transactionTable">
        <thead>
            <tr>
             <th>ID</th>
                <th>BOOK</th>
                <th>FIRST NAME / MIDDLE NAME / LAST NAME</th>
                <th>BORROWER ID</th>
                <th>BORROWER TYPE</th>
                <th>COURSE</th>
                <th>YEAR</th>
                <th>DATE BORROWED</th>
                <th>DATE RETURNED</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($transactions && $transactions->num_rows > 0): ?>
                <?php while ($row = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                        <td><?= htmlspecialchars($row['Title'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['borrower_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['borrower_id'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['borrower_type'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['course'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['year'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['date_borrowed']) ?></td>
                        <td><?= htmlspecialchars($row['date_returned'] ?? '-') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align:center;">No transactions found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===============================
     SEARCH SCRIPT
================================ -->
<script>
function searchTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let rows = document.querySelectorAll("#transactionTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
    });
}
</script>

</body>
</html>
