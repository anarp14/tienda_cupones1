<?php
session_start();

require '../../vendor/autoload.php';

$cupon= obtener_post('cupon');
$descuento = obtener_post('descuento');
$fecha_caducidad = obtener_post('fecha_caducidad');

$pdo = conectar();


$sent = $pdo->prepare("INSERT INTO cupones (cupon, descuento, fecha_caducidad)
                       VALUES (:cupon, :descuento, :fecha_caducidad)");

$sent->execute([
    ':cupon' => $cupon,
    ':descuento' => $descuento,
    ':fecha_caducidad' => $fecha_caducidad
]);


$_SESSION['exito'] = "El cupón se ha creado con éxito.";

volver_a('Location: /admin/cupones.php');
