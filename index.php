<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Products';
$dbError = null;
$products = [];

try {
    $stmt = db()->prepare(
        'SELECT id, name, price, image_path, stock
         FROM products
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = 'Could not load products. Check the database and config.';
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
  <style>
    :root {
      --bg: #f8fafc;
      --card: #fff;
      --text: #0f172a;
      --muted: #64748b;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --border: #e2e8f0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      line-height: 1.5;
      color: var(--text);
      background: var(--bg);
    }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 1.25rem; }
    .wrap { max-width: 72rem; margin: 0 auto; }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }
    h1 { font-size: 1.5rem; margin: 0; }
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 8px;
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
      margin-bottom: 1rem;
    }
    .empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--muted);
      background: var(--card);
      border-radius: 12px;
      border: 1px dashed var(--border);
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
      gap: 1.25rem;
    }
    .card {
      background: var(--card);
      border-radius: 12px;
      border: 1px solid var(--border);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
    }
    .card__media {
      aspect-ratio: 4 / 3;
      background: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .card__media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .card__placeholder {
      font-size: 2rem;
      font-weight: 700;
      color: var(--muted);
      user-select: none;
    }
    .card__body { padding: 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.35rem; }
    .card__title {
      font-size: 1rem;
      font-weight: 600;
      margin: 0;
      line-height: 1.35;
    }
    .card__title a {
      color: inherit;
      text-decoration: none;
    }
    .card__title a:hover { color: var(--accent); }
    .card__price {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--accent);
      margin-top: auto;
    }
    .card__stock { font-size: 0.8rem; color: var(--muted); }
    .btn {
      display: inline-block;
      text-align: center;
      padding: 0.55rem 0.9rem;
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      text-decoration: none;
      border: none;
      cursor: pointer;
      background: var(--accent);
      color: #fff;
      margin-top: 0.75rem;
      width: 100%;
    }
    .btn:hover { background: var(--accent-hover); }
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <h1>Shop</h1>
    </header>

    <?php if ($dbError !== null): ?>
      <p class="alert" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (count($products) === 0): ?>
      <p class="empty">No products yet. Add rows in the <code>products</code> table or re-import <code>database.sql</code>.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($products as $p): ?>
          <?php
          $id = (int) $p['id'];
          $name = (string) $p['name'];
          $price = (float) $p['price'];
          $stock = (int) $p['stock'];
          $img = product_image_src($p['image_path'] !== null ? (string) $p['image_path'] : null);
          $initial = first_char_upper($name);
          $detailUrl = 'product.php?id=' . $id;
          ?>
          <article class="card">
            <div class="card__media">
              <?php if ($img !== null): ?>
                <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" width="400" height="300" loading="lazy">
              <?php else: ?>
                <span class="card__placeholder" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
            <div class="card__body">
              <h2 class="card__title">
                <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
              </h2>
              <p class="card__price"><?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="card__stock"><?= $stock > 0 ? 'In stock' : 'Out of stock' ?></p>
              <a class="btn" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">View product</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
