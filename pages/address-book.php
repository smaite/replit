<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Get action
$action = $_GET['action'] ?? '';
$address_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $address_type = validateInput($_POST['address_type'] ?? '', 'text', 20);
        $full_name = validateInput($_POST['full_name'] ?? '', 'text', 100);
        $phone = validateInput($_POST['phone'] ?? '', 'text', 20);
        $address_line1 = validateInput($_POST['address_line1'] ?? '', 'text', 255);
        $address_line2 = validateInput($_POST['address_line2'] ?? '', 'text', 255);
        $city = validateInput($_POST['city'] ?? '', 'text', 100);
        $state = validateInput($_POST['state'] ?? '', 'text', 100);
        $postal_code = validateInput($_POST['postal_code'] ?? '', 'text', 20);
        $country = validateInput($_POST['country'] ?? '', 'text', 100);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (!$full_name || !$phone || !$address_line1 || !$city || !$postal_code) {
            $error = 'Please fill in all required fields';
        } else {
            if ($action === 'edit' && $address_id) {
                // Update address
                $stmt = $conn->prepare("
                    UPDATE user_addresses 
                    SET address_type = ?, full_name = ?, phone = ?, address_line1 = ?, 
                        address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, 
                        is_default = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $address_type, $full_name, $phone, $address_line1, $address_line2,
                    $city, $state, $postal_code, $country, $is_default, $address_id, $_SESSION['user_id']
                ]);
                
                $success = 'Address updated successfully!';
            } else {
                // Add new address
                $stmt = $conn->prepare("
                    INSERT INTO user_addresses 
                    (user_id, address_type, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'], $address_type, $full_name, $phone, $address_line1, $address_line2,
                    $city, $state, $postal_code, $country, $is_default
                ]);
                
                $success = 'Address added successfully!';
            }

            // Redirect after 2 seconds
            header('Refresh: 2; url=/pages/address-book.php');
        }
    }
}

// Fetch addresses
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$addresses = $stmt->fetchAll();

// Fetch single address for editing
$edit_address = null;
if ($action === 'edit' && $address_id) {
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$address_id, $_SESSION['user_id']]);
    $edit_address = $stmt->fetch();
    
    if (!$edit_address) {
        $error = 'Address not found';
    }
}

// Handle delete
if ($action === 'delete' && $address_id && verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$address_id, $_SESSION['user_id']]);
    redirect('/pages/address-book.php');
}

