<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!is_post()) {
    redirect('/');
}

verify_csrf();

try {
    $order = create_cod_order($_POST);
    clear_old();
    redirect('thank-you.php?order=' . urlencode((string)$order['order_number']));
} catch (Throwable $exception) {
    remember_old($_POST);
    flash('error', $exception->getMessage());
    redirect('/#checkout');
}

