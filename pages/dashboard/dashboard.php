<!DOCTYPE html>
<html>
  <?php
  session_start();
  //$userId = $_SESSION['user_id'];
  require_once '../../Api/api.php';
  require_once '../../Api/key.php';


  // ✅ Hardcoded username for testing
  $username = "test_user";
  
  $api = new qOverflowAPI(API_KEY);
  $question = $api->getUserQuestions($username);

  
  // ✅ Safely grab the questions array
  $questions = $question['questions'] ?? [];
  ?>
<head>
  <meta charset="UTF-8">
  <title>Authed Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans">

  <!-- Navigation -->
  <div class="bg-gray-800 flex justify-between items-center px-10 py-4 shadow-md border-b border-gray-700">
    <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="w-10 h-10" alt="logo">
    <div class="flex gap-10 text-sm font-medium">
      <h3>placeholder</h3>
      <h3>placeholder</h3>
      <h3>placeholder</h3>
    </div>
  </div>

  <!-- User Info -->
  <div class="bg-gray-800 rounded-lg mx-10 mt-10 p-6 flex items-center shadow-md">
    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Default_pfp.svg/340px-Default_pfp.svg.png" class="w-32 h-32 rounded-lg mr-6" alt="User Image">
    <div>
      <h4 class="text-2xl font-bold mb-2">Welcome [Username]!</h4>
      <p class="mb-2">Email: UserMain123@gmail.com <i class="fas fa-pen-alt ml-2"></i></p>
      <p class="mb-4">Password: **** <i class="fas fa-pen-alt ml-2"></i></p>
      <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
        Delete Account
      </button>
    </div>
  </div>
</br>
    
    <div class="pl-10 flex space-x-5">
     <button  onclick="showQuestions()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded text-sm rounded-lg">
        Questions
      </button>
      
     <button onclick="showAnswers()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded text-sm rounded-lg">
        Answers
      </button>
    </div>
    
    

    <div class="flex flex-wrap gap-4 justify-between gap-6 mt-6 px-10" id="questions">
    <?php if (!empty($questions)): ?>
      <?php foreach ($questions as $q): ?>
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md w-[300px]">
          <p class="text-sm font-semibold">QUESTION TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($q['title']); ?></p>
          <p class="mt-4 text-sm">VOTES: <?php echo $q['votes'] ?? 0; ?></p>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any questions yet.</p>
    <?php endif; ?>
  </div>
  
  <!--
<div class="flex flex-row gap-4 gap-4 mt-4" id="questions">
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
   
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">QUESTION TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
    
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">QUESTION TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
    
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">QUESTION TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    </div>
    -->
    
        <div class="flex flex-row gap-4 mt-4 hidden" id="answers">
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
    <!-- Left Side -->
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">ANSWER TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
    <!-- Left Side -->
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">ANSWER TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex flex-col lg:flex-row justify-between items-start shadow-md w-[300px]">
    <!-- Left Side -->
    <div class="mb-8 lg:mb-0">
      <p class="mt-4 text-sm">ANSWER TITLE</p>
       <p class="mt-4 text-sm">VOTES:#</p>
    </div>
    </div>
    </div>


<script>
  function showQuestions() {
    document.getElementById("questions").classList.remove("hidden");
    document.getElementById("answers").classList.add("hidden");
  }

  function showAnswers() {
    document.getElementById("questions").classList.add("hidden");
    document.getElementById("answers").classList.remove("hidden");
  }
</script>

</body>
</html>