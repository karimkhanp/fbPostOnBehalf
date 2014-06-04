<?php
//Application Configurations
$app_id        = "1788292862";
$app_secret    = "c7b7fe02ae32b2b9332622";
$site_url      = "my site url";

try{
	include_once "src/facebook.php";
}catch(Exception $e) {
	error_log($e);
}
// Create our application instance
$facebook = new Facebook(array(
		'appId'  => $app_id,
		'secret' => $app_secret,
	));

// Get User ID
$user = $facebook->getUser();
// We may or may not have this data based
// on whether the user is logged in.
// If we have a $user id here, it means we know
// the user is logged into
// Facebook, but we don’t know if the access token is valid. An access
// token is invalid if the user logged out of Facebook.

if ($user) {
	//==================== Single query method ======================================
	try{
		// Proceed knowing you have a logged in user who's authenticated.
		$user_profile = $facebook->api('/me');
	}catch(FacebookApiException $e) {
		error_log($e);
		$user = NULL;
	}
	//==================== Single query method ends =================================
}


if (!$user) {
	// Get login URL
	$loginUrl = $facebook->getLoginUrl(array(
			'scope'   => 'publish_stream user_groups',
			'redirect_uri' => $site_url,
		));
}

if ($user) {
	// Proceed knowing you have a logged in user who has a valid session.

	//========= Batch requests over the Facebook Graph API using the PHP-SDK ========
	// Save your method calls into an array
	$queries = array(
		array('method' => 'GET', 'relative_url' => '/'.$user),
		array('method' => 'GET', 'relative_url' => '/'.$user.'/groups?limit=5000'),
		array('method' => 'GET', 'relative_url' => '/'.$user.'/likes?limit=5000'),
	);

	// POST your queries to the batch endpoint on the graph.
	try{
		$batchResponse = $facebook->api('?batch='.json_encode($queries), 'POST');
	}catch(Exception $o) {
		error_log($o);
	}

	//Return values are indexed in order of the original array, content is in ['body'] as a JSON
	//string. Decode for use as a PHP array.
	$user_info  = json_decode($batchResponse[0]['body'], TRUE);
	$groups   = json_decode($batchResponse[1]['body'], TRUE);
	$pages   = json_decode($batchResponse[2]['body'], TRUE);
	//========= Batch requests over the Facebook Graph API using the PHP-SDK ends =====

	if (isset($_POST['submit_x']) && isset($_POST['ids'])) {
		$list_ids = array();
		$group_new_list = array();
		if (isset($groups['data'])) {
			foreach ($groups['data'] as $group) {
				$group_new_list[$group['id']] = $group['name'];
			}
		}

		$page_new_list = array();
		if (isset($pages['data'])) {
			foreach ($pages['data'] as $page) {
				$page_new_list[$page['id']] = $page['name'];
			}
		}

		if ($_POST['message'] || $_POST['link'] || $_POST['picture']) {
			$body = array();
			if (isset($_POST['message'])) $body['message'] = $_POST['message'];
			if (isset($_POST['link'])) $body['link'] = $_POST['link'];
			if (isset($_POST['picture'])) $body['picture'] = $_POST['picture'];
			if (isset($_POST['name'])) $body['name'] = $_POST['name'];
			if (isset($_POST['caption'])) $body['caption'] = $_POST['caption'];
			if (isset($_POST['description'])) $body['description'] = $_POST['description'];

			$batchPost=array();

			$i=1;
			$flag=1;

			foreach ($_POST['ids'] as $id) {
				$batchPost[] = array('method' => 'POST', 'relative_url' => "/$id/feed", 'body' => http_build_query($body));
				if ($i++ == 50) {
					try{
						$multiPostResponse = $facebook->api('?batch='.urlencode(json_encode($batchPost)), 'POST');
						if (is_array($multiPostResponse)) {
							foreach ($multiPostResponse as $singleResponse) {
								$temp = json_decode($singleResponse['body'], true);
								if (isset($temp['id'])) {
									$splitId = explode("_", $temp['id']);
									if (!empty($splitId[1])) $list_ids[] = $splitId[0];
								}elseif (isset($temp['error'])) {
									error_log(print_r($temp['error'], true));
								}
							}
						}
					}catch(FacebookApiException $e) {
						error_log($e);
					}

					$flag=0;
					unset($batchPost);
					$i=1;
				}

			}
			if (isset($batchPost) && count($batchPost) > 0 ) {
				try{
					$multiPostResponse = $facebook->api('?batch='.urlencode(json_encode($batchPost)), 'POST');
					if (is_array($multiPostResponse)) {
						foreach ($multiPostResponse as $singleResponse) {
							$temp = json_decode($singleResponse['body'], true);
							if (isset($temp['id'])) {
								$splitId = explode("_", $temp['id']);
								if (!empty($splitId[1])) $list_ids[] = $splitId[0];
							}elseif (isset($temp['error'])) {
								error_log(print_r($temp['error'], true));
							}
						}
					}
				}catch(FacebookApiException $e) {
					error_log($e);
				}
				$flag=0;
			}
		}
		else {
			$flag=2;
		}
	}
}
?>
