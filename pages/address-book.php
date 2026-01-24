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

                // If set as default, unset others
                if ($is_default) {
                    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE id != ? AND user_id = ?");
                    $stmt->execute([$address_id, $_SESSION['user_id']]);
                }

                $success = 'Address updated successfully!';
            } else {
                // If set as default, unset others
                if ($is_default) {
                    $stmt = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                }

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

            // Redirect after 1 second
            header('Refresh: 1; url=/pages/address-book.php');
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

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/pages/dashboard.php" class="hover:text-primary">My Account</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Address Book</span>
        </nav>

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Address Book</h1>
            <?php if ($action !== 'add' && $action !== 'edit'): ?>
                <a href="/pages/address-book.php?action=add" class="inline-flex items-center justify-center px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-sm hover:shadow-md transform hover:-translate-y-0.5">
                    <i class="fas fa-plus mr-2"></i> Add New Address
                </a>
            <?php endif; ?>
        </div>

        <?php if ($action === 'add' || ($action === 'edit' && $edit_address)): ?>
            <!-- Add/Edit Address Form -->
            <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-900">
                        <?php echo $action === 'edit' ? 'Edit Address' : 'Add New Address'; ?>
                    </h2>
                    <a href="/pages/address-book.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                <div class="p-8">
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-center gap-3">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" name="full_name" required
                                           value="<?php echo htmlspecialchars($edit_address['full_name'] ?? ''); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Enter your name">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="tel" name="phone" required
                                           value="<?php echo htmlspecialchars($edit_address['phone'] ?? ''); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Enter your mobile number">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Region / Province <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-map"></i>
                                    </span>
                                    <select name="state" id="province_select" required onchange="updateCities()"
                                            class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary appearance-none cursor-pointer">
                                        <option value="">Select Region</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">City <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-city"></i>
                                    </span>
                                    <select name="city" id="city_select" required
                                            class="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary appearance-none cursor-pointer">
                                        <option value="">Select City</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Address <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </span>
                                    <input type="text" name="address_line1" required
                                           value="<?php echo htmlspecialchars($edit_address['address_line1'] ?? ''); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Street address, House No / Apartment">
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Landmark / Area (Optional)</label>
                                <input type="text" name="address_line2"
                                       value="<?php echo htmlspecialchars($edit_address['address_line2'] ?? ''); ?>"
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                       placeholder="E.g. Near Big Mart">
                            </div>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" name="country" value="Nepal">
                        <input type="hidden" name="postal_code" value="00000">

                        <div class="mb-8">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Address Type</label>
                            <input type="hidden" name="address_type" id="address_type" value="<?php echo htmlspecialchars($edit_address['address_type'] ?? 'home'); ?>">
                            <div class="flex gap-4">
                                <button type="button" id="label_home" onclick="selectLabel('home')"
                                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 border rounded-xl transition-all duration-200 <?php echo (!$edit_address || $edit_address['address_type'] === 'home') ? 'ring-2 ring-primary border-primary bg-indigo-50 text-primary' : 'border-gray-200 hover:bg-gray-50 text-gray-600'; ?>">
                                    <i class="fas fa-home"></i>
                                    <span class="font-bold">Home</span>
                                </button>
                                <button type="button" id="label_office" onclick="selectLabel('office')"
                                        class="flex-1 flex items-center justify-center gap-2 px-6 py-3 border rounded-xl transition-all duration-200 <?php echo ($edit_address && $edit_address['address_type'] === 'office') ? 'ring-2 ring-primary border-primary bg-indigo-50 text-primary' : 'border-gray-200 hover:bg-gray-50 text-gray-600'; ?>">
                                    <i class="fas fa-briefcase"></i>
                                    <span class="font-bold">Office</span>
                                </button>
                            </div>
                        </div>

                        <div class="mb-8 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_default" <?php echo ($edit_address && $edit_address['is_default']) ? 'checked' : ''; ?>
                                       class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary transition cursor-pointer">
                                <div>
                                    <span class="block text-sm font-bold text-gray-900">Set as default address</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Use this address as the default for checkout</span>
                                </div>
                            </label>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" class="flex-1 px-8 py-3.5 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                                <?php echo $action === 'edit' ? 'Update Address' : 'Save Address'; ?>
                            </button>
                            <a href="/pages/address-book.php" class="px-8 py-3.5 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Addresses List -->
            <?php if (empty($addresses)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-16 text-center max-w-2xl mx-auto">
                    <div class="w-24 h-24 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-map-marked-alt text-4xl text-primary"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">No Addresses Found</h2>
                    <p class="text-gray-500 mb-8">You haven't added any shipping addresses yet.</p>
                    <a href="/pages/address-book.php?action=add" class="inline-flex items-center justify-center px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5 shadow-lg shadow-indigo-200">
                        <i class="fas fa-plus mr-2"></i> Add Address
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($addresses as $address): ?>
                        <div class="bg-white rounded-xl shadow-sm p-6 border transition-all duration-300 hover:shadow-md group relative
                            <?php echo $address['is_default'] ? 'border-primary ring-1 ring-primary' : 'border-gray-200'; ?>">

                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-50 text-gray-600">
                                        <i class="fas <?php echo $address['address_type'] === 'home' ? 'fa-home' : 'fa-briefcase'; ?>"></i>
                                    </span>
                                    <div>
                                        <h3 class="font-bold text-gray-900 uppercase text-sm tracking-wide">
                                            <?php echo htmlspecialchars($address['address_type']); ?>
                                        </h3>
                                        <?php if ($address['is_default']): ?>
                                            <span class="text-xs font-bold text-primary">Default Delivery Address</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="/pages/address-book.php?action=edit&id=<?php echo $address['id']; ?>"
                                       class="p-2 text-gray-400 hover:text-primary hover:bg-indigo-50 rounded-lg transition" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/pages/address-book.php?action=delete&id=<?php echo $address['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                       onclick="return confirm('Are you sure you want to delete this address?');"
                                       class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="pl-[52px]">
                                <h4 class="font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($address['full_name']); ?></h4>
                                <p class="text-gray-600 text-sm leading-relaxed mb-3">
                                    <?php echo htmlspecialchars($address['address_line1']); ?><br>
                                    <?php if ($address['address_line2']): ?>
                                        <?php echo htmlspecialchars($address['address_line2']); ?><br>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?>
                                </p>
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <i class="fas fa-phone text-xs"></i>
                                    <?php echo htmlspecialchars($address['phone']); ?>
                                </div>
                            </div>

                            <!-- Mobile Actions (Always Visible) -->
                            <div class="md:hidden mt-4 pt-4 border-t border-gray-100 flex justify-end gap-3">
                                <a href="/pages/address-book.php?action=edit&id=<?php echo $address['id']; ?>"
                                   class="text-sm font-medium text-primary">Edit</a>
                                <a href="/pages/address-book.php?action=delete&id=<?php echo $address['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>"
                                   onclick="return confirm('Are you sure you want to delete this address?');"
                                   class="text-sm font-medium text-red-500">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
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
        // Remove active classes
        const homeBtn = document.getElementById('label_home');
        const officeBtn = document.getElementById('label_office');

        homeBtn.classList.remove('ring-2', 'ring-primary', 'border-primary', 'bg-indigo-50', 'text-primary');
        officeBtn.classList.remove('ring-2', 'ring-primary', 'border-primary', 'bg-indigo-50', 'text-primary');

        // Add inactive classes
        homeBtn.classList.add('border-gray-200', 'text-gray-600');
        officeBtn.classList.add('border-gray-200', 'text-gray-600');

        if (label === 'home') {
            homeBtn.classList.remove('border-gray-200', 'text-gray-600');
            homeBtn.classList.add('ring-2', 'ring-primary', 'border-primary', 'bg-indigo-50', 'text-primary');
            document.getElementById('address_type').value = 'home';
        } else {
            officeBtn.classList.remove('border-gray-200', 'text-gray-600');
            officeBtn.classList.add('ring-2', 'ring-primary', 'border-primary', 'bg-indigo-50', 'text-primary');
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
