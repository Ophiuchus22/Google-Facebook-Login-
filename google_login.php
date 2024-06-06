<?php
// Start a PHP session to manage user data.
session_start();

// Include Configuration Files
// ---------------------------
require_once 'google_config.php'; // Load configuration specific to Google authentication (client ID, secret, etc.).
require_once 'db_conn.php';       // Load database connection.

// Handle Google OAuth Callback
// -----------------------------
// Check if the user is being redirected back from Google after authorization.
if (isset($_GET['code'])) {

    // Fetch Access Token from Google
    // ------------------------------
    $token = $gClient->fetchAccessTokenWithAuthCode($_GET['code']); // Exchange authorization code for access token.
    $gClient->setAccessToken($token['access_token']);               // Set the access token for the Google client.
    $_SESSION['access_token'] = $token['access_token'];             // Store access token in session for later use.
    
    // Retrieve User Information from Google
    // --------------------------------------
    $google_oauth = new Google_Service_Oauth2($gClient);          // Create OAuth2 service object.
    $google_account_info = $google_oauth->userinfo_v2_me->get();  // Get user information from Google.
    $email = $google_account_info->email;                         // Get the user's email address from Google.
    $first_name = $google_account_info->givenName;                // Get the user's first name from Google.
    $last_name = $google_account_info->familyName;                // Get the user's last name from Google.
    $google_id = $google_account_info->id;                        // Get the user's unique Google ID.
    $profile_picture = $google_account_info->picture;             // Get the URL of the user's Google profile picture.

    // Check if User Exists
    // --------------------
    $check_user_query = "SELECT * FROM user WHERE Email = '$email'"; // Query to check if the email exists in the database.
    $check_user_result = mysqli_query($conn, $check_user_query);     // Execute the query.

    // Handle Existing User or New User
    // --------------------------------
    if (mysqli_num_rows($check_user_result) > 0) {
        
        // Existing User: Log In and Update Name
        // ------------------------------------
        $user_row = mysqli_fetch_assoc($check_user_result); // Fetch the user's data from the database. 
        $_SESSION['user_id'] = $user_row['user_id'];        // Store user ID in session.
        $_SESSION['username'] = $user_row['username'];      // Store username in session.

        // Update Name If Changed
        // ----------------------
        // Update the user's name in the database if it has changed since their last Google login.
        if ($user_row['Lastname'] != $last_name || $user_row['First_name'] != $first_name) {
            $update_name_query = "UPDATE user SET Lastname = '$last_name', First_name = '$first_name' WHERE user_id = ?"; // Prepare update query.
            $update_name_stmt = mysqli_prepare($conn, $update_name_query);
            mysqli_stmt_bind_param($update_name_stmt, "i", $_SESSION['user_id']); // Bind user ID.
            mysqli_stmt_execute($update_name_stmt);
        }

    } else {
        // To generate random default username.
        function generate_random_username() {
            $words = ["apple", "brave", "charm", "droid", "eagle", "fable", "gleam", "hatch", "icily", "jolly", "karma", "lucky", "mango", 
                      "neato", "omega", "pearl", "quark", "risky", "smile", "tango", "ultra", "vivid", "whale", "xenon", "young", "zesty",
                      "frost", "grape", "honey", "jumpy", "knock", "lemon", "mocha", "nacho", "pinky", "queen", "robot", "sunny", "tiger", 
                      "unite", "velvet", "wizard", "xylos", "yummy", "zephyr", "azure", "blitz", "coral", "dream", "ember", "flora", "glint", 
                      "hover", "ivory", "jewel", "kebab", "lyric", "mirth", "novel", "olive", "plume", "quilt", "raven", "swift", "twirl", 
                      "urban", "valor", "whirl", "xylos", "yacht", "zebra"];
            $word = $words[array_rand($words)];
            
            // Ensure the word is at most 5 letters
            if (strlen($word) > 5) {
                $word = substr($word, 0, 5);
            }
            // Generate a random number with up to 3 digits
            $number = rand(0, 999);
            // Combine word and number ensuring total length is 8 characters
            $username = $word . $number;
            // Truncate if necessary to ensure total length is 8 characters
            $username = substr($username, 0, 8);
            
            return $username;
        }
        
        // New User: Create Account
        // ------------------------
        $username = generate_random_username(); // Generate a random username.
        $password = 'user123';                  // Default password..
        
        // Insert New User into Database
        // ------------------------------
        $insert_user_query = "INSERT INTO user (username, password, Lastname, First_name, Email, verified) 
                              VALUES ('$username', '$password', '$last_name', '$first_name', '$email', 1)"; // Insert user data.
        if (mysqli_query($conn, $insert_user_query)) {
            $user_id = mysqli_insert_id($conn); // Get the ID of the newly inserted user.
    
            // Create a new profile entry for the user
            $insert_profile_query = "INSERT INTO user_profile (user_id, full_name, email) VALUES ('$user_id', '$first_name $last_name', '$email')";
            mysqli_query($conn, $insert_profile_query); 
    
            // Insert Default Password into Password History
            $insert_password_history_query = "INSERT INTO password_history (user_id, password, date_updated) VALUES ('$user_id', '$password', NOW())"; 
            if (!mysqli_query($conn, $insert_password_history_query)) {
                echo "Error: " . mysqli_error($conn); // Display an error if the password history insert fails.
            }
    
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            
        } else {
            echo "Error: " . mysqli_error($conn); // Display error if user creation fails.
        }
    }
        // Update Active Status
        // --------------------
        // Set the user's status to 'Online' after login or registration
        $update_sql = "UPDATE user SET Active = 'Online' WHERE user_id = ?"; // Update the 'Active' status to 'Online' for the logged-in user.
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $_SESSION['user_id']); 
        mysqli_stmt_execute($update_stmt);

        // Redirect to User Profile
        // ------------------------
        header("Location: user_profile.php");
        exit;
}
?>