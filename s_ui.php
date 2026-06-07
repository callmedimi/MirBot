<?php
require_once 'config.php';
ini_set('error_log', 'error_log');

/**
 * Perform a CSRF-token handshake and login to establish a session cookie.
 * 
 * @param array $marzban_list_get
 * @param string $cookie_file
 * @return string|false CSRF token if successful, false otherwise.
 */
function loginS_ui($marzban_list_get, $cookie_file)
{
    $url = rtrim($marzban_list_get['url_panel'], '/');
    
    // 1. GET /csrf-token to get CSRF token and set session cookie
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . '/csrf-token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    
    if (!$response) {
        return false;
    }
    
    $res = json_decode($response, true);
    if (!isset($res['success']) || !$res['success'] || !isset($res['obj'])) {
        return false;
    }
    
    $csrf_token = $res['obj'];
    
    // 2. POST /login with username, password, and X-CSRF-Token header
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . '/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => $marzban_list_get['username_panel'],
            'password' => $marzban_list_get['password_panel']
        )),
        CURLOPT_HTTPHEADER => array(
            'X-CSRF-Token: ' . $csrf_token
        ),
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    
    if (!$response) {
        return false;
    }
    
    $res = json_decode($response, true);
    if (isset($res['success']) && $res['success']) {
        return $csrf_token;
    }
    
    return false;
}

/**
 * Handle authenticated HTTP requests to the 3x-ui v3 panel.
 * Supports both Bearer Token and Cookie-based CSRF session logins.
 * 
 * @param string $namepanel
 * @param string $endpoint
 * @param string $method
 * @param mixed $payload
 * @return array
 */
function request_s_ui($namepanel, $endpoint, $method = 'GET', $payload = null)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (!$marzban_list_get) {
        return array('success' => false, 'msg' => 'Panel not found in database');
    }
    
    $url = rtrim($marzban_list_get['url_panel'], '/') . $endpoint;
    $headers = array();
    $use_cookie = false;
    $csrf_token = '';
    $cookie_file = __DIR__ . '/cookie_s_ui_' . ($marzban_list_get['code_panel'] ?? 'default') . '.txt';
    
    // If username_panel is set, use cookie login; otherwise use Bearer token
    if (!empty($marzban_list_get['username_panel'])) {
        $csrf_token = loginS_ui($marzban_list_get, $cookie_file);
        if ($csrf_token) {
            $use_cookie = true;
            $headers[] = 'X-CSRF-Token: ' . $csrf_token;
        }
    } else {
        $headers[] = 'Authorization: Bearer ' . $marzban_list_get['password_panel'];
    }
    
    $curl = curl_init();
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
    );
    
    if ($payload !== null) {
        if (is_array($payload) || is_object($payload)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
        } else {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
    }
    
    if ($use_cookie) {
        $options[CURLOPT_COOKIEFILE] = $cookie_file;
    }
    
    if (!empty($headers)) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
        $error_msg = curl_error($curl);
        curl_close($curl);
        if ($use_cookie && is_file($cookie_file)) {
            @unlink($cookie_file);
        }
        return array('success' => false, 'msg' => $error_msg);
    }
    
    curl_close($curl);
    
    if ($use_cookie && is_file($cookie_file)) {
        @unlink($cookie_file);
    }
    
    return json_decode($response, true);
}

/**
 * Get client details by email/username.
 * 
 * @param string $username
 * @param string $namepanel
 * @return array
 */
function get_Clients_ui($username, $namepanel)
{
    $response = request_s_ui($namepanel, '/panel/api/clients/list', 'GET');
    if (!isset($response['success']) || !$response['success'] || !isset($response['obj'])) {
        return [];
    }
    
    foreach ($response['obj'] as $client) {
        if ($client['email'] == $username) {
            // Fetch client connection links
            $links_res = request_s_ui($namepanel, '/panel/api/clients/links/' . urlencode($username), 'GET');
            $links = [];
            if (isset($links_res['success']) && $links_res['success'] && is_array($links_res['obj'])) {
                foreach ($links_res['obj'] as $uri) {
                    $links[] = ['uri' => $uri];
                }
            }
            
            return array(
                'id' => $client['id'],
                'name' => $client['email'],
                'email' => $client['email'],
                'enable' => $client['enable'],
                'volume' => $client['totalGB'],
                'expiry' => $client['expiryTime'],
                'up' => $client['traffic']['up'] ?? 0,
                'down' => $client['traffic']['down'] ?? 0,
                'inbounds' => $client['inboundIds'] ?? [],
                'desc' => $client['comment'] ?? '',
                'links' => $links,
                'config' => $client // Preserve raw record
            );
        }
    }
    
    return [];
}

/**
 * Get detailed client data. Maps directly to get_Clients_ui since it contains all information in v3.
 * 
 * @param string $username
 * @param string $namepanel
 * @return array
 */
function GetClientsS_UI($username, $namepanel)
{
    return get_Clients_ui($username, $namepanel);
}

/**
 * Add a new client to the panel.
 * 
 * @param string $namepanel
 * @param string $usernameac
 * @param int $Expire
 * @param int $Total
 * @param mixed $inboundid
 * @param string $note
 * @return array
 */
