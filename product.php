<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$rawId = $_GET['id'] ?? '';
$id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$product = null;
$dbError = null;

if ($id === false) {
    $pageTitle = 'Product';
} else {
    try {
        $stmt = db()->prepare(
            'SELECT id, name, description, price, stock, image_path
             FROM products
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $product = $row;
            $pageTitle = (string) $product['name'];
        } else {
            $pageTitle = 'Product not found';
        }
    } catch (Throwable $e) {
        $dbError = 'Could not load this product. Check the database and config.';
        $pageTitle = 'Error';
    }
}

function product_image_src(?string $imagePath): ?string
{
    if ($imagePath === null || $imagePath === '') {
        return null;
    }
    return $imagePath;
}

function first_char_upper(string $text): string
{
    if (function_exists('mb_substr')) {
        return strtoupper(mb_substr($text, 0, 1, 'UTF-8'));
    }
    return strtoupper(substr($text, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — ENSaj shop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
      <a class="navbar-brand" href="index.php">ENSaj shop</a>
      <span class="navbar-text text-white-50 small">Product</span>
    </div>
  </nav>

  <main class="container pb-5">
    <?php if ($id === false): ?>
      <div class="alert alert-warning" role="alert">
        Invalid product link. Please pick a product from the shop.
      </div>
      <a class="btn btn-primary" href="index.php">Back to products</a>

    <?php elseif ($dbError !== null): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
      <a class="btn btn-outline-primary" href="index.php">Back to products</a>

    <?php elseif ($product === null): ?>
      <div class="alert alert-secondary" role="alert">
        This product does not exist or is no longer available.
      </div>
      <a class="btn btn-primary" href="index.php">Back to products</a>

    <?php else: ?>
      <?php
      $name = (string) $product['name'];
      $desc = $product['description'] !== null ? (string) $product['description'] : '';
      $price = (float) $product['price'];
      $stock = (int) $product['stock'];
      $img = product_image_src($product['image_path'] !== null ? (string) $product['image_path'] : null);
      $initial = first_char_upper($name);
      $inStock = $stock > 0;
      ?>
      <div class="row g-4 align-items-start">
        <div class="col-lg-6">
          <div class="ratio ratio-4x3 bg-secondary bg-opacity-10 rounded-3 overflow-hidden border">
            <?php if ($img !== null): ?>
              <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" class="object-fit-cover w-100 h-100">
            <?php else: ?>
              <div class="d-flex align-items-center justify-content-center h-100 display-1 text-secondary opacity-50 user-select-none" aria-hidden="true">
                <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-6">
          <h1 class="h2 mb-3"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="fs-3 fw-semibold text-primary mb-2"><?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?></p>
          <p class="mb-4">
            <?php if ($inStock): ?>
              <span class="badge text-bg-success">In stock (<?= (int) $stock ?>)</span>
            <?php else: ?>
              <span class="badge text-bg-danger">Out of stock</span>
            <?php endif; ?>
          </p>
          <?php if ($desc !== ''): ?>
            <div class="mb-4">
              <h2 class="h6 text-uppercase text-muted">Description</h2>
              <p class="mb-0 text-body-secondary"><?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
          <?php endif; ?>

          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-lg"<?= $inStock ? '' : ' disabled' ?>>
              Add to cart
            </button>
            <a class="btn btn-outline-secondary btn-lg" href="index.php">Continue shopping</a>
          </div>
          <?php if (!$inStock): ?>
            <p class="form-text mt-2 mb-0">You cannot add out-of-stock items (cart logic will come later).</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
