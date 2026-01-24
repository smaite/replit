<?php
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Location Debug - SASTO Hub';

// Get local IP
$local_ip = getUserIp();

// Get public IP (for localhost testing)
$public_ip = null;
try {
    $public_ip = file_get_contents('https://api.ipify.org');
} catch (Exception $e) {
    $public_ip = 'Failed to fetch';
}

// Get location data from API using public IP
$api_response = null;
$api_city = null;
try {
    $ip_to_check = ($local_ip === '127.0.0.1' || $local_ip === '::1') ? $public_ip : $local_ip;
    $json = file_get_contents("http://ip-api.com/json/{$ip_to_check}?fields=status,message,country,regionName,city,isp,query");
    $api_response = json_decode($json, true);
    if ($api_response && $api_response['status'] === 'success') {
        $api_city = $api_response['city'];
    }
} catch (Exception $e) {
    $api_response = ['error' => $e->getMessage()];
}

// Get current session city
$session_city = $_SESSION['detected_city'] ?? 'Not set';
$geo_source = $_SESSION['geo_source'] ?? 'Not set';

// Get current function result
$function_result = getUserLocation();

include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">🛠️ Location Debug</h1>

        <div class="grid gap-6">
            <!-- IP Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-network-wired text-primary"></i> IP Information
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Local IP (from server)</span>
                        <code class="bg-gray-100 px-3 py-1 rounded text-sm font-mono"><?php echo htmlspecialchars($local_ip); ?></code>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Public IP (from ipify.org)</span>
                        <code class="bg-gray-100 px-3 py-1 rounded text-sm font-mono"><?php echo htmlspecialchars($public_ip); ?></code>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-gray-600">Is Localhost?</span>
                        <span class="<?php echo ($local_ip === '127.0.0.1' || $local_ip === '::1') ? 'text-yellow-600' : 'text-green-600'; ?> font-medium">
                            <?php echo ($local_ip === '127.0.0.1' || $local_ip === '::1') ? 'Yes ⚠️' : 'No ✅'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- API Response -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-globe text-primary"></i> ip-api.com Response
                </h2>
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto font-mono"><?php echo htmlspecialchars(json_encode($api_response, JSON_PRETTY_PRINT)); ?></pre>
            </div>

            <!-- Detection Results -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-primary"></i> Detection Results
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">City from API</span>
                        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($api_city ?? 'N/A'); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-gray-100">
                        <span class="text-gray-600">Session Cache ($_SESSION['detected_city'])</span>
                        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($session_city); ?></span>
                    </div>
                    <div class="flex justify-between py-2 bg-primary/5 -mx-6 px-6 rounded-lg">
                        <span class="text-gray-600">getUserLocation() Result</span>
                        <span class="font-bold text-primary text-lg"><?php echo htmlspecialchars($function_result); ?></span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-tools text-primary"></i> Actions
                </h2>
                <div class="flex gap-4">
                    <a href="?clear_session=1" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition">
                        <i class="fas fa-trash-alt mr-2"></i> Clear Session Cache
                    </a>
                    <a href="debug-location.php" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg font-medium transition">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Handle clear session
if (isset($_GET['clear_session'])) {
    unset($_SESSION['detected_city']);
    header('Location: debug-location.php');
    exit;
}

include '../includes/footer.php';
?>
