<?php
// Simple flash messages stored in session.

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function flash_set(string $key, string $message): void {
  $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
  if (empty($_SESSION['flash'][$key])) return null;
  $msg = (string)$_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $msg;
}
