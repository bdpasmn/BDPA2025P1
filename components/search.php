<!DOCTYPE html><html>
<head>
<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);


$_SESSION['username'] = 'test_user';

// Collect search parameters (GET method for example)
$title    = $_GET['title'] ?? '';
$body     = $_GET['body'] ?? '';
$creator  = $_GET['creator'] ?? '';
$fromTime = $_GET['from_time'] ?? null;
$toTime   = $_GET['to_time'] ?? null;

$query = "SELECT * FROM questions WHERE 1=1";
$params = [];

// Add filters dynamically
if (!empty($title)) {
    $query .= " AND title ILIKE :title";
    $params[':title'] = '%' . $title . '%';
}
if (!empty($body)) {
    $query .= " AND body ILIKE :body";
    $params[':body'] = '%' . $body . '%';
}
if (!empty($creator)) {
    $query .= " AND creator ILIKE :creator";
    $params[':creator'] = '%' . $creator . '%';
}
if (!empty($fromTime)) {
    $query .= " AND creation_time >= :from_time";
    $params[':from_time'] = $fromTime;
}
if (!empty($toTime)) {
    $query .= " AND creation_time <= :to_time";
    $params[':to_time'] = $toTime;
}

// Default order: newest first
$query .= " ORDER BY creation_time DESC";
header('Content-Type: application/json');
echo json_encode($results);
?>
</head>
<body> 
    <h2>Search Questions</h2>
    <form method="get" action="search.php">
        <input type="text" name="title" placeholder="Title" value="<?php echo htmlspecialchars($title); ?>">
        <input type="text" name="body" placeholder="Body" value="<?php echo htmlspecialchars($body); ?>">
        <input type="text" name="creator" placeholder="Creator" value="<?php echo htmlspecialchars($creator); ?>">
        <input type="date" name="from_time" value="<?php echo htmlspecialchars($fromTime); ?>">
        <input type="date" name="to_time" value="<?php echo htmlspecialchars($toTime); ?>">
        <button type="submit">Search</button>
    </form>
    <!-- You can add a section here to display results if desired -->
</body>
</html>