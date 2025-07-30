<?php  
session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once '../../api/key.php';
require_once '../../api/api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Question Not Found • qOverflow</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .error-icon-filter {
      filter: brightness(0) invert(1);
    }
    .text-shadow {
      text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }
    .text-shadow-sm {
      text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    }
  </style>
</head>
<body class="bg-gray-900 text-white font-sans flex flex-col min-h-screen">

<?php
if (isset($_SESSION['username'])) {
    include '../../components/navBarLogin.php';
} else {
    include '../../components/navBarLogOut.php';
}
?>

<div class="flex-grow flex items-center justify-start px-5 max-w-4xl w-full mx-auto">

  <div class="w-full">
    <div class="flex items-center justify-start flex-row mb-5 ml-7">
      <div class="mr-5 flex-shrink-0">
        <img src="https://static.thenounproject.com/png/1648939-200.png" 
             alt="Question Not Found Icon" 
             class="w-28 h-auto error-icon-filter lg:w-32 md:w-25 sm:w-20">
      </div>
      <h1 class="text-5xl font-bold text-white text-shadow lg:text-6xl md:text-5xl sm:text-4xl">
        Question Not Found
      </h1>
    </div>

    <p class="text-2xl my-5 ml-12 text-shadow-sm leading-relaxed sm:ml-5">
        Sorry, we couldn’t find the question that you are looking for...
    </p>

    <div class="bg-gray-800 p-4 rounded-xl ml-12 mt-5 border border-gray-700 shadow-lg sm:ml-5">
      <p class="text-gray-300 text-base mb-2">
        <strong class="text-white">Here are some possible reasons:</strong>
      </p>
      <ul class="list-disc list-inside text-gray-300 text-base space-y-1">
        <li>The question was removed from our system</li>
        <li>The question is outdated</li>
        <li>You typed the URL incorrectly</li>
      </ul>
    </div>

    <p class="text-2xl my-5 ml-12 text-shadow-sm leading-relaxed sm:ml-5">
      You can try opening the question again or head back to the qOverflow Buffet.
    </p>
  </div>

</div>

<style>
  @media (max-width: 500px) {
    .flex-row {
      flex-direction: column !important;
      text-align: left !important; /* keep left aligned */
      margin-left: 0 !important;
    }
    .mr-5 {
      margin-right: 0 !important;
      margin-bottom: 0.625rem !important;
    }
    .ml-12, .sm\:ml-5 {
      margin-left: 0 !important;
      text-align: left !important; /* keep left aligned */
    }
  }
</style>
</body>
</html>
