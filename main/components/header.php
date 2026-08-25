<?php
/**
 * Mutendi CMS — church shell: everything above the page content.
 *
 * A page includes config.php, then this file, writes its content, then
 * includes footer.php. $page_title is optional.
 */

if (!isset($base_url)) { require_once __DIR__ . '/../includes/config.php'; }

$page_title = $page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Light only: nothing on this side has a dark variant, so the browser is
     told not to invert anything when the OS is set to dark. -->
<meta name="color-scheme" content="light">
<meta name="theme-color" content="#ffffff">
<title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars($church['name']) ?></title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars($church['logo']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
</head>
<body>

<a class="skip" href="#content">Skip to content</a>

<div class="app" id="app">

  <?php require __DIR__ . '/sidebar.php'; ?>

  <div class="shell">
    <?php require __DIR__ . '/topbar.php'; ?>

    <main class="content" id="content">
