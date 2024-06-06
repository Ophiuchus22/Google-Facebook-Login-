<?php
// Load the Google API client library (installed via Composer)
require_once 'vendor/autoload.php';

// Create a new Google Client object
$gClient = new Google_Client();

// Set up Google project credentials 
$gClient->setClientId("535111942707-ac8kddin2o20mefs3un3legkhfehdprv.apps.googleusercontent.com");  // Google Client ID from the Google Developer Console
$gClient->setClientSecret("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");   // Your Google Client Secret from the Developer Console

// App name
$gClient->setApplicationName("IPT LAB5");

// Set the URL where Google should redirect the user after authorization
$gClient->setRedirectUri("http://localhost/ipt101(lab5)/google_login.php");

// Specify what information to access from the user's Google account
$gClient->addScope("https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email");

// A special link that starts the Google login process
$login_url = $gClient->createAuthUrl();
?>