<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$rawId = $_GET['id'] ?? '';
$id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === false) {
    header('Location: products.php?status=invalid_id');
    exit;
}

try {
    // Prevent delete if the product does not exist.
    $checkStmt = db()->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $id]);
    $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($product === false) {
        header('Location: products.php?status=not_found');
        exit;
    }

    $deleteStmt = db()->prepare('DELETE FROM products WHERE id = :id');
    $deleteStmt->execute([':id' => $id]);
} catch (Throwable $e) {
    header('Location: products.php');
    exit;
}

header('Location: products.php?status=deleted');
exit;
