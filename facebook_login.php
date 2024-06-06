<?php
session_start();
require_once 'facebook_config.php';
require_once 'db_conn.php';

// If the captured code param exists and is valid
if (isset($_GET['code']) && !empty($_GET['code'])) {
    // Execute cURL request to retrieve the access token
    $params = [
        'client_id' => $facebook_oauth_app_id,
        'client_secret' => $facebook_oauth_app_secret,
        'redirect_uri' => $facebook_oauth_redirect_uri,
        'code' => $_GET['code']
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/oauth/access_token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true);

    if (isset($response['access_token'])) {
        $access_token = $response['access_token'];

        // Get user profile data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . $access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_data = curl_exec($ch);
        curl_close($ch);
        $user_data = json_decode($user_data, true);

        $email = $user_data['email'];
        $name = $user_data['name'];
        $facebook_id = $user_data['id'];
        $profile_picture = $user_data['picture']['data']['url'];

        // Split the name into first and last name
        $name_parts = explode(' ', $name);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        // Check if the user already exists in the database00
        $check_user_query = "SELECT * FROM user WHERE Email = '$email'";
        $check_user_result = mysqli_query($conn, $check_user_query);

        if (mysqli_num_rows($check_user_result) > 0) {
            // User exists, log them in
            $user_row = mysqli_fetch_assoc($check_user_result);
            $_SESSION['user_id'] = $user_row['user_id'];
            $_SESSION['username'] = $user_row['username'];
            $_SESSION['phone'] = $user_row['phone'];

            header("Location: user_profile.php"); // Redirect to the remnider page after login
            exit;
        } else {
            // User doesn't exist, create a new account
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
            $username = generate_random_username();
            $password = 'user123';

            $insert_query = "INSERT INTO user (username, password, First_name, Lastname, email, verified) 
                            VALUES ('$username', '$password', '$first_name', '$last_name',  '$email', 1)";
            if (mysqli_query($conn, $insert_query)) {
                $user_id = mysqli_insert_id($conn);

                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['phone'] = $phone;

                // Create a new profile entry for the user
                $insert_profile_query = "INSERT INTO user_profile (user_id, full_name, email) VALUES ('$user_id', '$first_name $last_name', '$email')";
                mysqli_query($conn, $insert_profile_query); 

                // Insert Default Password into Password History
                $insert_password_history_query = "INSERT INTO password_history (user_id, password, date_updated) VALUES ('$user_id', '$password', NOW())"; 
                if (!mysqli_query($conn, $insert_password_history_query)) {
                    echo "Error: " . mysqli_error($conn); // Display an error if the password history insert fails.
                }

                header("Location: user_profile.php"); 
                exit;
            } else {
                echo "Error: " . mysqli_error($conn);
            }
        }
    } else {
        // Error handling
        echo 'Error: ' . $response['error']['message'];
    }
} else {
    // Define params and redirect to Facebook OAuth page
    $params = [
        'client_id' => $facebook_oauth_app_id,
        'redirect_uri' => $facebook_oauth_redirect_uri,
        'response_type' => 'code',
        'scope' => 'email'
    ];
    header('Location: https://www.facebook.com/' . $facebook_oauth_version . '/dialog/oauth?' . http_build_query($params));
    exit;
}
?>