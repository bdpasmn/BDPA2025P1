<!DOCTYPE html>
<html>
<?php
//main auth AGAIN
session_start();  // start the session if not already started

require_once '../../Api/api.php';
require_once '../../Key.php';

$api = new BdpaDriveAPI(API_KEY);

$username = $_SESSION['username'] ?? null;

$totalBytes = 0;
// Get first page of file results (limited to 100)
$response = $api->searchFilesystem($username);
$totalBytes = 0;

if (!empty($response['nodes'])) {
    foreach ($response['nodes'] as $file) {
        $totalBytes += isset($file['size']) ? (int)$file['size'] : 0;
    }
}


$response = $api->searchFilesystem($username);
$loggedInUser = $response['user'] ?? null;


$response = $api->getUserByUsername($username); // $username = 'sir_suds_a_lot'

$email = $response['user']['email'] ?? 'not found';

//this code uses the get user by username function to get the email and stuff, but not the password, which is why it says "not found"

$loggedInUserResponse = $api->getUserByUsername($username);
$loggedInUser = $loggedInUserResponse['user'] ?? null;

//deleting

if (isset($_POST['delete_account'])) {

  $fileData = $api->searchFilesystem($username);
  $nodeIds = [];

  if (!empty($fileData['nodes']) && is_array($fileData['nodes'])) {
      $nodeIds = array_column($fileData['nodes'], 'node_id');
  }

  if (!empty($nodeIds)) {
      $deleteFiles = $api->deleteNodes($username, ...$nodeIds);
      // Optional: log result or check for success
  }

  // 3. Delete user from API
  $deleteUser = $api->deleteUser($username);

  // 4. If API deletion successful, also delete from your database
  if (!empty($deleteUser['success']) && $deleteUser['success'] === true) {
      try {
          // Connect to PostgreSQL
          $dsn = "pgsql:host=db.bttjqsfqjpturavdyjcn.supabase.co;port=5432;dbname=postgres";
          $user = "postgres";
          $pass = "bdp@Smn2025!?";
          $pdo = new PDO($dsn, $user, $pass);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

          // Delete from local DB
          $stmt = $pdo->prepare("DELETE FROM users WHERE username = :username");
          $stmt->execute(['username' => $username]);

          // Destroy session and redirect
          session_destroy();
          header('Location: login.php');
          exit();

      } catch (PDOException $e) {
          $error = "API user deleted, but DB delete failed: " . $e->getMessage();
      }
  } else {
      $error = "Failed to delete account from API.";
  }
  
}
?>


<head>
   <script src="https://cdn.tailwindcss.com"></script>
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <title> Authed Dashboard</title>
</head>

<body class="bg-gray-900 text-white">


<?php include '../../BDPA2025/Practice2025/navBarLogNew.php';?>
    
    <br>
    
    <div class=" bg-gray-800 rounded-lg mx-10 mt-10 p-6 flex items-center shadow-md border border-gray-700 gap-10"> 
      <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2c/Default_pfp.svg/340px-Default_pfp.svg.png" class="mr-10 w-36 h-36"> 
      <div class="flex flex-col">

      <h4 class="text-2xl font-bold mb-2"> Welcome <?= htmlspecialchars($username ?? '') ?></h4>

        <p>Email: <span id="emailDisplay"><?= htmlspecialchars($loggedInUser['email'] ?? 'not found') ?></span>
          <i class="fas fa-pen-alt ml-2" onclick="openEditModal('email')"></i>
        </p>
        <p>Password:  <span id="passwordDisplay">********</span>
          <i class="fas fa-pen-alt ml-2" onclick="openEditModal('password')"></i>
        </p>


      <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
        <button type="submit" name="delete_account" class=" bg-indigo-500 text-white font-bold px-4 py-2 rounded shadow-lg shadow-indigo-500/50 text-white px-4 py-2 rounded text-sm mb-2 mt-2 w-max">
          Delete Account
        </button>
      </form>


    </div>
  </div>
    
    <br>
    
    <div class="bg-gray-800 rounded-lg mx-10 my-10 p-6 flex items-start gap-16 border border-gray-700"> 
  <div class="data-left">
    <p class="mb-4 text-xl">Total storage used: 
      <br>
      <br>
    <?= $totalBytes ?>
    bytes</p>
  </div>

  <!-- Modal -->
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
//this is here grabs the info so it can be used in javascript
const modal = document.getElementById('editModal');
const modalTitle = document.getElementById('modalTitle');
const editInput = document.getElementById('editInput');
const saveBtn = document.getElementById('saveBtn');
const cancelBtn = document.getElementById('cancelBtn');


//grabs what is being edited (either the email or password)
let currentField = null;



//Shows the modal by changing its classes (likely using TailwindCSS — hidden hides it, flex shows it) and Stores which field (email or password) is being edited.
function openEditModal(field) {
  currentField = field;
  modal.classList.remove('hidden');
  modal.classList.add('flex');


  // Set modal title and input value depending on field
  //Updates modal content depending on whether the user is editing their email or password:
  if (field === 'email') {
    modalTitle.textContent = 'Edit Email';
    editInput.type = 'email';
    editInput.value = document.getElementById('emailDisplay').textContent.trim();
  } else if (field === 'password') {
    modalTitle.textContent = 'Edit Password';
    editInput.type = 'password';
    editInput.value = '';
  }
  
  editInput.focus();
}



//Hides the modal when the Cancel button is clicked.
cancelBtn.onclick = () => {
  modal.classList.add('hidden');
  modal.classList.remove('flex');
};


//Gets the new value the user entered. If it's empty, shows an alert and stops.
saveBtn.onclick = () => {
  const newValue = editInput.value.trim();
  if (!newValue) {
    alert('Please enter a value.');
    return;
  }

  // Call your update API here — example fetch: Sends a POST request to updateProfile.php. Sends JSON data: which field is being changed and the new value.
  fetch('updateProfile.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ field: currentField, value: newValue })
  })


  //Parses the response. If data.success is true, then the update worked:
  .then(res => res.json())
  .then(data => {
    if (data.success) {



      // Update display on pageUpdates the visible profile info on the page:Shows the new email. For password, just replaces it with ********.
      if (currentField === 'email') {
        document.getElementById('emailDisplay').textContent = newValue;
      } else if (currentField === 'password') {
        document.getElementById('passwordDisplay').textContent = '********';
      }

      //Hides the modal.
      modal.classList.add('hidden');
      modal.classList.remove('flex');

      //If the server returned an error, shows an alert. If the fetch itself fails (e.g., network error), also shows a generic alert.
    } else {
      alert('Update failed: ' + data.message);
    }
  })
  .catch(() => alert('An error occurred.'));
};
</script>


</body>
</html>