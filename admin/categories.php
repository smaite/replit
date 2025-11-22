<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$message = '';

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Invalid security token</div>';
    } elseif (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            if ($stmt->execute([$name])) {
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category added successfully</div>';
            } else {
                $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Error adding category</div>';
            }
        }
    } elseif (isset($_POST['edit_category'])) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        if ($name) {
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            if ($stmt->execute([$name, $category_id])) {
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category updated successfully</div>';
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$category_id])) {
            $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category deleted successfully</div>';
        }
    }
}

// Fetch all categories with product counts
$stmt = $conn->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

$page_title = 'Manage Categories - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">
            <i class="fas fa-list text-primary"></i> Category Management
        </h1>
        <p class="text-gray-600 mt-2">Manage product categories</p>
    </div>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Category Form -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-plus text-primary"></i> Add Category
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="add_category" value="1">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                    <input type="text" name="name" placeholder="e.g., Electronics" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                           minlength="3" maxlength="100">
                </div>
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <?php if (empty($categories)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-folder text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Categories</h3>
                        <p class="text-gray-600">Add your first category to get started</p>
                    </div>
                <?php else: ?>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Category Name</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Products</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($categories as $category): ?>
                                <tr class="hover:bg-gray-50 transition" id="category-<?php echo $category['id']; ?>">
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-block px-3 py-1 text-sm font-medium bg-blue-100 text-blue-700 rounded-full">
                                            <?php echo $category['product_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-3">
                                            <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                    class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($category['product_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this category?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="delete_category" value="1">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-gray-400 cursor-not-allowed text-sm" title="Cannot delete category with products">
                                                    <i class="fas fa-trash"></i> Delete
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-sm w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Edit Category</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="edit_category" value="1">
            <input type="hidden" id="categoryId" name="category_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                <input type="text" id="categoryName" name="name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                       minlength="3" maxlength="100">
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                    Update
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name) {
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>

<?php include '../includes/footer.php'; ?>
