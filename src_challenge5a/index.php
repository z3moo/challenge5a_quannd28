<?php
require_once 'includes/config.php';
redirect(empty($_SESSION['user']) ? BASE_URL . '/login.php' : BASE_URL . '/users.php');
