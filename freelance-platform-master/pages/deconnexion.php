<?php
require_once '../includes/functions.php';

session_destroy();
setFlashMessage('success', 'Vous avez été déconnecté avec succès.');
redirect('../index.php');
?>
