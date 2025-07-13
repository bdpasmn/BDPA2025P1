  <?php
  // UPDATE PROFILE PAGE
//again!!!!!
  session_start();
  //$userId = $_SESSION['user_id'];

  require_once '../../Api/api.php';
  require_once '../../Api/key.php';
  require_once '../../db.php';

  //header('Content-Type: application/json');

  $api = new qOverflowAPI(API_KEY);

  $pdo = new PDO($dsn, $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $username = $_SESSION['username'];

  //$updateUser = $api->updateUser($username);

  if ($_SERVER["REQUEST_METHOD"] === "POST") { // Handle form submission
    //$input = json_decode(file_get_contents('php://input'), true);
    $field = $_POST['field'] ?? null;// Get the field to update
    $value = $_POST['value'] ?? null;// Get the new value

    $allowedFields = ['email', 'password']; // Define allowed fields for update
    if (!in_array($field, $allowedFields) || !$value) {
    exit();
}
    $UpdatesInApi = [];// Initialize the updates array for API
    $UpdatesInSupabase = [];// Initialize the updates array for Supabase

    if ($field === 'email') {//if the field is email
      $UpdatesInApi = $api->updateUser($username, ['email' => $value]);// Update email in API
      $UpdatesInSupabase = $pdo->prepare("UPDATE users SET email = :email WHERE username = :username");// Prepare the SQL statement for Supabase
      $UpdatesInSupabase->execute(['email' => $value, 'username' => $username]);// Execute the SQL statement
      echo "Successfully updated email.";
      exit();
    } elseif ($field === 'password') {//if the field is password
          if (
        strlen($value) < 11 ||
        !preg_match("/[A-Z]/", $value) || // Check for uppercase letter
        !preg_match("/[a-z]/", $value) ||// Check for lowercase letter
        !preg_match("/[0-9]/", $value) ||// Check for number
        !preg_match("/[\W]/", $value)// Check for special character
    ) {
        echo "Password must be at least 11 characters and include uppercase, lowercase, number, and special character.";
        exit();
    }
      $salt = bin2hex(random_bytes(16));// Generate a secure random salt
      $passwordHash = hash_pbkdf2("sha256", $value,  $salt,100000, 128, false);// Hash the password with the salt
      //$UpdatesInSupabase = json_encode(['password' => $passwordHash]);

      $UpdatesInApi = $api->updateUser($username, [ // Prepare the data for API update
            'key' => $passwordHash,// Hash the password
            'salt' => $salt// Include the salt
        ]);

      $UpdatesInSupabase = $pdo->prepare("UPDATE users SET password = :password WHERE username = :username"); // Prepare the SQL statement for Supabase
      $UpdatesInSupabase->execute(['password' => $passwordHash, 'username' => $username]);// Execute the SQL statement
      echo "Successfully updated password."; 
        exit();
    }
  }