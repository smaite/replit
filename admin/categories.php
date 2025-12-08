<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$message = '';

// Create uploads directory if not exists
$uploadDir = '../uploads/categories/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Invalid security token</div>';
    } elseif (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $imagePath = null;
        
        // Handle image upload
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (in_array($_FILES['image']['type'], $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'cat_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = $filename;
                }
            }
        }
        
        if ($name) {
            // Generate unique slug from name
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
            $slug = $slug . '-' . time(); // Ensure uniqueness
            
            $stmt = $conn->prepare("INSERT INTO categories (name, slug, parent_id, image, status) VALUES (?, ?, ?, ?, 'active')");
            if ($stmt->execute([$name, $slug, $parentId, $imagePath])) {
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category added successfully</div>';
            } else {
                $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Error adding category</div>';
            }
        }
    } elseif (isset($_POST['edit_category'])) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $imagePath = null;
        
        // Handle image upload for edit
        if (!empty($_FILES['edit_image']['name']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (in_array($_FILES['edit_image']['type'], $allowed) && $_FILES['edit_image']['size'] <= 2 * 1024 * 1024) {
                $ext = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
                $filename = 'cat_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = $filename;
                }
            }
        }
        
        if ($name) {
            if ($imagePath) {
                $stmt = $conn->prepare("UPDATE categories SET name = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $imagePath, $category_id]);
            } else {
                $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $category_id]);
            }
            $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category updated successfully</div>';
        }
    } elseif (isset($_POST['toggle_status'])) {
        $category_id = (int)$_POST['category_id'];
        $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $category_id]);
        $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category status updated</div>';
    } elseif (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$category_id])) {
            $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category deleted successfully</div>';
        }
    }
}

// Fetch parent categories for dropdown
$parentStmt = $conn->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name");
$parentCategories = $parentStmt->fetchAll();

// Fetch all categories with product counts
$stmt = $conn->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name
");
$allCategories = $stmt->fetchAll();

