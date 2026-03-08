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

/* FETCH DISTINCT SHELVES */
$shelves = $conn->query("SELECT DISTINCT Shelf_Location FROM books");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Library Books</title>
<link rel="stylesheet" href="books.css">
<style>

.shelf-filter button {
    margin: 3px;
    padding: 5px 10px;
    cursor: pointer;
}
.add-book-btn {
    margin-top: 5px;
    padding: 5px 10px;
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

<h2>Book List</h2>

<!-- SEARCH -->
<div class="search-box">
<input type="text" id="search" placeholder="🔍 Search books by title, author, or call number...">
</div>

<!-- SHELF FILTER -->
<div class="shelf-buttons">
<button onclick="filterShelf('all')">All Books</button>
<?php
while($s = $shelves->fetch_assoc()){
    $shelf = htmlspecialchars($s['Shelf_Location']);
    echo "<button onclick=\"filterShelf('$shelf')\">$shelf</button>";
}
?>
</div>

<table id="bookTable">
<thead>
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
</thead>
<tbody>
<?php while ($row = $books->fetch_assoc()): ?>
<tr data-shelf="<?= htmlspecialchars($row['Shelf_Location']) ?>">

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
while($b = $borrowers_result->fetch_assoc()){
    $borrowers[] = $b['borrower_name'];
}
if(count($borrowers)==0){
    echo "—";
}elseif(count($borrowers)==1){
    echo htmlspecialchars($borrowers[0]);
}else{
    echo '<select class="borrower-select">';
    foreach($borrowers as $name){
        echo "<option>".htmlspecialchars($name)."</option>";
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
$reservers=[];
while($r=$reserve_result->fetch_assoc()){
    $reservers[]=$r['borrower_name'];
}
if(count($reservers)==0){
    echo "—";
}elseif(count($reservers)==1){
    echo htmlspecialchars($reservers[0]);
}else{
    echo '<select class="borrower-select">';
    foreach($reservers as $name){
        echo "<option>".htmlspecialchars($name)."</option>";
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
$dates=[];
while($d=$date_result->fetch_assoc()){
    $dates[]=date("M d, Y h:i A",strtotime($d['reserve_date']));
}
if(count($dates)==0){
    echo "—";
}elseif(count($dates)==1){
    echo $dates[0];
}else{
    echo '<select class="borrower-select">';
    foreach($dates as $date){
        echo "<option>$date</option>";
    }
    echo '</select>';
}
?>
</td>

<!-- RESERVE BUTTON -->
<td>
<?php if($row['available']>0): ?>
<form method="GET" action="reserve.php">
<input type="hidden" name="book_id" value="<?= $book_id ?>">
<button class="reserve-btn">RESERVE</button>
</form>
<?php else: ?>
<form method="GET" action="reserve.php">
<input type="hidden" name="book_id" value="<?= $book_id ?>">
<input type="hidden" name="reserve_wait" value="3-7">
<button class="reserve-wait-btn">Reserve (3-7 days)</button>
</form>
<?php endif; ?>
</td>

</tr>
<?php endwhile; ?>
</tbody>
</table>

</main>
</div>

<!-- ======= SEARCH + SHELF FILTER SCRIPT ======= -->
<script>
const searchInput = document.getElementById("search");
const rows = document.querySelectorAll("#bookTable tbody tr");
let activeShelf = "all";

// Cache row data once for faster search
const rowData = Array.from(rows).map(row => ({
    row,
    text: row.textContent.toLowerCase().replace(/\s+/g,' ').trim(),
    shelf: row.getAttribute("data-shelf")
}));

// Search function
function filterTable() {
    const filter = searchInput.value.toLowerCase().trim();
    rowData.forEach(({ row, text, shelf }) => {
        const matchSearch = text.includes(filter);
        const matchShelf = (activeShelf === "all") || (shelf === activeShelf);
        row.style.display = (matchSearch && matchShelf) ? "" : "none";
    });
}

// Event listener for search input
searchInput.addEventListener("input", filterTable);

// Shelf filter function
function filterShelf(shelf) {
    activeShelf = shelf;
    filterTable();
}
</script>

</body>
</html>