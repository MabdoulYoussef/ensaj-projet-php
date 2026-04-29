<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$dbError = null;
$cartCount = array_sum($_SESSION['cart']);

/** Normalize cart: positive int keys and quantities only. */
$clean = [];
foreach ($_SESSION['cart'] as $key => $qty) {
    $pid = (int) $key;
    $q = (int) $qty;
    if ($pid > 0 && $q > 0) {
        $clean[$pid] = $q;
    }
}
$_SESSION['cart'] = $clean;

try {
    if (isset($_GET['remove'])) {
        $rid = filter_var($_GET['remove'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($rid !== false && isset($_SESSION['cart'][$rid])) {
            unset($_SESSION['cart'][$rid]);
        }
        header('Location: cart.php');
        exit;
    }

    if (isset($_GET['inc'])) {
        $pid = filter_var($_GET['inc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($pid !== false && isset($_SESSION['cart'][$pid])) {
            $stmt = db()->prepare(
                'SELECT stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([':id' => $pid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                $stock = (int) $row['stock'];
                if ($_SESSION['cart'][$pid] < $stock) {
                    $_SESSION['cart'][$pid]++;
                }
            }
        }
        header('Location: cart.php');
        exit;
    }

    if (isset($_GET['dec'])) {
        $pid = filter_var($_GET['dec'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($pid !== false && isset($_SESSION['cart'][$pid])) {
            if ($_SESSION['cart'][$pid] > 1) {
                $_SESSION['cart'][$pid]--;
            } else {
                unset($_SESSION['cart'][$pid]);
            }
        }
        header('Location: cart.php');
        exit;
    }
} catch (Throwable $e) {
    $dbError = 'Could not update the cart.';
}

$cartCount = array_sum($_SESSION['cart']);
$lines = [];
$grandTotal = 0.0;

$ids = array_keys($_SESSION['cart']);
if ($ids !== []) {
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, name, price, stock
                FROM products
                WHERE id IN ($placeholders) AND is_active = 1";
        $stmt = db()->prepare($sql);
        $stmt->execute($ids);
        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byId[(int) $row['id']] = $row;
        }

        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (!isset($byId[$pid])) {
                unset($_SESSION['cart'][$pid]);
                continue;
            }
            $p = $byId[$pid];
            $stock = (int) $p['stock'];
            if ($qty > $stock) {
                $_SESSION['cart'][$pid] = $stock;
                $qty = $stock;
            }
            if ($qty < 1) {
                unset($_SESSION['cart'][$pid]);
                continue;
            }
            $price = (float) $p['price'];
            $lineTotal = $price * $qty;
            $grandTotal += $lineTotal;
            $lines[] = [
                'id'         => $pid,
                'name'       => (string) $p['name'],
                'price'      => $price,
                'qty'        => $qty,
                'stock'      => $stock,
                'line_total' => $lineTotal,
            ];
        }
    } catch (Throwable $e) {
        $dbError = 'Could not load cart products. Check the database and config.';
        $lines = [];
        $grandTotal = 0.0;
    }
}

$pageTitle = 'Cart';
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
      --line: rgb(255 255 255 / 12%);
      --card: #12162f;
      --accent: #7c3aed;
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at 10% 0%, rgb(124 58 237 / 35%) 0%, transparent 30%),
        radial-gradient(circle at 90% -10%, rgb(6 182 212 / 25%) 0%, transparent 32%),
        linear-gradient(160deg, #080a17, #0f1228 55%, #090b19);
      min-height: 100vh;
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 1rem; }
    .nav {
      display: flex; justify-content: space-between; align-items: center; gap: 1rem;
      border: 1px solid var(--line); border-radius: 14px;
      background: rgb(255 255 255 / 5%); backdrop-filter: blur(10px);
      padding: 0.85rem 1rem; margin-bottom: 1rem;
    }
    .brand { color: #fff; text-decoration: none; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; }
    .pill { border-radius: 999px; padding: 0.35rem 0.65rem; border: 1px solid var(--line); background: rgb(255 255 255 / 7%); color: #dbeafe; }
    h1 { margin: 0 0 0.9rem; font-size: 1.7rem; }
    .alert { border: 1px solid var(--line); border-radius: 12px; padding: 0.9rem 1rem; margin-bottom: 0.9rem; background: rgb(255 255 255 / 6%); }
    .panel {
      border: 1px solid var(--line);
      border-radius: 16px;
      background: linear-gradient(180deg, rgb(255 255 255 / 7%), rgb(255 255 255 / 3%));
      overflow: hidden;
      box-shadow: 0 18px 34px rgb(0 0 0 / 28%);
      animation: reveal 550ms ease forwards;
      opacity: 0;
      transform: translateY(12px);
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.9rem 0.8rem; border-bottom: 1px solid rgb(255 255 255 / 9%); }
    th { text-align: left; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
    td { color: #e2e8f0; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    tr:hover td { background: rgb(255 255 255 / 2%); }
    .p-link { color: #c4b5fd; text-decoration: none; font-weight: 600; }
    .p-link:hover { color: #ddd6fe; }
    .btn {
      border: 1px solid var(--line);
      border-radius: 9px;
      padding: 0.38rem 0.58rem;
      text-decoration: none;
      color: #eef2ff;
      background: rgb(255 255 255 / 6%);
      font-weight: 700;
      font-size: 0.85rem;
      display: inline-block;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-danger { color: #fecaca; border-color: rgb(248 113 113 / 40%); background: rgb(127 29 29 / 25%); }
    .btn-primary { background: linear-gradient(120deg, #6d28d9, #0891b2); }
    .btn-soft { background: rgb(255 255 255 / 7%); }
    .btn.disabled { pointer-events: none; opacity: 0.5; }
    .footer {
      display: flex; justify-content: space-between; align-items: center; gap: 1rem;
      margin-top: 1rem; flex-wrap: wrap;
    }
    .actions { display: flex; gap: 0.55rem; flex-wrap: wrap; }
    .total { font-size: 1.35rem; font-weight: 800; }
    .total span { color: #a5b4fc; }
    @keyframes reveal { to { opacity: 1; transform: translateY(0); } }
    @media (max-width: 760px) {
      .wrap { padding: 0.8rem; }
      th, td { padding: 0.65rem 0.5rem; font-size: 0.84rem; }
      .total { font-size: 1.12rem; }
    }
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav">
      <a class="brand" href="index.php">ENSAJ Wear</a>
      <span class="pill">Cart (<?= (int) $cartCount ?>)</span>
    </nav>
    <h1>Your cart</h1>

    <?php if ($dbError !== null): ?>
      <div class="alert" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($lines === []): ?>
      <div class="alert">Your cart is empty.</div>
      <a class="btn btn-primary" href="index.php">Browse products</a>
    <?php else: ?>
      <div class="panel">
        <table>
          <thead>
            <tr>
              <th scope="col">Product</th>
              <th scope="col" class="text-end">Price</th>
              <th scope="col" class="text-center">Qty</th>
              <th scope="col" class="text-end">Subtotal</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $line): ?>
              <?php
              $pid = (int) $line['id'];
              $atMax = $line['qty'] >= $line['stock'];
              ?>
              <tr>
                <td>
                  <a class="p-link" href="product.php?id=<?= $pid ?>"><?= htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8') ?></a>
                </td>
                <td class="text-end"><?= htmlspecialchars(number_format($line['price'], 0), ENT_QUOTES, 'UTF-8') ?> DH</td>
                <td class="text-center"><?= (int) $line['qty'] ?></td>
                <td class="text-end fw-semibold"><?= htmlspecialchars(number_format($line['line_total'], 0), ENT_QUOTES, 'UTF-8') ?> DH</td>
                <td class="text-end text-nowrap">
                  <a class="btn" href="cart.php?dec=<?= $pid ?>" title="Decrease">−</a>
                  <?php if ($atMax): ?>
                    <span class="btn disabled" title="Max stock">+</span>
                  <?php else: ?>
                    <a class="btn" href="cart.php?inc=<?= $pid ?>" title="Increase">+</a>
                  <?php endif; ?>
                  <a class="btn btn-danger" href="cart.php?remove=<?= $pid ?>" title="Remove">Remove</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <div class="actions">
          <a class="btn btn-soft" href="index.php">Continue shopping</a>
          <a class="btn btn-primary" href="checkout.php">Checkout</a>
        </div>
        <p class="total">
          Total: <span><?= htmlspecialchars(number_format($grandTotal, 0), ENT_QUOTES, 'UTF-8') ?> DH</span>
        </p>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
