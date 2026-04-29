<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$cartCount = array_sum($_SESSION['cart']);

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
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — ENSAJ Wear</title>
  <style>
    :root {
      --bg: #090b19;
      --text: #eef2ff;
      --muted: #a7b0d4;
      --card: #12162f;
      --line: rgb(255 255 255 / 12%);
      --accent: #7c3aed;
      --accent-2: #06b6d4;
      --ok: #4ade80;
      --bad: #f87171;
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at 10% 0%, rgb(124 58 237 / 35%) 0%, transparent 30%),
        radial-gradient(circle at 90% -10%, rgb(6 182 212 / 28%) 0%, transparent 32%),
        linear-gradient(160deg, #080a17, #0f1228 55%, #090b19);
      min-height: 100vh;
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 1rem; }
    .nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      border: 1px solid var(--line);
      border-radius: 14px;
      background: rgb(255 255 255 / 5%);
      backdrop-filter: blur(10px);
      padding: 0.85rem 1rem;
      margin-bottom: 1rem;
    }
    .brand {
      color: #fff;
      text-decoration: none;
      font-weight: 700;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    .pill {
      border-radius: 999px;
      padding: 0.35rem 0.65rem;
      border: 1px solid var(--line);
      background: rgb(255 255 255 / 7%);
      color: #dbeafe;
      text-decoration: none;
      font-size: 0.84rem;
    }
    .card {
      border: 1px solid var(--line);
      border-radius: 20px;
      background: linear-gradient(180deg, rgb(255 255 255 / 7%), rgb(255 255 255 / 3%));
      box-shadow: 0 22px 40px rgb(0 0 0 / 30%);
      overflow: hidden;
      animation: reveal 600ms ease forwards;
      opacity: 0;
      transform: translateY(10px);
    }
    .grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 1.2rem; }
    .media {
      aspect-ratio: 4/3;
      display: grid;
      place-items: center;
      background: linear-gradient(145deg, #1e1b4b, #0f172a);
      overflow: hidden;
    }
    .media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 350ms ease;
    }
    .media:hover img { transform: scale(1.06); }
    .placeholder {
      font-size: 3rem;
      font-weight: 800;
      color: #dbeafe;
    }
    .info { padding: 1.15rem; }
    h1 { margin: 0 0 0.75rem; font-size: clamp(1.55rem, 3vw, 2.2rem); line-height: 1.1; }
    .price { font-size: 1.9rem; font-weight: 800; color: #a5b4fc; margin: 0 0 0.65rem; }
    .badge {
      display: inline-block;
      border-radius: 999px;
      padding: 0.34rem 0.72rem;
      font-size: 0.8rem;
      font-weight: 700;
      margin-bottom: 0.9rem;
      border: 1px solid var(--line);
    }
    .badge.ok { color: var(--ok); background: rgb(22 163 74 / 15%); }
    .badge.bad { color: var(--bad); background: rgb(185 28 28 / 15%); }
    .desc-title { margin: 0 0 0.3rem; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.08em; color: var(--muted); }
    .desc { margin: 0 0 1.2rem; color: #d6defc; line-height: 1.6; }
    .actions { display: flex; flex-wrap: wrap; gap: 0.6rem; }
    .btn {
      border: 1px solid var(--line);
      border-radius: 11px;
      padding: 0.66rem 1rem;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.9rem;
      transition: transform 180ms ease, filter 180ms ease, box-shadow 180ms ease;
      cursor: pointer;
    }
    .btn:hover { transform: translateY(-2px); }
    .btn-primary {
      color: #fff;
      background: linear-gradient(120deg, #6d28d9, #0891b2);
      box-shadow: 0 10px 16px rgb(8 145 178 / 28%);
    }
    .btn-primary:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
    .btn-soft { color: #dbeafe; background: rgb(255 255 255 / 6%); }
    .helper { color: var(--muted); font-size: 0.84rem; margin-top: 0.65rem; }
    .alert {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 0.9rem 1rem;
      margin-bottom: 0.9rem;
      background: rgb(255 255 255 / 6%);
      color: #f8fafc;
    }
    @keyframes reveal { to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav">
      <a class="brand" href="index.php">ENSAJ Wear</a>
      <a class="pill" href="cart.php">Cart (<?= (int) $cartCount ?>)</a>
    </nav>
    <?php if ($id === false): ?>
      <div class="alert" role="alert">
        Invalid product link. Please pick a product from the shop.
      </div>
      <a class="btn btn-primary" href="index.php">Back to products</a>

    <?php elseif ($dbError !== null): ?>
      <div class="alert" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
      <a class="btn btn-soft" href="index.php">Back to products</a>

    <?php elseif ($product === null): ?>
      <div class="alert" role="alert">
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
      <div class="grid card">
        <div>
          <div class="media">
            <?php if ($img !== null): ?>
              <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php else: ?>
              <div class="placeholder" aria-hidden="true">
                <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="info">
          <h1><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="price"><?= htmlspecialchars(number_format($price, 0), ENT_QUOTES, 'UTF-8') ?> DH</p>
          <p>
            <?php if ($inStock): ?>
              <span class="badge ok">In stock (<?= (int) $stock ?>)</span>
            <?php else: ?>
              <span class="badge bad">Out of stock</span>
            <?php endif; ?>
          </p>
          <?php if ($desc !== ''): ?>
            <div>
              <h2 class="desc-title">Description</h2>
              <p class="desc"><?= nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
          <?php endif; ?>

          <div class="actions">
            <?php if ($inStock): ?>
              <form action="add_to_cart.php" method="get" class="d-inline">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <button type="submit" class="btn btn-primary">Add to cart</button>
              </form>
            <?php else: ?>
              <button type="button" class="btn btn-primary" disabled>Add to cart</button>
            <?php endif; ?>
            <a class="btn btn-soft" href="index.php">Continue shopping</a>
          </div>
          <?php if (!$inStock): ?>
            <p class="helper">This product is out of stock.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
