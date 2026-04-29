<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$cartCount = array_sum($_SESSION['cart']);
$dbError = null;
$pageTitle = 'Checkout';

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

/**
 * Build cart lines from session + DB (prices and stock from database, not the session alone).
 *
 * @return array{lines: list<array>, grand: float, error: string|null}
 */
function checkout_load_lines(PDO $pdo, array $cart): array
{
    $ids = array_keys($cart);
    if ($ids === []) {
        return ['lines' => [], 'grand' => 0.0, 'error' => null];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, name, price, stock
            FROM products
            WHERE id IN ($placeholders) AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byId[(int) $row['id']] = $row;
    }

    $lines = [];
    $grand = 0.0;
    foreach ($cart as $pid => $qty) {
        if (!isset($byId[$pid])) {
            continue;
        }
        $p = $byId[$pid];
        $stock = (int) $p['stock'];
        if ($qty > $stock) {
            return ['lines' => [], 'grand' => 0.0, 'error' => 'Not enough stock for one or more products. Update your cart and try again.'];
        }
        if ($qty < 1) {
            continue;
        }
        $price = (float) $p['price'];
        $lineTotal = $price * $qty;
        $grand += $lineTotal;
        $lines[] = [
            'id'         => $pid,
            'name'       => (string) $p['name'],
            'price'      => $price,
            'qty'        => $qty,
            'stock'      => $stock,
            'line_total' => $lineTotal,
        ];
    }

    if ($lines === [] && $cart !== []) {
        return ['lines' => [], 'grand' => 0.0, 'error' => 'Your cart contains no valid products.'];
    }

    return ['lines' => $lines, 'grand' => $grand, 'error' => null];
}

