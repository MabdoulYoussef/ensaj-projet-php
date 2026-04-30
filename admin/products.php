<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$products = [];
$error = null;
$status = (string) ($_GET['status'] ?? '');

try {
    $stmt = db()->prepare(
        'SELECT id, name, price, stock
         FROM products
         ORDER BY id ASC'
    );
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Could not load products.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Products</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-4">
    <h1 class="mb-4">Manage Products</h1>

    <?php if ($status === 'deleted'): ?>
      <div class="alert alert-success">Product deleted successfully.</div>
    <?php elseif ($status === 'not_found'): ?>
      <div class="alert alert-warning">Product not found. Nothing was deleted.</div>
    <?php elseif ($status === 'invalid_id'): ?>
      <div class="alert alert-warning">Invalid product id.</div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (count($products) === 0): ?>
      <div class="alert alert-info">No products found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <?php
              $id = (int) $product['id'];
              $name = (string) $product['name'];
              $price = (float) $product['price'];
              $stock = (int) $product['stock'];
              $deleteUrl = 'delete_product.php?id=' . $id;
              ?>
              <tr>
                <td><?= $id ?></td>
                <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(number_format($price, 0), ENT_QUOTES, 'UTF-8') ?> DH</td>
                <td><?= $stock ?></td>
                <td>
                  <a
                    href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Are you sure you want to delete this product?');"
                  >
                    Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
