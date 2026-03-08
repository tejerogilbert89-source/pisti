<?php
session_start();
date_default_timezone_set('Asia/Manila');

/* ===============================
   DATABASE CONNECTION
================================ */
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "school_inventory";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* ===============================
   LOGIN CHECK
================================ */
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

/* ===============================
   FETCH BOOKS
================================ */
$sql = "SELECT * FROM books ORDER BY book_id DESC";
$books = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Library Books</title>
<link rel="stylesheet" href="books.css">
<style>
.reserve-btn{
    font-size:16px;
    font-weight:bold;
    letter-spacing:1px;
    padding:6px 12px;
    text-transform:uppercase;
    cursor:pointer;
    border:none;
    border-radius:5px;
    color:white;
    background-color:#2e7d32; /* green */
    transition: background 0.3s;
}
.reserve-btn:hover{
    background-color:#1b5e20;
}
.reserve-wait-btn{
    font-size:16px;
    font-weight:bold;
    letter-spacing:1px;
    padding:6px 12px;
    text-transform:uppercase;
    cursor:pointer;
    border:none;
    border-radius:5px;
    color:white;
    background-color:orange;
    transition: background 0.3s;
}
.borrower-select{
    padding:4px 6px;
    font-size:14px;
}
.reserve-qty{
    padding:4px 6px;
    font-size:14px;
    width:50px;
    margin-right:5px;
}
</style>
</head>
<body>
<div class="container">

<aside class="sidebar">
    <h2>BORROWER</h2>
    <ul>
        <li><a class="active" href="#">Books</a></li>
        <li><a href="student_transaction.php">My Transactions</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</aside>

<main class="main">

<?php if (isset($_SESSION['message'])): ?>
<p style="color:green">
<?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
</p>
<?php endif; ?>

<h2>Book List</h2>
<input type="text" id="search" placeholder="Search books...">

<table id="bookTable" border="1" cellpadding="5" cellspacing="0">
<tr>
    <th>ID</th>
    <th>CALL NO</th>
    <th>TITLE</th>
    <th>AUTHOR</th>
    <th>EDITION</th>
    <th>IMPRINT</th>
    <th>ACCESSION</th>
    <th>COPIES</th>
    <th>SHELF</th>
    <th>STATUS</th>
    <th>AVAILABLE</th>
    <th>FIRST/MIDDLE/LAST</th>
    <th>RESERVER</th>
    <th>RESERVE DATE</th>
    <th>ACTIONS</th>
</tr>

<?php while ($row = $books->fetch_assoc()): ?>
<tr>
    <td><?= $row['book_id'] ?></td>
    <td><?= htmlspecialchars($row['Call_Number']) ?></td>
    <td><?= htmlspecialchars($row['Title']) ?></td>
    <td><?= htmlspecialchars($row['Author']) ?></td>
    <td><?= htmlspecialchars($row['Edition'] ?: 'N/A') ?></td>
    <td><?= htmlspecialchars($row['Imprint']) ?></td>
    <td><?= htmlspecialchars($row['Accession_Number']) ?></td>
    <td><?= $row['Copies'] ?></td>
    <td><?= htmlspecialchars($row['Shelf_Location']) ?></td>
    <td><?= $row['status'] ?></td>
    <td><?= $row['available'] ?></td>

    <!-- CURRENT BORROWERS -->
    <td>
    <?php
    $book_id = $row['book_id'];
    $borrowers_result = $conn->query("
        SELECT br.borrower_name 
        FROM transactions t
        JOIN borrower br ON t.borrower_id = br.borrower_id
        WHERE t.book_id = $book_id 
        AND t.date_returned IS NULL
    ");
    $borrowers = [];
    while ($b = $borrowers_result->fetch_assoc()) {
        $borrowers[] = $b['borrower_name'];
    }
    if (count($borrowers) === 0) {
        echo "—";
    } elseif (count($borrowers) === 1) {
        echo htmlspecialchars($borrowers[0]);
    } else {
        echo '<select class="borrower-select">';
        foreach ($borrowers as $b_name) {
            echo '<option>' . htmlspecialchars($b_name) . '</option>';
        }
        echo '</select>';
    }
    ?>
    </td>

    <!-- RESERVERS -->
    <td>
    <?php
    $reserve_result = $conn->query("
        SELECT borrower_name 
        FROM reservations 
        WHERE book_id = $book_id
        ORDER BY reserve_date ASC
    ");
    $reservers = [];
    while ($r = $reserve_result->fetch_assoc()) {
        $reservers[] = $r['borrower_name'];
    }
    if (count($reservers) === 0) {
        echo "—";
    } elseif (count($reservers) === 1) {
        echo htmlspecialchars($reservers[0]);
    } else {
        echo '<select class="borrower-select">';
        foreach ($reservers as $name) {
            echo '<option>' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
    }
    ?>
    </td>

    <!-- RESERVE DATES -->
    <td>
    <?php
    $date_result = $conn->query("
        SELECT reserve_date 
        FROM reservations 
        WHERE book_id = $book_id
        ORDER BY reserve_date ASC
    ");
    $dates = [];
    while ($d = $date_result->fetch_assoc()) {
        $dates[] = date("M d, Y h:i A", strtotime($d['reserve_date']));
    }
    if (count($dates) === 0) {
        echo "—";
    } elseif (count($dates) === 1) {
        echo $dates[0];
    } else {
        echo '<select class="borrower-select">';
        foreach ($dates as $date) {
            echo '<option>' . $date . '</option>';
        }
        echo '</select>';
    }
    ?>
    </td>

    <!-- RESERVE BUTTON -->
    <td>
    <?php if ($row['available'] > 0): ?>
        <form method="GET" action="reserve.php">
            <input type="hidden" name="book_id" value="<?= $book_id ?>">
            <button type="submit" class="reserve-btn">RESERVE</button>
        </form>
    <?php else: ?>
        <form method="GET" action="reserve.php">
            <input type="hidden" name="book_id" value="<?= $book_id ?>">
            <input type="hidden" name="reserve_wait" value="3-7">
            <button type="submit" class="reserve-wait-btn">
                Reserve (3-7 days)
            </button>
        </form>
    <?php endif; ?>
    </td>

</tr>
<?php endwhile; ?>
</table>

</main>
</div>

<!-- SEARCH SCRIPT -->
<script>
const searchInput = document.getElementById("search");
const rows = Array.from(document.querySelectorAll("#bookTable tr")).slice(1);

let debounceTimeout = null;

searchInput.addEventListener("input", function () {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        const filter = this.value.trim().toLowerCase();
        rows.forEach(row => {
            const cells = row.querySelectorAll("td");
            const text = [
                cells[1]?.textContent,
                cells[2]?.textContent,
                cells[3]?.textContent,
                cells[6]?.textContent
            ].join(" ").toLowerCase();
            row.style.display = text.includes(filter) ? "" : "none";
        });
    }, 200);
});
</script>

</body>
</html>