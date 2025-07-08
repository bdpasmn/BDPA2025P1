<!DOCTYPE html>
<html>
  <?php
  session_start();
  //$userId = $_SESSION['user_id'];
  require_once '../../Api/api.php';
  require_once '../../Api/key.php';
  require_once '../../Api/db.php';
  $api = new qOverflowAPI(API_KEY);
  $username = "test-user";
  
  $question = $api->getUserQuestions($username);
  $answer = $api->getUserAnswers($username);
  $user = $api->getUser($username);
  
  $questions = $question['questions'] ?? [];
  $answers = $answer['answers'] ?? [];
  $loggedInUser = $user['user'] ?? [];
/*
   // deleting user account
  if (isset($_POST['delete_account'])) {
  $deleteUser = $api->deleteUser($username);
  }
  session_destroy();
  header('Location: login.php');
*/
  $users = $api->listUsers();

// Print as raw PHP array
echo '<pre>';
print_r($users);
echo '</pre>';


/*
$username = 'test_user';  // The user you want info for
$userData = $api->getUser($username);

// $userData likely contains a full user info array, e.g.:
print_r($userData);  // For debugging, see full user data

// To display specific info, for example:
echo "Username: " . htmlspecialchars($userData['user']['username'] ?? 'N/A') . "<br>";
echo "Email: " . htmlspecialchars($userData['user']['email'] ?? 'N/A') . "<br>";
// Add other fields as needed
*/

  ?>
<head>
  <meta charset="UTF-8">
  <title>Authed Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white font-sans">

  
  <div class="bg-gray-800 flex justify-between items-center px-10 py-4 shadow-md border-b border-gray-700">
    <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="w-10 h-10" alt="logo">
    <div class="flex gap-10 text-sm font-medium">
      <h3>placeholder</h3>
      <h3>placeholder</h3>
      <h3>placeholder</h3>
    </div>
  </div>


  <div class="bg-gray-800 rounded-lg mx-10 mt-10 p-6 flex items-center shadow-md">
    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Default_pfp.svg/340px-Default_pfp.svg.png" class="w-32 h-32 rounded-lg mr-6" alt="User Image">
    <div>
      <!--
      <h4 class="text-2xl font-bold mb-2">Welcome [Username]!</h4>
      <p class="mb-2">Email: UserMain123@gmail.com <i class="fas fa-pen-alt ml-2"></i></p>
      <p class="mb-4">Password: **** <i class="fas fa-pen-alt ml-2"></i></p>
      -->

      <h4 class="text-2xl font-bold mb-2"> Welcome <?= htmlspecialchars($username ?? '') ?></h4>
       <p>Email: <span id="emailDisplay"><?= htmlspecialchars($loggedInUser['email'] ?? 'not found') ?></span>
        <i class="fas fa-pen-alt ml-2" onclick="openEditModal('email')"></i>
      </p>
        <p>Password:  <span id="passwordDisplay">********</span>
        <i class="fas fa-pen-alt ml-2" onclick="openEditModal('password')"></i>
      </p>
      <!--
      <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
        Delete Account
      </button>
      -->
      
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
    <?php if (!empty($questions)): ?>
      <?php foreach ($questions as $q): ?>
        <a href="/pages/q&a/q&a.php">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md w-[300px]">
          <p class="text-sm font-semibold">QUESTION TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($q['title']); ?></p>
          <p class="mt-4 text-sm">VOTES: <?php echo $q['votes'] ?? 0; ?></p>
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any questions yet.</p>
    <?php endif; ?>
  </div>



  <div class="flex flex-wrap gap-4 justify-between gap-6 mt-6 px-10 hidden" id="answers">
    <?php if (!empty($answers)): ?>
      <?php foreach ($answers as $a): ?>
        <a href="/pages/q&a/q&a.php">
        <div class="bg-gray-800 rounded-lg p-6 flex flex-col shadow-md w-[300px]">
          <p class="text-sm font-semibold">QUESTION TITLE:</p>
          <p class="mt-2 text-sm font-semibold"><?php echo htmlspecialchars($a['title']); ?></p>
          <p class="mt-4 text-sm">VOTES: <?php echo $a['votes'] ?? 0; ?></p>
        </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-gray-400">You haven't posted any answers yet.</p>
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


  const modal = document.getElementById('editModal');
  const modalTitle = document.getElementById('modalTitle');
  const editInput = document.getElementById('editInput');
  const cancelBtn = document.getElementById('cancelBtn');

  function openEditModal(field) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (field === 'email') {
      modalTitle.textContent = 'Edit Email';
      editInput.type = 'email';
      editInput.value = ''; // or populate if you want
    } else if (field === 'password') {
      modalTitle.textContent = 'Edit Password';
      editInput.type = 'password';
      editInput.value = '';
    }

    editInput.focus();
  }

  // Close modal on Cancel button click
  cancelBtn.onclick = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }




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