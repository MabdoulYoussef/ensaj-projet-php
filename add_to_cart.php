<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$rawId = $_GET['id'] ?? '';
$id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === false) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT id, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    header('Location: product.php?id=' . $id);
    exit;
}

if ($row === false) {
    header('Location: index.php');
    exit;
}

$stock = (int) $row['stock'];
if ($stock < 1) {
    header('Location: product.php?id=' . $id);
    exit;
}

$current = (int) ($_SESSION['cart'][$id] ?? 0);
if ($current >= $stock) {
    header('Location: product.php?id=' . $id);
    exit;
}

$_SESSION['cart'][$id] = $current + 1;

header('Location: cart.php');
exit;
