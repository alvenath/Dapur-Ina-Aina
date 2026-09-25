<?php
/**
 * Index: Entry point — redirect berdasarkan status login
 */
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
