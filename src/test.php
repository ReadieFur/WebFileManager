<?php
//Check if domain extension is localhost.
$httpHostParts = explode('.', $_SERVER['HTTP_HOST']);
// if ($httpHostParts[count($httpHostParts) - 1] !== 'localhost') { return http_response_code(403); }
echo '<style>body{background-color:black;color:white;}</style>'; //Optional CSS (don't include if testing api responses).

// require_once __DIR__ . '/_assets/libs/vendor/autoload.php';
// require_once __DIR__ . '/_assets/configuration/config.php';

// $googleClient = new Google_Client(['client_id' => $_GET['google_id']]);  // Specify the CLIENT_ID of the app that accesses the backend
// $googleClient = new Google_Client(['client_id' => Config::Config()['gapi']['client_id']]);  // Specify the CLIENT_ID of the app that accesses the backend
// $payload = $googleClient->verifyIdToken($_GET['google_user_token']);
// if ($payload)
// {
//     //Remove the last 10 characters from the email address (@gmail.com).
//     print_r(substr($payload['email'], 0, strlen($payload['email']) - 10));
// }
// else 
// {
//     print_r(false);
// }
?>
<script>
    //https://developer.mozilla.org/en-US/docs/Web/API/CredentialsContainer/store
    if ("PasswordCredential" in window)
    {
        let credential = new PasswordCredential({
            id: "example-username",
            name: "John Doe", // In case of a login, the name comes from the server.
            password: "correct horse battery staple"
        });

        navigator.credentials.store(credential).then(() =>
        {
            console.log("Credential stored in the user agent's credential manager.");
        },
        (err) =>
        {
            console.error("Error while storing the credential: ", err);
        });
    }
    else
    {
        console.error("Your browser doesn't support storing credentials.");
    }
</script>