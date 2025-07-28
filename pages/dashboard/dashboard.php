<!DOCTYPE html>
<html>
  <?php
  // DASHBOARD PAGE
  //again!!!!! :)
  session_start();

  require_once '../../Api/api.php';
  require_once '../../Api/key.php';
  require_once '../../db.php';
  $api = new qOverflowAPI(API_KEY);
  

  $pdo = new PDO($dsn, $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   
  if (!isset($_SESSION['username'])) { //if session username is not set, redirect to index.php
    header('Location: /index.php');
    exit();
  } 
  $username = $_SESSION['username']; //set username to the session username




  $AllQuestionInfo= $api->getUserQuestions($username); //getting user questions
  $JustUserQuestions = $AllQuestionInfo['questions'] ?? [];// getting just the user questions

  $AllAnswerInfo = $api->getUserAnswers($username);// getting user answers
  $JustUserAnswer = $AllAnswerInfo['answers'] ?? [];// getting just the user answers

  $GettingUser = $api->getUser($username);// getting user info
  $loggedInUser = $GettingUser['user'] ?? [];// getting logged in user info

   // deleting user account
  if (isset($_POST['delete_account'])) {

  $AllQuestionInfo= $api->getUserQuestions($username);          //this is just the code that deletes their questions
  $JustUserQuestions = $AllQuestionInfo['questions'] ?? [];

      foreach ($JustUserQuestions as $UserQuestion) {
       $api->deleteQuestion($UserQuestion['question_id']);
   }                                                           //question deletion ends here

  $deleteUser = $api->deleteUser($username);
  $stmt = $pdo->prepare("DELETE FROM users WHERE username = :username");
  $stmt->execute(['username' => $username]);
  session_destroy();
  header('Location: /index.php');
  exit();
  }
  



$email = $loggedInUser['email'] ?? ' '; //gets email of logged in user, if not found, sets to empty string
$NormalizedEmail = strtolower(trim($email)); // Normalize email by converting to lowercase and trimming whitespace
$HashedEmail = hash('sha256', $NormalizedEmail); // Hash the normalized email using SHA-256
$gravatarUrl = "https://www.gravatar.com/avatar/$HashedEmail?d=identicon"; // Generate Gravatar URL with identicon fallback



//priting all users
/*
$users = $api->listUsers();
echo '<pre>';
print_r($users);
echo '</pre>';



$AllQuestionInfo= $api->getUserQuestions($username);
echo '<pre>';
print_r($AllQuestionInfo);
echo '</pre>';

$JustUserQuestions = $AllQuestionInfo['questions'] ?? [];
echo '<pre>';
print_r($JustUserQuestions);
echo '</pre>';

$Answers= $api->getUserAnswers($username);
echo '<pre>';
print_r($Answers);
echo '</pre>';
*/


$QuestionsPerPage = 9;//sets amount of questions per page to 9

// Get current page from URL, default to 1
$currentQuestionsPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; //sees if page is set in the URL, if not, sets to 1
$currentQuestionsPage = max($currentQuestionsPage, 1); // Ensures at least 1 page

$totalQuestions = count($JustUserQuestions); // Count total of user's questions
$totalQuestionsPages = ceil($totalQuestions / $QuestionsPerPage); //divides total questions by amount allowed per page to get total pages

// Calculate offset and slice the questions array
$startQuestionsIndex = ($currentQuestionsPage - 1) * $QuestionsPerPage; // Calculate the starting index for pagination
$paginatedQuestions = array_slice($JustUserQuestions, $startQuestionsIndex, $QuestionsPerPage); // Slice the questions array to get only the items for the current page


// Pagination for answers
$answersPerPage = 9;

// Get current answer page from URL (can use a different GET param to avoid conflict)
$currentAnswerPage = isset($_GET['answer_page']) ? (int)$_GET['answer_page'] : 1;
$currentAnswerPage = max($currentAnswerPage, 1);

$totalAnswers = count($JustUserAnswer);
$totalAnswerPages = ceil($totalAnswers / $answersPerPage);

$startAnswerIndex = ($currentAnswerPage - 1) * $answersPerPage;
$paginatedAnswers = array_slice($JustUserAnswer, $startAnswerIndex, $answersPerPage);


  ?>


<head>
  <meta charset="UTF-8">
  <title>Authed Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans">




<?php  include '../../../BDPA2025P1/components/navBarLogIn.php';  ?>

 <div id="spinner" class="flex justify-center items-center py-20">
    <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
  </div>

<div id="dashboard" class="hidden">

  <div class="bg-gray-800 rounded-lg mx-10 mt-10 p-6 flex items-center shadow-md flex-wrap border border-gray-700">
  <img src="<?= $gravatarUrl ?>" alt="Profile Picture" class="w-32 h-32 rounded-lg mr-6"/> <!-- genreate gravatar URL with identicon fallback -->
  <div>
      <h4 class="text-3xl font-bold mb-2"> Welcome <?= htmlspecialchars($username ?? '') ?></h4>
       <p>Email: <span id="emailDisplay"><?= htmlspecialchars($loggedInUser['email'] ?? 'not found') ?></span>
        <i class="fas fa-pen-alt ml-2" onclick="openEditModal('email')"></i>
      </p>
        <p>Password:  <span id="passwordDisplay">********</span>
        <i class="fas fa-pen-alt ml-2 pb-4" onclick="openEditModal('password')"></i>
      </p>
        <button onclick="openDeleteModal()" class=" bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
          Delete Account
        </button>
  

    </div>
  </div>
</br>
    
    <div class="pl-10 flex space-x-5">
     <button  onclick="showQuestions()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded text-sm">
        Questions
      </button>
      
     <button onclick="showAnswers()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded text-sm">
        Answers
      </button>
    </div>
    
    


     <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6 px-4 sm:px-6 lg:px-10" id="questions">
    <?php if (!empty($JustUserQuestions)): ?> <!-- Check if there are user questions -->
      <?php /*foreach ($JustUserQuestions as $UserQuestion): */?> <!-- Loop through each user question -->
      <?php foreach ($paginatedQuestions as $UserQuestion): ?> <!-- Loop through each paginated user question for that page-->
        <a href="../q&a/q&a.php?questionName=<?= urlencode($UserQuestion['title']) ?>">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md border border-gray-700 "> <!-- got rid of w-[300px] in the div class -->
        <p class="text-l font-semibold text-blue-400">QUESTION TITLE:</p>
          <p class="mt-2 text-sm font-semibold hover:underline block break-words"><?php echo htmlspecialchars($UserQuestion['title']); ?></p> <!-- Display question title -->
          <p class="mt-4 text-sm">VOTES: <?php echo $UserQuestion['upvotes'] ?? 'not found'; ?></p> <!-- Display question votes -->
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any questions yet.</p> <!-- Display message if no questions found --> 
    <?php endif; ?>
  </div>

  <?php if ($totalQuestionsPages > 1): ?> <!-- Check if there are multiple pages -->
  <div id="questionPagination" class="mt-4 flex justify-center space-x-2 text-sm  mb-4 ">
    <?php for ($i = 1; $i <= $totalQuestionsPages; $i++): ?> <!-- Loop through each page number -->
      <a href="?page=<?= $i ?>" class="px-3 py-1 rounded <?= $i === $currentQuestionsPage ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>"> <!-- Highlight current page -->
        <?= $i ?> <!-- Display page number -->
      </a> 
    <?php endfor; ?>
  </div>
<?php endif; ?>






  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6 px-4 sm:px-6 lg:px-10 hidden" id="answers">
    <?php if (!empty($JustUserAnswer)): ?> <!-- Check if there are user answers -->
      <?php /*foreach ($JustUserAnswer as $UserAnswer): */?> <!-- Loop through each user answer -->
        <?php foreach ($paginatedAnswers as $UserAnswer): ?>
        <a href="/pages/q&a/q&a.php?questionName=<?= urlencode($UserAnswer['question_id']) ?>">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md border border-gray-700"> <!-- got rid of w-[300px] in the div class -->
          <p class="text-l font-semibold text-blue-400">ANSWER TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($UserAnswer['text']); ?></p> <!-- Display question title -->
          <p class="mt-4 text-sm">VOTES: <?php echo $UserAnswer['upvotes'] ?? 'not found'; ?></p><!-- Display answer votes -->
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any answers yet.</p><!-- Display message if no answers found --> 
    <?php endif; ?>
  </div>  

  <?php if ($totalAnswerPages > 1): ?>
  <div id="answerPagination" class="mt-6 flex justify-center space-x-2 text-sm mb-4 hidden">
    <?php for ($i = 1; $i <= $totalAnswerPages; $i++): ?>
      <a href="?answer_page=<?= $i ?>" class="px-3 py-1 rounded <?= $i === $currentAnswerPage ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
<?php endif; ?>


  <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
    <div class="bg-gray-800 p-6 rounded-lg w-96">
      <h2 class="text-xl font-bold mb-4">Confirm Account Deletion</h2>
      <p class="mb-4">Are you sure you want to delete your account? This action cannot be undone.</p>
      <div class="flex justify-end gap-4">
        <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700"> Cancel</button>
          <form method="POST">
            <button type="submit" name="delete_account" class=" bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
              Delete Account
            </button>
          </form>
      </div>
    </div>
  </div>

  <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
  <div class="bg-gray-800 p-6 rounded-lg w-96">
    <h2 class="text-xl font-bold mb-4" id="modalTitle">Edit</h2>
    
    <label for="editInput" class="block mb-2">New value:</label>
    <input id="editInput" type="text" class="w-full p-2 rounded bg-gray-700 text-white border border-gray-600 mb-4" />
    
   

<!--new password code -->
    <!-- Add this below the main password input -->
  <div id="confirmPasswordWrapper" class="hidden">
    <label for="confirmInput" class="block mb-2">Confirm password:</label>
    <input id="confirmInput" type="password" class="w-full p-2 rounded bg-gray-700 text-white border border-gray-600 mb-4" />
  </div>
<!--end of new password code -->
 <div class="flex justify-end gap-4">
      <button id="cancelBtn" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">Cancel</button>
      <button id="saveBtn" class="px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">Save</button>
    </div>
  </div>
</div>

</div>

<script>


  const modal = document.getElementById('editModal'); // Get the modal element
  const modalTitle = document.getElementById('modalTitle'); // Get the modal title element
  const editInput = document.getElementById('editInput');// Get the input field in the modal
  const cancelBtn = document.getElementById('cancelBtn');// Get the Cancel button

  function openEditModal(field) { // Function to open the modal for editing email or password
    currentField = field;  // save which field (email or password)
    modal.classList.remove('hidden');// hide the modal
    modal.classList.add('flex');//show the modal

    document.getElementById('confirmPasswordWrapper').classList.add('hidden');


    if (field === 'email') {//if the field
      modalTitle.textContent = 'Edit Email';//make the title of the modal Edit Email
      editInput.type = 'email'; //set the
      editInput.value = ''; //leave it empty for user to input new email
    } else if (field === 'password') {//if the feild is password
      modalTitle.textContent = 'Edit Password';// set the title to Edit Password
      editInput.type = 'password';//set the input type to password
      editInput.value = '';//leave it empty for user to input new email
      document.getElementById('confirmPasswordWrapper').classList.remove('hidden');
    }
  }


  //saveBtn.onclick = () => { // onlcick of save button
  saveBtn.onclick = function()  { 
  const value = editInput.value;// get the value from the input field

  

  //new password code to confirm password
  if (currentField === 'password') {
    const confirmValue = document.getElementById('confirmInput').value;
    if (value !== confirmValue) {
      alert("Passwords do not match.");
      return;
    }
  }
  //end of password code to confirm password



  const data = new FormData();// Create a new FormData object to send data
  data.append('field', currentField);// Append the current field (email or password) to the FormData
  data.append('value', value);// Append the value to the FormData

  fetch('updateProfile.php', {// Send the data to updateProfile.php
    method: 'POST',// Use POST method
    body: data// Send the FormData object as the request body
  })
  
  /*
  .then(r => r.text())// Convert the response to text
  .then(t => {// Handle the response text
  */
  .then(function (r){// Convert the response to text
      return r.text();//return the response text
  })
  .then(function (t) {// Handle the response text

    alert(t);// Show an alert with the response text
    modal.classList.add('hidden');// hide the modal
    modal.classList.remove('flex');//remove the flex class to hide it
    });
  };
    cancelBtn.onclick = function() { //onlcick of cancel button
    modal.classList.add('hidden');// hide the modal
    modal.classList.remove('flex');//remove the flex class to hide it
  }



  function showQuestions() { //function to show questions
  document.getElementById("questions").classList.remove("hidden"); // show the questions section
  document.getElementById("answers").classList.add("hidden");// hide the answers section

  const questionPagination = document.getElementById("questionPagination");
  const answerPagination = document.getElementById("answerPagination");

  if (questionPagination) questionPagination.classList.remove("hidden");
  if (answerPagination) answerPagination.classList.add("hidden");
  }

  function showAnswers() { //function to show answers
  document.getElementById("questions").classList.add("hidden");//show the questions section
  document.getElementById("answers").classList.remove("hidden");//hide the answers section

  const questionPagination = document.getElementById("questionPagination");
  const answerPagination = document.getElementById("answerPagination");

  if (questionPagination) questionPagination.classList.add("hidden");
  if (answerPagination) answerPagination.classList.remove("hidden");
  }

//deleting account modal 
  function openDeleteModal() {
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
  }
/*
  window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('spinner').classList.add('hidden');
  document.getElementById('dashboard').classList.remove('hidden');
  
});
*/

window.addEventListener('DOMContentLoaded', () => {
  document.getElementById('spinner').classList.add('hidden');
  document.getElementById('dashboard').classList.remove('hidden');

  const params = new URLSearchParams(window.location.search);
  if (params.has('answer_page')) {
    showAnswers();  // show Answers tab if answer_page param is present
  } else {
    showQuestions(); // default to Questions tab
  }
});

</script>

</body>
</html>