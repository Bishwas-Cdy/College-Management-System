<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php 
    // Get college name for page title
    if (!isset($pageTitle)) $pageTitle = 'Dashboard';
    $collegeName = 'CMS';
    if (!isset($conn)) {
      if (file_exists(__DIR__ . '/../config/db.php')) {
        try {
          require_once __DIR__ . '/../config/db.php';
        } catch (Throwable $e) {
          $conn = null;
        }
      }
    }
    if (isset($conn) && $conn) {
      $settings = get_system_settings($conn);
      $collegeName = $settings['college_name'] ?? 'CMS';
    }
    echo htmlspecialchars($collegeName) . ' - ' . htmlspecialchars($pageTitle);
  ?></title>
  <!-- Bootstrap 5 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet">
  <!-- Font Awesome -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    rel="stylesheet">
  <link rel="stylesheet" href="./public/style.css">
</head>
<body>