$page_title = 'Address Book - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/pages/dashboard.php" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">Address Book</h1>
        <p class="text-gray-600 mt-2">Manage your delivery addresses</p>
    </div>

    <?php if ($action === 'add' || ($action === 'edit' && $edit_address)): ?>
        <!-- Add/Edit Address Form -->
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                <?php echo $action === 'edit' ? 'Edit Address' : 'Add New Address'; ?>
            </h2>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Full Name *</label>
                        <input type="text" name="full_name" required
                               value="<?php echo htmlspecialchars($edit_address['full_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Enter your first and last name">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Phone Number *</label>
                        <input type="tel" name="phone" required
                               value="<?php echo htmlspecialchars($edit_address['phone'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Please enter your phone number">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Region *</label>
                        <select name="state" id="province_select" required onchange="updateCities()"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white">
                            <option value="">Select Region</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">City *</label>
                        <select name="city" id="city_select" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white">
                            <option value="">Select City</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Building / House No / Floor / Street *</label>
                        <input type="text" name="address_line1" required
                               value="<?php echo htmlspecialchars($edit_address['address_line1'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Please enter">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">Area</label>
                        <input type="text" name="address_line2"
                               value="<?php echo htmlspecialchars($edit_address['address_line2'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Please choose your area">
                    </div>
                </div>
                
                <!-- Hidden fields for compatibility -->
                <input type="hidden" name="country" value="Nepal">
                <input type="hidden" name="postal_code" value="00000">

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-600 mb-3">Select a label for effective delivery:</label>
                    <input type="hidden" name="address_type" id="address_type" value="<?php echo htmlspecialchars($edit_address['address_type'] ?? 'home'); ?>">
                    <div class="flex gap-4">
                        <button type="button" id="label_office" onclick="selectLabel('office')"
                                class="flex items-center gap-2 px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition <?php echo ($edit_address && $edit_address['address_type'] === 'office') ? 'ring-2 ring-primary bg-indigo-50' : ''; ?>">
                            <i class="fas fa-briefcase text-gray-500"></i>
                            <span class="font-medium text-gray-700">OFFICE</span>
                        </button>
                        <button type="button" id="label_home" onclick="selectLabel('home')"
                                class="flex items-center gap-2 px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition <?php echo (!$edit_address || $edit_address['address_type'] === 'home') ? 'ring-2 ring-primary bg-indigo-50' : ''; ?>">
                            <i class="fas fa-home text-gray-500"></i>
                            <span class="font-medium text-gray-700">HOME</span>
                        </button>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_default" <?php echo ($edit_address && $edit_address['is_default']) ? 'checked' : ''; ?>
                               class="w-4 h-4 rounded text-primary focus:ring-primary">
                        <span class="text-sm font-medium text-gray-700">Set as default address</span>
                    </label>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-8 py-3 bg-[#0095A0] hover:bg-[#007f8a] text-white font-bold rounded shadow-lg transition">
                        <?php echo $action === 'edit' ? 'UPDATE ADDRESS' : 'SAVE ADDRESS'; ?>
                    </button>
                    <a href="/pages/address-book.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded transition">
                        CANCEL
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- Addresses List -->
        <div class="mb-6">
            <a href="/pages/address-book.php?action=add" class="inline-block bg-primary hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition">
                <i class="fas fa-plus"></i> Add New Address
            </a>
        </div>

        <?php if (empty($addresses)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-map-marker-alt text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">No Addresses Yet</h2>
                <p class="text-gray-600 mb-6">Add your first address to get started</p>
                <a href="/pages/address-book.php?action=add" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                    <i class="fas fa-plus"></i> Add Address
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($addresses as $address): ?>
                    <div class="bg-white rounded-lg shadow p-6 border-2 <?php echo $address['is_default'] ? 'border-primary' : 'border-gray-200'; ?>">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    <i class="fas fa-map-marker-alt text-primary"></i> 
                                    <?php echo ucfirst($address['address_type']); ?>
                                </h3>
                                <?php if ($address['is_default']): ?>
                                    <span class="inline-block mt-1 px-2 py-1 bg-primary text-white text-xs rounded font-medium">
                                        Default Address
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <a href="/pages/address-book.php?action=edit&id=<?php echo $address['id']; ?>" 
                                   class="text-primary hover:text-indigo-700 font-medium">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="/pages/address-book.php?action=delete&id=<?php echo $address['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                   onclick="return confirm('Are you sure you want to delete this address?');"
                                   class="text-red-600 hover:text-red-700 font-medium">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>

                        <div class="space-y-2 text-gray-700">
                            <p class="font-medium"><?php echo htmlspecialchars($address['full_name']); ?></p>
                            <p><?php echo htmlspecialchars($address['address_line1']); ?></p>
                            <?php if ($address['address_line2']): ?>
                                <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                            <?php endif; ?>
                            <p><?php echo htmlspecialchars($address['city']); ?><?php if ($address['state']): ?>, <?php echo htmlspecialchars($address['state']); ?><?php endif; ?> <?php echo htmlspecialchars($address['postal_code']); ?></p>
                            <p><?php echo htmlspecialchars($address['country']); ?></p>
                            <p class="text-sm">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($address['phone']); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Load location data
    const locationData = <?php echo file_get_contents('../location.json'); ?>;
    const editState = "<?php echo $edit_address['state'] ?? ''; ?>";
    const editCity = "<?php echo $edit_address['city'] ?? ''; ?>";

    function updateCities() {
        const provinceSelect = document.getElementById('province_select');
        const citySelect = document.getElementById('city_select');
        const selectedProvince = provinceSelect.value;
        
        // Clear current cities
        citySelect.innerHTML = '<option value="">Select City</option>';
        
        if (selectedProvince && locationData[selectedProvince]) {
            locationData[selectedProvince].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                if (city === editCity) {
                    option.selected = true;
                }
                citySelect.appendChild(option);
            });
        }
    }

    function selectLabel(label) {
        // Visual updates
        document.getElementById('label_home').classList.remove('ring-2', 'ring-primary', 'bg-indigo-50');
        document.getElementById('label_office').classList.remove('ring-2', 'ring-primary', 'bg-indigo-50');
        
        if (label === 'home') {
            document.getElementById('label_home').classList.add('ring-2', 'ring-primary', 'bg-indigo-50');
            document.getElementById('address_type').value = 'home';
        } else {
            document.getElementById('label_office').classList.add('ring-2', 'ring-primary', 'bg-indigo-50');
            document.getElementById('address_type').value = 'office';
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        const provinceSelect = document.getElementById('province_select');
        
        // Populate Provinces
        if (provinceSelect) {
            Object.keys(locationData).forEach(province => {
                const option = document.createElement('option');
                option.value = province;
                option.textContent = province;
                if (province === editState) {
                    option.selected = true;
                }
                provinceSelect.appendChild(option);
            });

            // Trigger city update if editing
            if (editState) {
                updateCities();
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