function addClientS_ui($namepanel, $usernameac, $Expire, $Total, $inboundid, $note)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    if (!$marzban_list_get) {
        return array('success' => false, 'msg' => 'Panel not found');
    }
    
    if ($Expire == 0) {
        $expiryTime = 0;
    } else {
        if (isset($marzban_list_get['on_hold_test']) && $marzban_list_get['on_hold_test'] == "1") {
            $timelast = $Expire - time();
            $expiryTime = -intval(($timelast / 86400) * 86400000);
        } else {
            $expiryTime = intval($Expire * 1000);
        }
    }
    
    if ($usernameac == null) {
        return array('success' => false, 'msg' => 'Username is null');
    }
    
    $password = bin2hex(random_bytes(16));
    $uuid = generateUUID();
    $subId = bin2hex(random_bytes(8));
    
    // Normalize inbound ids to an array of integers
    $inboundIds = [];
    if (is_array($inboundid)) {
        foreach ($inboundid as $id) {
            $inboundIds[] = intval($id);
        }
    } elseif (is_string($inboundid) && strpos($inboundid, ',') !== false) {
        $parts = explode(',', $inboundid);
        foreach ($parts as $part) {
            $inboundIds[] = intval(trim($part));
        }
    } else {
        $inboundIds[] = intval($inboundid);
    }
    
    $payload = array(
        'client' => array(
            'id' => $uuid,
            'email' => $usernameac,
            'limitIp' => 0,
            'totalGB' => intval($Total),
            'expiryTime' => $expiryTime,
            'enable' => true,
            'tgId' => 0,
            'subId' => $subId,
            'comment' => $note,
            'password' => $password,
            'auth' => $uuid,
            'flow' => ''
        ),
        'inboundIds' => $inboundIds
    );
    
    return request_s_ui($namepanel, '/panel/api/clients/add', 'POST', $payload);
}

/**
 * Update client attributes.
 * 
 * @param string $namepanel
 * @param array $config
 * @return array
 */
function updateClientS_ui($namepanel, array $config)
{
    $data = is_string($config['data']) ? json_decode($config['data'], true) : $config['data'];
    if (!$data) {
        return array('success' => false, 'msg' => 'Invalid data payload');
    }
    
    $email = $data['name'] ?? ($data['email'] ?? '');
    if (empty($email)) {
        return array('success' => false, 'msg' => 'Client email not found in payload');
    }
    
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $namepanel, "select");
    
    $expiryTime = 0;
    if (isset($data['expiry'])) {
        $expire = $data['expiry'];
        if ($expire == 0) {
            $expiryTime = 0;
        } else {
            if (isset($marzban_list_get['on_hold_test']) && $marzban_list_get['on_hold_test'] == "1") {
                if ($expire < 0) {
                    $expiryTime = $expire;
                } else {
                    $timelast = $expire - time();
                    $expiryTime = -intval(($timelast / 86400) * 86400000);
                }
            } else {
                $expiryTime = intval($expire * 1000);
            }
        }
    }
    
    $payload = array(
        'email' => $email,
        'enable' => isset($data['enable']) ? (bool)$data['enable'] : true,
        'limitIp' => isset($data['limitIp']) ? intval($data['limitIp']) : 0,
        'totalGB' => isset($data['volume']) ? intval($data['volume']) : 0,
        'expiryTime' => $expiryTime,
        'comment' => $data['desc'] ?? '',
    );
    
    return request_s_ui($namepanel, '/panel/api/clients/update/' . urlencode($email), 'POST', $payload);
}

/**
 * Reset client traffic usage.
 * 
 * @param string $usernamepanel
 * @param string $namepanel
 * @return array
 */
function ResetUserDataUsages_ui($usernamepanel, $namepanel)
{
    return request_s_ui($namepanel, '/panel/api/clients/resetTraffic/' . urlencode($usernamepanel), 'POST');
}

/**
 * Delete a client.
 * 
 * @param string $location
 * @param string $username
 * @return array
 */
function removeClientS_ui($location, $username)
{
    return request_s_ui($location, '/panel/api/clients/del/' . urlencode($username), 'POST');
}

/**
 * Check if a client is online.
 * 
 * @param string $name_panel
 * @param string $username
 * @return string 'online' or 'offline'
 */
function get_onlineclients_ui($name_panel, $username)
{
    $response = request_s_ui($name_panel, '/panel/api/clients/onlines', 'POST');
    if (!isset($response['success']) || !$response['success']) {
        return "offline";
    }
    
    $online_users = $response['obj'] ?? [];
    if (!is_array($online_users)) {
        return "offline";
    }
    
    if (in_array($username, $online_users)) {
        return "online";
    }
    
    return "offline";
}

/**
 * Get panel settings including subscription paths and ports.
 * 
 * @param string $name_panel
 * @return array
 */
function get_settig($name_panel)
{
    $response = request_s_ui($name_panel, '/panel/setting/all', 'POST');
    if (!isset($response['success']) || !$response['success'] || !isset($response['obj'])) {
        return [
            'subPort' => '2096',
            'subPath' => '/sub/'
        ];
    }
    
    $settings = $response['obj'];
    if (!isset($settings['subPort']) || empty($settings['subPort'])) {
        $settings['subPort'] = '2096';
    }
    if (!isset($settings['subPath']) || empty($settings['subPath'])) {
        $settings['subPath'] = '/sub/';
    }
    
    return $settings;
}
