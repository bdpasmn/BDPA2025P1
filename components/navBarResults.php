<?php
$query = $_GET['query'] ?? '';
$datetime = $_GET['datetime'] ?? '';

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Search Results</title>
  <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white p-6 font-sans">

  <h1 class="text-3xl font-bold mb-4">Search Results</h1>

  <?php if (!empty($query)): ?>
    <p class="mb-2">Search Query: <span class="text-blue-400"><?= h($query) ?></span></p>
  <?php endif; ?>

  <?php if (!empty($datetime)): ?>
    <?php
      $unix = strtotime($datetime);
    ?>
    <p class="mb-2">
      Date & Time: <span class="text-blue-400"><?= h($datetime) ?></span><br>
      Unix Timestamp: <span class="text-green-400"><?= $unix !== false ? $unix : 'Invalid date' ?></span>
    </p>
  <?php endif; ?>

  <?php if (empty($query) && empty($datetime)): ?>
    <p class="text-red-400">No search input provided.</p>
  <?php endif; ?>

</body>
</html>