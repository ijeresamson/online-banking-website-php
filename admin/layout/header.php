<?php 
ob_start();
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">

    <link rel="icon" type="image/png" href="uploads/favicon.png">

    <title>Admin Panel</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="dist/css/font_awesome_5_free.min.css">
    <link rel="stylesheet" href="dist/css/select2.min.css">
    <link rel="stylesheet" href="dist/css/bootstrap-tagsinput.css">
    <link rel="stylesheet" href="dist/css/duotone-dark.css">
    <link rel="stylesheet" href="dist/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="dist/css/iziToast.min.css">
    <link rel="stylesheet" href="dist/css/fontawesome-iconpicker.min.css">
    <link rel="stylesheet" href="dist/css/bootstrap4-toggle.min.css">
    <link rel="stylesheet" href="dist/css/style.css">
    <link rel="stylesheet" href="dist/css/components.css">
    <link rel="stylesheet" href="dist/css/air-datepicker.min.css">
    <link rel="stylesheet" href="dist/css/spacing.css">
    <link rel="stylesheet" href="dist/css/custom.css">

    <script src="dist/js/jquery-3.7.0.min.js"></script>
    <script src="dist/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/popper.min.js"></script>
    <script src="dist/js/tooltip.js"></script>
    <script src="dist/js/jquery.nicescroll.min.js"></script>
    <script src="dist/js/moment.min.js"></script>
    <script src="dist/js/stisla.js"></script>
    <script src="dist/js/jscolor.js"></script>
    <script src="dist/js/bootstrap-tagsinput.min.js"></script>
    <script src="dist/js/select2.full.min.js"></script>
    <script src="dist/js/jquery.dataTables.min.js"></script>
    <script src="dist/js/dataTables.bootstrap4.min.js"></script>
    <script src="dist/js/iziToast.min.js"></script>
    <script src="dist/js/fontawesome-iconpicker.js"></script>
    <script src="dist/js/air-datepicker.min.js"></script>
    <script src="dist/tinymce/tinymce.min.js"></script>
    <script src="dist/js/bootstrap4-toggle.min.js"></script>
</head>