<?php
require 'config.php';
require 'vendor/autoload.php';

define('APPLICATION_NAME', 'Gmail API PHP Quickstart');
define('CREDENTIALS_PATH', '~/.credentials/gmail-php-quickstart.json');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('SCOPES', implode(' ', array(
  Google_Service_Gmail::GMAIL_READONLY)
));

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfigFile(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
  $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
  if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents($credentialsPath);
  } else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if(!file_exists(dirname($credentialsPath))) {
      mkdir(dirname($credentialsPath), 0700, true);
    }
    file_put_contents($credentialsPath, $accessToken);
    printf("Credentials saved to %s\n", $credentialsPath);
  }
  $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
  if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
  }
  return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
  $homeDirectory = getenv('HOME');
  if (empty($homeDirectory)) {
    $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
  }
  return str_replace('~', realpath($homeDirectory), $path);
}

/**
 * Get list of Messages in user's mailbox.
 *
 * @param  Google_Service_Gmail $service Authorized Gmail API instance.
 * @param  string $userId User's email address. The special value 'me'
 * can be used to indicate the authenticated user.
 * @return array Array of Messages.
 */
function listMessages($service, $userId) {
  $pageToken = NULL;
  $messages = array();
  $opt_param = array();
  $q = PARAM_QUERY;
  do {
    try {
      if ($pageToken) {
        $opt_param['pageToken'] = $pageToken;
      }
	  if ($q) {
        $opt_param['q'] = $q;
      }
      $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
      if ($messagesResponse->getMessages()) {
        $messages = array_merge($messages, $messagesResponse->getMessages());
        $pageToken = $messagesResponse->getNextPageToken();
      }
    } catch (Exception $e) {
      print 'An error occurred: ' . $e->getMessage();
    }
  } while ($pageToken);

  //foreach ($messages as $message) {
    //print 'Message with ID: ' . $message->getId() . '<br/>';
  //}

  return $messages;
}

/**
 * Get Message with given ID.
 *
 * @param  Google_Service_Gmail $service Authorized Gmail API instance.
 * @param  string $userId User's email address. The special value 'me'
 * can be used to indicate the authenticated user.
 * @param  string $messageId ID of Message to get.
 * @return Google_Service_Gmail_Message Message retrieved.
 */
function getMessage($service, $userId, $messageId) {
  try {
    $message = $service->users_messages->get($userId, $messageId);
    //print 'Message with ID: ' . $message->getId() . ' retrieved.';
    return $message;
  } catch (Exception $e) {
    print 'An error occurred: ' . $e->getMessage();
  }
}

function createSavedFile(){
	file_put_contents(SAVED_FILE, json_encode([]));
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Gmail($client);
$user = 'me';
$results = listMessages($service, $user);
$saved = [];
if(!file_exists(SAVED_FILE)){
	createSavedFile();
}else{
	$saved_contents = json_decode(file_get_contents(SAVED_FILE));
	if(empty($saved_contents)){
		createSavedFile();
	}else{
		$saved = $saved_contents;
	}
}

foreach($results as $obj){
	$msg = getMessage($service, $user, $obj->getId());
	
	//$parts = $msg->getPayload()->getParts();
    //$body = $parts[0]['body'];
	
	$payLoad = $msg->getPayload();
	
	$headers = $payLoad->getHeaders();
	foreach($headers as $header){
		if($header->getName() == 'Subject'){
			$subject = $header->getValue();
			break;
		}
	}
	
	if($subject){
		$name = trim(str_replace([', ', ' ', "/", ":"], ['_', '_', '-', '-'], $subject));
		$file = FILES_PATH . $name . '.txt';
		
		if(!file_exists($file) && !in_array($name, $saved)){
			$body = $payLoad->getBody();
			$rawData = $body->getData();
			$sanitizedData = strtr($rawData,'-_', '+/');
			$decodedMessage = base64_decode($sanitizedData);
			file_put_contents($file, $decodedMessage);
			$saved []= $name;
		}
	}
}

file_put_contents(SAVED_FILE, json_encode($saved));
?>