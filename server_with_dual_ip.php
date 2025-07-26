<?php
$api_key = "ptla_fg7QmQ2OLpNdB1A9nGbGCRY84aEJGpO7xl5gKzD98Wd";
$panel_url = "https://panel.ghotp.xyz";
$count_file = 'server_count.txt';

// STOCK CHECK START
$max = 20;
$current = file_exists($count_file) ? (int)file_get_contents($count_file) : 0;

if ($current >= $max) {
    die("⚠️ All free server slots are currently taken. Please try again later.");
}
// STOCK CHECK END

// Get POST data
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$type = $_POST['type'] ?? ''; // "mta" or "samp"

if (!$first_name || !$last_name || !$username || !$email || !$password || !$type) {
    die("All fields are required.");
}

// Check if user exists
$user_id = get_existing_user_id($panel_url, $api_key, $email, $username);

if (!$user_id) {
    // Create user
    $user_data = [
        "username" => $username,
        "email" => $email,
        "first_name" => $first_name,
        "last_name" => $last_name,
        "password" => $password,
        "language" => "en",
        "root_admin" => false
    ];
    $user_response = api_request("{$panel_url}/api/application/users", $user_data, $api_key);

    if (isset($user_response['errors'])) {
        die("Failed to create user: " . json_encode($user_response['errors']));
    }

    $user_id = $user_response['attributes']['id'] ?? null;
    if (!$user_id) {
        die("Failed to retrieve user ID.");
    }
}

// Check if user already has a server
$existing_servers = api_request("{$panel_url}/api/application/servers?filter[user_id]=$user_id", [], $api_key);
if (!empty($existing_servers['data'])) {
    die("User already has a server. Only one server allowed per email.");
}

// Egg IDs
$egg_ids = [
    "mta" => 37,
    "samp" => 26
];

if (!isset($egg_ids[$type])) {
    die("Invalid server type.");
}

// Set up server configuration
if ($type === 'mta') {
    $docker_image = "ghcr.io/parkervcp/games:mta";
    $startup = "./mta-server64 --port {{SERVER_PORT}} --httpport {{SERVER_WEBPORT}} -n";
    $environment = [
        "SERVER_WEBPORT" => "22005",
        "MAX_PLAYERS" => "50"
    ];
    $memory = 1024;
    $disk = 5120;
    $cpu = 100;
} elseif ($type === 'samp') {
    $docker_image = "ghcr.io/parkervcp/games:samp";
    $startup = "./samp03svr";
    $environment = [
        "RCON_PASS" => "changeme123",
        "Version" => "0.3.7"
    ];
    $memory = 1024;
    $disk = 5120;
    $cpu = 100;
}

// Node and location
$node_id = 2;
$location_id = 2;

// Find free allocation
$allocations = api_request("{$panel_url}/api/application/nodes/$node_id/allocations?per_page=50", [], $api_key);
// Find two free allocations
$allocation_id = null;
$allocation_id2 = null;
if (isset($allocations['data'])) {
    foreach ($allocations['data'] as $alloc) {
        if (empty($alloc['attributes']['assigned_to'])) {
            if (!$allocation_id) {
                $allocation_id = $alloc['attributes']['id'];
            } elseif (!$allocation_id2) {
                $allocation_id2 = $alloc['attributes']['id'];
                break;
            }
        }
    }
}
if (!$allocation_id || !$allocation_id2) {
    die("Not enough free allocations available.");
}
if (isset($allocations['data'])) {
    foreach ($allocations['data'] as $alloc) {
        if (empty($alloc['attributes']['assigned_to'])) {
            $allocation_id = $alloc['attributes']['id'];
            break;
        }
    }
}
if (!$allocation_id) {
    die("No free allocations available.");
}

// Add expiry description (15 days from now)
$expires_at = strtotime("+30 days");
$description = "ExpiresAt:$expires_at databass=https://db.ghotp.xyz";

// Create server
$server_data = [
    "name" => strtoupper($type) . " Free Server for $username",
    "description" => $description,
    "user" => $user_id,
    "egg" => $egg_ids[$type],
    "docker_image" => $docker_image,
    "startup" => $startup,
    "environment" => $environment,
    "limits" => [
        "memory" => $memory,
        "swap" => 0,
        "disk" => $disk,
        "io" => 500,
        "cpu" => $cpu
    ],
    "feature_limits" => [
        "databases" => 1,
        "allocations" => 1,
        "backups" => 0
    ],
    "allocation" => $allocation_id,
"allocations" => [$allocation_id2],
    "deploy" => [
        "locations" => [$location_id],
        "dedicated_ip" => false,
        "port_range" => []
    ],
    "start_on_completion" => true
];

$server_response = api_request("{$panel_url}/api/application/servers", $server_data, $api_key);

if (isset($server_response['errors'])) {
    die("Failed to create server: " . json_encode($server_response['errors']));
}

// ✅ Update stock count (AFTER server is created)
file_put_contents($count_file, $current + 1);

// ✅ Success Message
echo "✅ User and {$type} server created successfully! Redirecting to panel in 5 seconds...";
echo '<script>setTimeout(() => { window.location.href = "https://panel.ghotp.xyz"; }, 5000);</script>';

// ---------------- FUNCTIONS ----------------
function get_existing_user_id($panel_url, $api_key, $email, $username) {
    $email_check = api_request("{$panel_url}/api/application/users?filter[email]=$email", [], $api_key);
    if (!empty($email_check['data'])) {
        return $email_check['data'][0]['attributes']['id'];
    }

    $username_check = api_request("{$panel_url}/api/application/users?filter[username]=$username", [], $api_key);
    if (!empty($username_check['data'])) {
        return $username_check['data'][0]['attributes']['id'];
    }

    return null;
}

function api_request($url, $data, $api_key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        return json_decode($response, true);
    } else {
        return ['errors' => json_decode($response, true)];
    }
}
?>