// Organize into parent-child structure for accordion
$parentCats = [];
$childCats = [];
foreach ($allCategories as $cat) {
    if (empty($cat['parent_id'])) {
        $parentCats[$cat['id']] = $cat;
        $childCats[$cat['id']] = []; // initialize children array
    } else {
        $childCats[$cat['parent_id']][] = $cat;
    }
}

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
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="add_category" value="1">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                    <input type="text" name="name" placeholder="e.g., Electronics" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                           minlength="3" maxlength="100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Parent Category (optional)</label>
                    <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <option value="">— None (Top Level) —</option>
                        <?php foreach ($parentCategories as $pc): ?>
                            <option value="<?php echo $pc['id']; ?>"><?php echo htmlspecialchars($pc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select to make this a subcategory</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Image</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">Max 2MB (JPG, PNG, WebP)</p>
                </div>
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <?php if (empty($parentCats)): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-folder text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Categories</h3>
                        <p class="text-gray-600">Add your first category to get started</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($parentCats as $parentId => $parent): ?>
                            <?php $hasChildren = !empty($childCats[$parentId]); ?>
                            
                            <!-- Parent Category Card -->
                            <div class="category-accordion" id="cat-<?php echo $parentId; ?>">
                                <!-- Parent Header - Clickable to expand -->
                                <div class="flex items-center p-4 hover:bg-gray-50 cursor-pointer <?php echo $hasChildren ? 'accordion-toggle' : ''; ?>" 
                                     <?php if ($hasChildren): ?>onclick="toggleAccordion(<?php echo $parentId; ?>)"<?php endif; ?>>
                                    
                                    <!-- Expand/Collapse Icon -->
                                    <?php if ($hasChildren): ?>
                                        <i class="fas fa-chevron-right text-gray-400 mr-3 transition-transform accordion-icon" id="icon-<?php echo $parentId; ?>"></i>
                                    <?php else: ?>
                                        <div class="w-4 mr-3"></div>
                                    <?php endif; ?>
                                    
                                    <!-- Image -->
                                    <?php if (!empty($parent['image'])): ?>
                                        <img src="../uploads/categories/<?php echo htmlspecialchars($parent['image']); ?>" 
                                             alt="" class="w-12 h-12 object-cover rounded-lg mr-4">
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                                            <span class="text-white font-bold text-lg"><?php echo mb_substr($parent['name'], 0, 1); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Name & Count -->
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($parent['name']); ?></p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $parent['product_count']; ?> products
                                            <?php if ($hasChildren): ?>
                                                • <?php echo count($childCats[$parentId]); ?> subcategories
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Status -->
                                    <form method="POST" style="display: inline;" class="mr-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="category_id" value="<?php echo $parent['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo ($parent['status'] ?? 'inactive') === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="px-3 py-1 text-xs font-medium rounded-full <?php echo ($parent['status'] ?? 'inactive') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                            <?php echo ($parent['status'] ?? 'inactive') === 'active' ? '● Active' : '○ Inactive'; ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Actions -->
                                    <div class="flex gap-2">
                                        <button onclick="event.stopPropagation(); editCategory(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['name']); ?>')" 
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($parent['product_count'] == 0 && !$hasChildren): ?>
                                            <form method="POST" style="display: inline;" onsubmit="event.stopPropagation(); return confirm('Delete this category?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="delete_category" value="1">
                                                <input type="hidden" name="category_id" value="<?php echo $parent['id']; ?>">
                                                <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Subcategories Panel - Hidden by default -->
                                <?php if ($hasChildren): ?>
                                    <div class="accordion-content hidden bg-gray-50 border-t" id="children-<?php echo $parentId; ?>">
                                        <?php foreach ($childCats[$parentId] as $child): ?>
                                            <div class="flex items-center p-3 pl-12 hover:bg-gray-100 border-b border-gray-100">
                                                <!-- Indent indicator -->
                                                <span class="text-gray-300 mr-3">└─</span>
                                                
                                                <!-- Image -->
                                                <?php if (!empty($child['image'])): ?>
                                                    <img src="../uploads/categories/<?php echo htmlspecialchars($child['image']); ?>" 
                                                         alt="" class="w-10 h-10 object-cover rounded-lg mr-3">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-lg flex items-center justify-center mr-3">
                                                        <span class="text-white font-bold"><?php echo mb_substr($child['name'], 0, 1); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Name & Count -->
                                                <div class="flex-1">
                                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($child['name']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo $child['product_count']; ?> products</p>
                                                </div>
                                                
                                                <!-- Status -->
                                                <form method="POST" style="display: inline;" class="mr-3">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="toggle_status" value="1">
                                                    <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo ($child['status'] ?? 'inactive') === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="px-2 py-1 text-xs font-medium rounded-full <?php echo ($child['status'] ?? 'inactive') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                                        <?php echo ($child['status'] ?? 'inactive') === 'active' ? '● Active' : '○ Inactive'; ?>
                                                    </button>
                                                </form>
                                                
                                                <!-- Actions -->
                                                <div class="flex gap-1">
                                                    <button onclick="editCategory(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars($child['name']); ?>')" 
                                                            class="p-1 text-blue-600 hover:bg-blue-50 rounded text-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($child['product_count'] == 0): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subcategory?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="delete_category" value="1">
                                                            <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                                            <button type="submit" class="p-1 text-red-600 hover:bg-red-50 rounded text-sm" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Accordion JS -->
                    <script>
                    function toggleAccordion(parentId) {
                        const content = document.getElementById('children-' + parentId);
                        const icon = document.getElementById('icon-' + parentId);
                        
                        if (content.classList.contains('hidden')) {
                            content.classList.remove('hidden');
                            icon.style.transform = 'rotate(90deg)';
                        } else {
                            content.classList.add('hidden');
                            icon.style.transform = 'rotate(0deg)';
                        }
                    }
                    </script>
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
