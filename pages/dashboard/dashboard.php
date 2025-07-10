<!DOCTYPE html>
<html>
  <?php
  // DASHBOARD PAGE !!!
  session_start();

  require_once '../../Api/api.php';
  require_once '../../Api/key.php';
  require_once '../../db.php';
  $api = new qOverflowAPI(API_KEY);
  //$username = "Hello10";

  $pdo = new PDO($dsn, $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $username = $_SESSION['username'];
  
  $AllQuestionInfo= $api->getUserQuestions($username); //getting user questions
  $JustUserQuestions = $AllQuestionInfo['questions'] ?? [];// getting just the user questions

  $AllAnswerInfo = $api->getUserAnswers($username);// getting user answers
  $JustUserAnswer = $AllAnswerInfo['answers'] ?? [];// getting just the user answers

  $GettingUser = $api->getUser($username);// getting user info
  $loggedInUser = $GettingUser['user'] ?? [];// getting logged in user info

   // deleting user account
  if (isset($_POST['delete_account'])) {

  //$AllQuestionInfo= $api->getUserQuestions($username);          //this is just the code that deletes their questions
  //$JustUserQuestions = $AllQuestionInfo['questions'] ?? [];

      //foreach ($JustUserQuestions as $UserQuestion) {
       // $api->deleteQuestion($UserQuestion['question_id']);
   // }                                                           //question deletion ends here

  $deleteUser = $api->deleteUser($username);
  $stmt = $pdo->prepare("DELETE FROM users WHERE username = :username");
  $stmt->execute(['username' => $username]);
  }
  //session_destroy();
  //header('Location: login.php');



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
*/

/*
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
  ?>




<head>
  <meta charset="UTF-8">
  <title>Authed Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans">




<?php  include '../../../BDPA2025P1/components/navBar.php';  ?>

  <!--
  <div class="bg-gray-800 flex justify-between items-center px-10 py-4 shadow-md border-b border-gray-700">
    <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="w-10 h-10" alt="logo">
    <div class="flex gap-10 text-sm font-medium">
      <h3>placeholder</h3>
      <h3>placeholder</h3>
      <h3>placeholder</h3>
    </div>
  </div>
-->

  <div class="bg-gray-800 rounded-lg mx-10 mt-10 p-6 flex items-center shadow-md">
    <!--    this is just the hardcoded old user profile image
  <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Default_pfp.svg/340px-Default_pfp.svg.png" class="w-32 h-32 rounded-lg mr-6" alt="User Image">
  -->

  <img src="<?= $gravatarUrl ?>" alt="Profile Picture" class="w-32 h-32 rounded-lg mr-6"/> <!-- genreate gravatar URL with identicon fallback -->
  <div>

      <h4 class="text-2xl font-bold mb-2"> Welcome <?= htmlspecialchars($username ?? '') ?></h4>
       <p>Email: <span id="emailDisplay"><?= htmlspecialchars($loggedInUser['email'] ?? 'not found') ?></span>
        <i class="fas fa-pen-alt ml-2" onclick="openEditModal('email')"></i>
      </p>
        <p>Password:  <span id="passwordDisplay">********</span>
        <i class="fas fa-pen-alt ml-2 pb-4" onclick="openEditModal('password')"></i>
      </p>

      
      <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
        <button type="submit" name="delete_account" class=" bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
          Delete Account
        </button>
      </form>

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
    <?php if (!empty($JustUserQuestions)): ?> <!-- Check if there are user questions -->
      <?php foreach ($JustUserQuestions as $UserQuestion): ?> <!-- Loop through each user question -->
        <a href="/pages/q&a/q&a.php">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md w-[300px]">
          <p class="text-sm font-semibold">QUESTION TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($UserQuestion['title']); ?></p> <!-- Display question title -->
          <p class="mt-4 text-sm">VOTES: <?php echo $UserQuestion['upvotes'] ?? 0; ?></p> <!-- Display question votes -->
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any questions yet.</p> <!-- Display message if no questions found --> 
    <?php endif; ?>
  </div>



  <div class="flex flex-wrap gap-4 justify-between gap-6 mt-6 px-10 hidden" id="answers">
    <?php if (!empty($JustUserAnswer)): ?> <!-- Check if there are user answers -->
      <?php foreach ($JustUserAnswer as $UserAnswer): ?> <!-- Loop through each user answer -->
        <a href="/pages/q&a/q&a.php">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md w-[300px]">
          <p class="text-sm font-semibold">ANSWER TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($UserAnswer['text']); ?></p> <!-- Display question title -->
          <p class="mt-4 text-sm">VOTES: <?php echo $UserAnswer['upvotes'] ?? 0; ?></p><!-- Display answer votes -->
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any answers yet.</p><!-- Display message if no answers found --> 
    <?php endif; ?>
  </div>

  <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
  <div class="bg-gray-800 p-6 rounded-lg w-96">
    <h2 class="text-xl font-bold mb-4" id="modalTitle">Edit</h2>
    
    <label for="editInput" class="block mb-2">New value:</label>
    <input id="editInput" type="text" class="w-full p-2 rounded bg-gray-700 text-white border border-gray-600 mb-4" />
    
    <div class="flex justify-end gap-4">
      <button id="cancelBtn" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700">Cancel</button>
      <button id="saveBtn" class="px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">Save</button>
    </div>
  </div>
</div>

<script>


  const modal = document.getElementById('editModal'); // Get the modal element
  const modalTitle = document.getElementById('modalTitle'); // Get the modal title element
  const editInput = document.getElementById('editInput');// Get the input field in the modal
  const cancelBtn = document.getElementById('cancelBtn');// Get the Cancel button
  function openEditModal(field) {
    currentField = field;  // save which field (email or password)
    modal.classList.remove('hidden');// hide the modal
    modal.classList.add('flex');//show the modal

    if (field === 'email') {//if the field
      modalTitle.textContent = 'Edit Email';//make the title of the modal Edit Email
      editInput.type = 'email'; //set the
      editInput.value = ''; //leave it empty for user to input new email
    } else if (field === 'password') {//if the feild is password
      modalTitle.textContent = 'Edit Password';// set the title to Edit Password
      editInput.type = 'password';//set the input type to password
      editInput.value = '';//leave it empty for user to input new email
    }
  }


  saveBtn.onclick = () => { // onlcick of save button
  const value = editInput.value;// get the value from the input field

  const data = new FormData();// Create a new FormData object to send data
  data.append('field', currentField);// Append the current field (email or password) to the FormData
  data.append('value', value);// Append the value to the FormData

  fetch('updateProfile.php', {// Send the data to updateProfile.php
    method: 'POST',// Use POST method
    body: data// Send the FormData object as the request body
  })
  .then(r => r.text())// Convert the response to text
  .then(t => {// Handle the response text
    alert(t);// Show an alert with the response text
    modal.classList.add('hidden');// hide the modal
    modal.classList.remove('flex');//remove the flex class to hide it
    });
  };


  // Close modal on Cancel button click
  cancelBtn.onclick = () => { //onlcick of cancel button
    modal.classList.add('hidden');// hide the modal
    modal.classList.remove('flex');//remove the flex class to hide it
  }




  function showQuestions() { //function to show questions
    document.getElementById("questions").classList.remove("hidden"); // show the questions section
    document.getElementById("answers").classList.add("hidden");// hide the answers section
  }

  function showAnswers() { //function to show answers
    document.getElementById("questions").classList.add("hidden");//show the questions section
    document.getElementById("answers").classList.remove("hidden");//hide the answers section
  }
</script>

</body>
</html>