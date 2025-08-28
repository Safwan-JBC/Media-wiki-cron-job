<?php
// jobs/mediawiki_api_job.php

return function () {
    $apiEndpoint = "http://bwiki.dirkmeyer.info/api.php";
    $cookieFile = __DIR__ . "/../cookies.txt";
    $logFile = __DIR__ . "/../mediawiki_api.log";

    // Config
    $newUsername = "NEW_USERNAME123";
    $newPassword = "NEW_PASSWORD123";
    $pageTitle = "My_New_Page_Title_Cron_Job";
    $pageContent = "This is the content of my new page by Cron Job!";

    // Helper: log messages
    $logMessage = function ($msg) use ($logFile) {
        $time = date("Y-m-d H:i:s");
        file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
    };

    // Helper: send POST request
    $postRequest = function ($url, $postFields, $cookieFile, $writeCookie = false) use ($logMessage) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        if ($writeCookie) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        }
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $logMessage("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    };

    // 1) CREATE ACCOUNT
    $logMessage("=== Starting Create Account Step ===");
    $response = $postRequest($apiEndpoint, [
        'action' => 'createaccount',
        'name' => $newUsername,
        'password' => $newPassword,
        'format' => 'json'
    ], $cookieFile, true);
    $logMessage("Create Account Step 1 Response: " . json_encode($response));

    if (isset($response['createaccount']['token'])) {
        $token = $response['createaccount']['token'];
        $response = $postRequest($apiEndpoint, [
            'action' => 'createaccount',
            'name' => $newUsername,
            'password' => $newPassword,
            'token' => $token,
            'summary' => 'Creating account via API',
            'format' => 'json'
        ], $cookieFile, true);
        $logMessage("Create Account Step 2 Response: " . json_encode($response));
    } else {
        $logMessage("No createaccount token received, skipping account creation.");
    }

    // 2) LOGIN
    $logMessage("=== Starting Login Step ===");
    $response = $postRequest($apiEndpoint, [
        'action' => 'login',
        'lgname' => $newUsername,
        'lgpassword' => $newPassword,
        'format' => 'json'
    ], $cookieFile, true);
    $logMessage("Login Step 1 Response: " . json_encode($response));

    if (isset($response['login']['token'])) {
        $loginToken = $response['login']['token'];
        $response = $postRequest($apiEndpoint, [
            'action' => 'login',
            'lgname' => $newUsername,
            'lgpassword' => $newPassword,
            'lgtoken' => $loginToken,
            'format' => 'json'
        ], $cookieFile, true);
        $logMessage("Login Step 2 Response: " . json_encode($response));
    } else {
        $logMessage("No login token received, skipping login.");
    }

    // 3) GET EDIT TOKEN
    $logMessage("=== Getting Edit Token ===");
    $ch = curl_init($apiEndpoint . "?action=query&prop=info&intoken=edit&titles=" . urlencode($pageTitle) . "&format=json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response, true);
    $logMessage("Edit Token Response: " . json_encode($responseData));

    $pages = $responseData['query']['pages'] ?? [];
    $editToken = null;
    foreach ($pages as $page) {
        if (isset($page['edittoken'])) {
            $editToken = $page['edittoken'];
            break;
        }
    }
    if (!$editToken) {
        $logMessage("No edit token found, exiting.");
        return;
    }

    // 4) CREATE PAGE
    $logMessage("=== Creating Page ===");

    // Remove any trailing backslash from the token
    $cleanEditToken = substr($editToken, 0, -1);
    $finalToken = $cleanEditToken . '\\';

    $logMessage("finalToken Edit Token: " . $finalToken);

    $response = $postRequest($apiEndpoint, [
        'action' => 'edit',
        'title' => $pageTitle,
        'text' => $pageContent,
        'token' =>  $finalToken,
        'createonly' => 'true',
        'summary' => 'Creating a new page via API test now123',
        'format' => 'json'
    ], $cookieFile, true);

    $logMessage("Create Page Response: " . json_encode($response));

    $logMessage("=== MediaWiki Job Finished ===");
};
