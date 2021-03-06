//include this your authorization login home page using something like <?php require_once('auth.php')?>     
<?php

// Begin the PHP session so we have a place to store session data
session_start();

$client_id = '0oac1kwp0LnMy2uyX5d6';
$client_secret = '3j6rpGOXBBWb3NTc1Af5xvuF3k-5PTIfyzq4iY4Z';
$redirect_uri = 'https://yourdomain.com/auth'; //replace this with your redirect uri
$login_url = $redirect_uri;
$oauth_server = '';  // This is the issuer URL of your Okta/oAuth authorization server
$metadata_url = $oauth_server.'/.well-known/oauth-authorization-server';


// Fetch the authorization server metadata which contains a few URLs
// that we need later, such as the authorization and token endpoints 
// these endpoints are hardcoded below, we can dynamically add them with the request.
//$metadata = http($metadata_url);

$token_endpoint = ''; //add in token endpoint
$authorization_endpoint = ''; //add authorization endpoint

// print_r($_GET);

// If they click log out, destroy the session and redirect
if(isset($_GET['logout'])) {
  session_destroy();
  header('Location: '.$metadata->end_session_endpoint.'?'.http_build_query([
    'id_token_hint' => $_SESSION['id_token'],
    'post_logout_redirect_uri' => $redirect_uri,
  ]));
  #header('Location: /');
  die();
}

// If there is a username, they are logged in, and we'll show a simple logged-in view
if(isset($_SESSION['sub'])) {
  echo '<h2>Dashboard</h2>';
  echo '<p>Logged in as</p>';
  echo '<p>' . $_SESSION['name'] . '</p>';
  echo '<p><a href="'.$redirect_uri.'?logout">Log Out</a></p>';
  die();
}

// If something went wrong and we didn't get an authorization code, 
// there is probably an error description in the URL.
if(isset($_GET['error'])) {
  echo '<p>Authorization server returned an error: <b>'.htmlspecialchars($_GET['error']).'</b></p>';
  echo '<p>'.htmlspecialchars($_GET['error_description']).'</p>';
  echo '<p><a href="'.$login_url.'">Start Over</a></p>';
  die();
}

if(!isset($_GET['code'])) {
  // Normal Login
  // Create the link to send the user to log in

  // Generate a random state parameter for CSRF security
  $_SESSION['state'] = bin2hex(random_bytes(10));
  //PKCE secret = code_verifier
  $_SESSION['code_verifier'] = bin2hex(random_bytes(50));
  $code_challenge = base64_urlencode(hash('sha256',$_SESSION['code_verifier'],true));
  
  // Build the authorization URL by starting with the authorization endpoint
  // and adding a few query string parameters identifying this application
  //use the next line for Dynamic url using the metadata url
  //$authorize_url = $metadata->authorization_endpoint.'?'.http_build_query([
    $authorize_url = $authorization_endpoint.'?'.http_build_query([
    'response_type' => 'code',
    'client_id' => $client_id,
    'state' => $_SESSION['state'],
    'code_challenge' => $code_challenge,
    'code_challenge_method' => 'S256',
    'redirect_uri' => $redirect_uri,
    'scope' => 'openid email profile offline_access',
    // 'prompt' => 'login',
    'max_age' => '6000',
  ]);

  echo '<p>Not logged in</p>';
  echo '<p><a href="'.$authorize_url.'">Log In</a></p>';

  // header('Location: '.$authorize_url);

} else {
  // If the AS redirected here with a code, we can try to exchange it for an access token
  // Double check the server returned the correct state parameter
  if($_SESSION['state'] != $_GET['state']) {
    die('Authorization server returned an invalid state parameter');

  }

  // Exchange the authorization code now!
  //Use the next line for dynamic metadata
  //$response = http($metadata->token_endpoint, [
    $response = http($token_endpoint, [
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'redirect_uri' => $redirect_uri,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code_verifier' => $_SESSION['code_verifier'],
  ]);

  // If the response did not include an access token, show an error
  if(!isset($response->access_token)) {
    echo '<p>Error getting an access token:</p>';
    echo '<pre>';
    print_r("response".$response);
    echo '</pre>';
    echo '<p><a href="'.$redirect_uri.'">Start Over</a></p>';
    die();
  }

  echo '<h3>Access Token Response</h3>';
  echo '<pre>'; print_r($response); echo '</pre>';
  
  // Extract the user's email address from the ID token and save it in the session
  if(property_exists($response, 'id_token')) {
    $id_token = $response->id_token;
    $claims_component = explode('.', $id_token)[1];
    $userinfo = json_decode(base64_decode($claims_component));
    $_SESSION['name'] = $userinfo->name;
    $_SESSION['email'] = $userinfo->email;
    $_SESSION['sub'] = $userinfo->sub;
    

    echo '<p>Request confirmed '.htmlspecialchars($_SESSION['sub']).'</p>';
    echo '<p>Your email is '.htmlspecialchars($_SESSION['email']).'</p>';
    echo '<p>Hello '.htmlspecialchars($_SESSION['name']).'</p>';
  }

  echo '<p><a href="'.$redirect_uri.'">Home Page</a></p>';
  die();



}

// This function generates a base64-url-encoded version of 
// the sha256 hash of the input. This is used to generate the
// PKCE challenge from the PKCE code verifier.
function pkce_challenge($plain) {
  return base64_urlencode(hash('sha256', $plain, true));
}

// Base64-urlencoding is a simple variation on base64-encoding
// Instead of +/ we use -_, and the trailing = are removed.
function base64_urlencode($string) {
  return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

// This helper method makes an HTTP request using GET or POST
// depending on whether $post_params is present.
// The response is assumed to be a JSON body and will be decoded.
function http($url, $post_params=false) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  if($post_params)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_params));
  return json_decode(curl_exec($ch));
}