$thankYouOrderId = null;
if (isset($_GET['thank_you'])) {
    $thankYouOrderId = filter_var($_GET['order'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($thankYouOrderId === false) {
        $thankYouOrderId = null;
    }
}

if ($thankYouOrderId !== null) {
    $pageTitle = 'Order placed';
}

$lines = [];
$grandTotal = 0.0;

if ($thankYouOrderId === null) {
    $loaded = checkout_load_lines(db(), $_SESSION['cart']);
    $lines = $loaded['lines'];
    $grandTotal = $loaded['grand'];
    if ($loaded['error'] !== null) {
        $dbError = $loaded['error'];
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['confirm_order'])
    && $thankYouOrderId === null
) {
    $name = trim((string) ($_POST['shipping_name'] ?? ''));
    $email = trim((string) ($_POST['shipping_email'] ?? ''));
    $phone = trim((string) ($_POST['shipping_phone'] ?? ''));
    $address = trim((string) ($_POST['shipping_address'] ?? ''));

    if ($name === '' || $email === '' || $address === '') {
        $dbError = 'Please fill in name, email, and address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $dbError = 'Please enter a valid email address.';
    } else {
        $loaded = checkout_load_lines(db(), $_SESSION['cart']);
        $lines = $loaded['lines'];
        $grandTotal = $loaded['grand'];
        if ($loaded['error'] !== null) {
            $dbError = $loaded['error'];
        } elseif ($lines === []) {
            $dbError = 'Your cart is empty.';
        } else {
            $userId = isset($_SESSION['user_id']) ? filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            $userId = $userId !== false ? $userId : null;

            $pdo = db();
            try {
                $pdo->beginTransaction();

                foreach ($lines as $line) {
                    $u = $pdo->prepare(
                        'UPDATE products
                         SET stock = stock - ?
                         WHERE id = ? AND stock >= ? AND is_active = 1'
                    );
                    $u->execute([(int) $line['qty'], (int) $line['id'], (int) $line['qty']]);
                    if ($u->rowCount() !== 1) {
                        throw new RuntimeException('stock');
                    }
                }

                $insOrder = $pdo->prepare(
                    'INSERT INTO orders (user_id, status, total_amount, shipping_name, shipping_email, shipping_phone, shipping_address)
                     VALUES (:user_id, :status, :total, :sname, :semail, :sphone, :saddr)'
                );
                $insOrder->execute([
                    ':user_id'  => $userId,
                    ':status'   => 'pending',
                    ':total'    => number_format($grandTotal, 2, '.', ''),
                    ':sname'    => $name,
                    ':semail'   => $email,
                    ':sphone'   => $phone !== '' ? $phone : null,
                    ':saddr'    => $address,
                ]);

                $orderId = (int) $pdo->lastInsertId();

                $insItem = $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                     VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity)'
                );
                foreach ($lines as $line) {
                    $insItem->execute([
                        ':order_id'     => $orderId,
                        ':product_id'   => (int) $line['id'],
                        ':product_name' => $line['name'],
                        ':unit_price'   => number_format((float) $line['price'], 2, '.', ''),
                        ':quantity'     => (int) $line['qty'],
                    ]);
                }

                $pdo->commit();

                $_SESSION['cart'] = [];

                header('Location: checkout.php?thank_you=1&order=' . $orderId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $dbError = 'Could not place the order. Please try again (stock may have changed).';
            }
        }
    }

    $cartCount = array_sum($_SESSION['cart']);
    $loaded = checkout_load_lines(db(), $_SESSION['cart']);
    $lines = $loaded['lines'];
    $grandTotal = $loaded['grand'];
    if ($loaded['error'] !== null && $dbError === null) {
        $dbError = $loaded['error'];
    }
}

$cartCount = array_sum($_SESSION['cart']);
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
    h1 { margin: 0 0 0.95rem; font-size: 1.8rem; }
    h2 { margin: 0 0 0.65rem; color: #c4b5fd; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.06em; }
    .alert {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 0.9rem 1rem;
      margin-bottom: 0.9rem;
      background: rgb(255 255 255 / 6%);
    }
    .panel {
      border: 1px solid var(--line);
      border-radius: 16px;
      background: linear-gradient(180deg, rgb(255 255 255 / 7%), rgb(255 255 255 / 3%));
      overflow: hidden;
      box-shadow: 0 18px 34px rgb(0 0 0 / 28%);
      animation: reveal 550ms ease forwards;
      opacity: 0;
      transform: translateY(12px);
      margin-bottom: 1rem;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.84rem 0.8rem; border-bottom: 1px solid rgb(255 255 255 / 9%); }
    th { text-align: left; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .total { font-size: 1.25rem; font-weight: 800; margin: 0 0 1rem; }
    .total span { color: #a5b4fc; }
    .form-wrap {
      border: 1px solid var(--line);
      border-radius: 16px;
      background: linear-gradient(180deg, rgb(255 255 255 / 7%), rgb(255 255 255 / 3%));
      padding: 1rem;
      max-width: 34rem;
    }
    label { display: block; margin: 0 0 0.35rem; color: #dbeafe; font-size: 0.88rem; }
    .muted { color: var(--muted); font-size: 0.8rem; }
    input, textarea {
      width: 100%;
      border: 1px solid rgb(255 255 255 / 16%);
      border-radius: 10px;
      background: rgb(7 10 23 / 50%);
      color: #eef2ff;
      padding: 0.62rem 0.72rem;
      font: inherit;
      margin-bottom: 0.8rem;
      outline: none;
    }
    input:focus, textarea:focus { border-color: rgb(167 139 250 / 75%); box-shadow: 0 0 0 3px rgb(124 58 237 / 24%); }
    .actions { display: flex; gap: 0.55rem; flex-wrap: wrap; }
    .btn {
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 0.65rem 0.9rem;
      text-decoration: none;
      color: #eef2ff;
      background: rgb(255 255 255 / 7%);
      font-weight: 700;
      font-size: 0.9rem;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-primary { background: linear-gradient(120deg, #6d28d9, #0891b2); }
    @keyframes reveal { to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav">
      <a class="brand" href="index.php">ENSAJ Wear</a>
      <span class="pill">Cart (<?= (int) $cartCount ?>)</span>
    </nav>
    <?php if ($thankYouOrderId !== null): ?>
      <div class="alert" role="alert">
        <h1>Thank you!</h1>
        <p>Your order <strong>#<?= (int) $thankYouOrderId ?></strong> was recorded. Payment is not required in this demo.</p>
      </div>
      <a class="btn btn-primary" href="index.php">Back to shop</a>

    <?php else: ?>
      <h1>Checkout</h1>

      <?php if ($dbError !== null): ?>
        <div class="alert" role="alert"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if ($lines === []): ?>
        <div class="alert">Your cart is empty. Add products before checkout.</div>
        <a class="btn btn-primary" href="index.php">Browse products</a>
      <?php else: ?>
        <h2>Order summary</h2>
        <div class="panel">
          <table>
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col" class="text-end">Price</th>
                <th scope="col" class="text-center">Qty</th>
                <th scope="col" class="text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
                <tr>
                  <td><?= htmlspecialchars($line['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end"><?= htmlspecialchars(number_format($line['price'], 0), ENT_QUOTES, 'UTF-8') ?> DH</td>
                  <td class="text-center"><?= (int) $line['qty'] ?></td>
                  <td class="text-end"><?= htmlspecialchars(number_format($line['line_total'], 0), ENT_QUOTES, 'UTF-8') ?> DH</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <p class="total">
          Total: <span><?= htmlspecialchars(number_format($grandTotal, 0), ENT_QUOTES, 'UTF-8') ?> DH</span>
        </p>

        <h2>Shipping details</h2>
        <form method="post" action="checkout.php" class="form-wrap">
          <div>
            <label for="shipping_name">Full name</label>
            <input type="text" name="shipping_name" id="shipping_name" required maxlength="120"
              value="<?= htmlspecialchars(trim((string) ($_POST['shipping_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div>
            <label for="shipping_email">Email</label>
            <input type="email" name="shipping_email" id="shipping_email" required maxlength="255"
              value="<?= htmlspecialchars(trim((string) ($_POST['shipping_email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div>
            <label for="shipping_phone">Phone <span class="muted">(optional)</span></label>
            <input type="text" name="shipping_phone" id="shipping_phone" maxlength="40"
              value="<?= htmlspecialchars(trim((string) ($_POST['shipping_phone'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div>
            <label for="shipping_address">Address</label>
            <textarea name="shipping_address" id="shipping_address" rows="3" required maxlength="2000"><?= htmlspecialchars(trim((string) ($_POST['shipping_address'] ?? '')), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <input type="hidden" name="confirm_order" value="1">
          <div class="actions">
            <button type="submit" class="btn btn-primary">Confirm order</button>
            <a class="btn" href="cart.php">Edit cart</a>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
