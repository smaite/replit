<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Get filtering parameters
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = "1=1";
$params = [];

if ($role_filter !== 'all') {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pages = ceil($total / $limit);

// Fetch users
$stmt = $conn->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Manage Users - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">
            <i class="fas fa-users text-primary"></i> User Management
        </h1>
        <p class="text-gray-600 mt-2">Manage all customers and vendors on the platform</p>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                <form method="GET" class="relative">
                    <input type="text" name="search" placeholder="Name or email..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Role</label>
                <select onchange="location.href='?role=' + this.value + '<?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customers</option>
                    <option value="vendor" <?php echo $role_filter === 'vendor' ? 'selected' : ''; ?>>Vendors</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Results</label>
                <p class="px-4 py-2 text-gray-700 font-medium"><?php echo $total; ?> users found</p>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if (empty($users)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Users Found</h3>
                <p class="text-gray-600">Try adjusting your search or filter</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Name</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Email</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Phone</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Role</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Joined</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center font-bold">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                        </div>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-block px-3 py-1 text-xs rounded-full <?php 
                                        echo $user['role'] === 'admin' ? 'bg-red-100 text-red-700' :
                                             ($user['role'] === 'vendor' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700');
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="/admin/user-detail.php?id=<?php echo $user['id']; ?>" class="text-primary hover:text-indigo-700 font-medium text-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&role=<?php echo $role_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&role=<?php echo $role_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&role=<?php echo $role_filter; ?>" 
                           class="px-3 py-2 rounded <?php echo $i === $page ? 'bg-primary text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&role=<?php echo $role_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Next</a>
                        <a href="?page=<?php echo $pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&role=<?php echo $role_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
