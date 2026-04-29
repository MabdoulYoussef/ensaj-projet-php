<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Products';
$dbError = null;
$products = [];
$cartCount = array_sum($_SESSION['cart']);

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
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — ENSAJ Wear</title>
  <style>
    :root {
      --bg: #090b19;
      --bg-soft: #131735;
      --card: #12162f;
      --text: #eef2ff;
      --muted: #a7b0d4;
      --accent: #7c3aed;
      --accent-2: #06b6d4;
      --good: #4ade80;
      --danger: #f87171;
      --ring: rgb(124 58 237 / 45%);
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
      color: var(--text);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at 15% -5%, rgb(124 58 237 / 40%) 0%, transparent 32%),
        radial-gradient(circle at 85% 0%, rgb(6 182 212 / 25%) 0%, transparent 30%),
        linear-gradient(160deg, #080a17, #0f1228 55%, #090b19);
      min-height: 100vh;
    }
    .wrap { max-width: 1180px; margin: 0 auto; padding: 1rem 1rem 2rem; }
    .nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 0.9rem 1rem;
      border: 1px solid rgb(255 255 255 / 10%);
      border-radius: 14px;
      background: rgb(255 255 255 / 4%);
      backdrop-filter: blur(10px);
      position: sticky;
      top: 0.75rem;
      z-index: 15;
    }
    .brand {
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .brand span {
      background: linear-gradient(95deg, #a78bfa, #22d3ee);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 0.38rem 0.72rem;
      font-size: 0.82rem;
      color: #dbeafe;
      border: 1px solid rgb(255 255 255 / 15%);
      background: rgb(255 255 255 / 8%);
    }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      flex-wrap: wrap;
      justify-content: flex-end;
    }
    .hero {
      margin: 1rem 0 1.3rem;
      border-radius: 22px;
      padding: 2rem 1.5rem;
      position: relative;
      overflow: hidden;
      border: 1px solid rgb(255 255 255 / 10%);
      background:
        linear-gradient(135deg, rgb(124 58 237 / 20%), rgb(6 182 212 / 18%)),
        rgb(255 255 255 / 4%);
      box-shadow: inset 0 1px 0 rgb(255 255 255 / 18%), 0 24px 60px rgb(0 0 0 / 35%);
    }
    .hero::before {
      content: "";
      position: absolute;
      width: 280px;
      height: 280px;
      right: -90px;
      top: -90px;
      border-radius: 50%;
      background: radial-gradient(circle, rgb(124 58 237 / 45%), transparent 65%);
      pointer-events: none;
    }
    .hero h1 {
      margin: 0 0 0.4rem;
      font-size: clamp(1.8rem, 4.5vw, 2.9rem);
      line-height: 1.05;
      letter-spacing: -0.03em;
      max-width: 16ch;
    }
    .hero p {
      margin: 0;
      color: var(--muted);
      max-width: 62ch;
      font-size: 1.04rem;
    }
    .hero__row {
      margin-top: 1rem;
      display: flex;
      gap: 0.6rem;
      flex-wrap: wrap;
    }
    .tag {
      border-radius: 999px;
      padding: 0.42rem 0.72rem;
      font-size: 0.82rem;
      border: 1px solid rgb(255 255 255 / 18%);
      background: rgb(255 255 255 / 6%);
      color: #dbeafe;
    }
    .alert {
      padding: 0.8rem 1rem;
      border-radius: 12px;
      border: 1px solid rgb(248 113 113 / 65%);
      background: rgb(127 29 29 / 35%);
      color: #fecaca;
      margin-bottom: 1rem;
    }
    .empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--muted);
      border-radius: 16px;
      border: 1px dashed rgb(255 255 255 / 25%);
      background: rgb(255 255 255 / 3%);
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 1.1rem;
      perspective: 1200px;
    }
    .card {
      position: relative;
      border-radius: 18px;
      border: 1px solid rgb(255 255 255 / 11%);
      background: linear-gradient(180deg, rgb(255 255 255 / 7%), rgb(255 255 255 / 3%));
      backdrop-filter: blur(10px);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: 325px;
      transform-style: preserve-3d;
      opacity: 0;
      transform: translateY(16px) scale(0.99);
      animation: reveal 620ms cubic-bezier(.2,.78,.22,1) forwards;
      animation-delay: var(--delay, 0ms);
      transition: transform 260ms ease, box-shadow 260ms ease, border-color 260ms ease;
      box-shadow: 0 12px 28px rgb(0 0 0 / 28%);
      will-change: transform;
    }
    .card::after {
      content: "";
      position: absolute;
      inset: -60% -80%;
      background: linear-gradient(110deg, transparent, rgb(255 255 255 / 12%), transparent);
      transform: translateX(-120%) rotate(8deg);
      transition: transform 700ms ease;
      pointer-events: none;
    }
    .card:hover::after { transform: translateX(120%) rotate(8deg); }
    .card:hover {
      border-color: rgb(167 139 250 / 70%);
      box-shadow: 0 22px 46px rgb(0 0 0 / 35%), 0 0 0 1px var(--ring);
    }
    .card__badge {
      position: absolute;
      top: 0.7rem;
      left: 0.7rem;
      z-index: 2;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.28rem 0.55rem;
      background: rgb(124 58 237 / 80%);
      border: 1px solid rgb(196 181 253 / 70%);
      color: #f5f3ff;
    }
    .card__media {
      aspect-ratio: 4 / 3;
      background: linear-gradient(145deg, #1e1b4b, #0f172a);
      display: grid;
      place-items: center;
      overflow: hidden;
    }
    .card__media img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 380ms ease;
    }
    .card:hover .card__media img { transform: scale(1.08); }
    .card__placeholder {
      font-size: 2.3rem;
      font-weight: 800;
      color: #e0e7ff;
      text-shadow: 0 8px 20px rgb(0 0 0 / 35%);
      user-select: none;
    }
    .card__body {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      padding: 0.95rem;
      flex: 1;
    }
    .card__title {
      margin: 0;
      font-size: 1.01rem;
      line-height: 1.35;
      font-weight: 700;
      letter-spacing: -0.01em;
    }
    .card__title a {
      color: #f8fafc;
      text-decoration: none;
      transition: color 200ms ease;
    }
    .card__title a:hover { color: #c4b5fd; }
    .card__price {
      margin: auto 0 0;
      font-size: 1.22rem;
      font-weight: 800;
      color: #a5b4fc;
    }
    .card__stock {
      margin: 0;
      font-size: 0.82rem;
      font-weight: 600;
    }
    .stock-ok { color: var(--good); }
    .stock-off { color: var(--danger); }
    .btn {
      margin-top: 0.25rem;
      display: inline-block;
      width: 100%;
      text-align: center;
      text-decoration: none;
      border-radius: 11px;
      padding: 0.62rem 0.86rem;
      border: 1px solid rgb(255 255 255 / 12%);
      color: #f8fafc;
      background: linear-gradient(120deg, #6d28d9, #0891b2);
      font-size: 0.89rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      transition: transform 180ms ease, filter 180ms ease, box-shadow 180ms ease;
    }
    .btn:hover {
      transform: translateY(-2px);
      filter: brightness(1.05);
      box-shadow: 0 10px 18px rgb(6 182 212 / 30%);
    }
    .btn:active { transform: translateY(0); }
    @keyframes reveal {
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    @media (max-width: 680px) {
      .wrap { padding: 0.8rem 0.8rem 1.6rem; }
      .hero { padding: 1.35rem 1rem; border-radius: 16px; }
      .grid { grid-template-columns: repeat(auto-fill, minmax(172px, 1fr)); gap: 0.8rem; }
      .card { min-height: 295px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .card, .card::after, .card__media img, .btn { transition: none; animation: none; }
      .card { opacity: 1; transform: none; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="nav">
      <div class="brand">ensaj <span>wear</span></div>
      <div class="nav-actions">
        <span class="pill"><?= count($products) ?> Products Live</span>
        <a class="pill" href="cart.php">Cart (<?= (int) $cartCount ?>)</a>
      </div>
    </header>
    <section class="hero">
      <h1>ENSAJ Wear</h1>
      <p>Modern menswear for everyday confidence.</p>
      <div class="hero__row">
        <span class="tag">New collection</span>
        <span class="tag">Premium quality</span>
        <span class="tag">Fast delivery</span>
      </div>
    </section>

    <?php if ($dbError !== null): ?>
      <p class="alert" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (count($products) === 0): ?>
      <p class="empty">No products yet. Add rows in the <code>products</code> table or re-import <code>database.sql</code>.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($products as $i => $p): ?>
          <?php
          $id = (int) $p['id'];
          $name = (string) $p['name'];
          $price = (float) $p['price'];
          $stock = (int) $p['stock'];
          $img = product_image_src($p['image_path'] !== null ? (string) $p['image_path'] : null);
          $initial = first_char_upper($name);
          $detailUrl = 'product.php?id=' . $id;
          ?>
          <article class="card js-tilt" style="--delay: <?= (int) ($i * 65) ?>ms;">
            <span class="card__badge"><?= $stock > 0 ? 'HOT' : 'SOLD OUT' ?></span>
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
              <p class="card__price"><?= htmlspecialchars(number_format($price, 0), ENT_QUOTES, 'UTF-8') ?> DH</p>
              <p class="card__stock <?= $stock > 0 ? 'stock-ok' : 'stock-off' ?>">
                <?= $stock > 0 ? 'In stock' : 'Out of stock' ?>
              </p>
              <a class="btn" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">View product</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <script>
    (function () {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      var cards = document.querySelectorAll('.js-tilt');
      cards.forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
          var r = card.getBoundingClientRect();
          var x = (e.clientX - r.left) / r.width;
          var y = (e.clientY - r.top) / r.height;
          var rotateY = (x - 0.5) * 10;
          var rotateX = (0.5 - y) * 8;
          card.style.transform = 'rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) translateY(-6px)';
        });
        card.addEventListener('mouseleave', function () {
          card.style.transform = '';
        });
      });
    })();
  </script>
</body>
</html>
