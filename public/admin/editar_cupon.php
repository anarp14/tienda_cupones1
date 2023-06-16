<?php
session_start();

require '../../vendor/autoload.php';

$id = obtener_post('id');
$cupon= obtener_post('cupon');
$descuento = obtener_post('descuento');
$fecha_caducidad = obtener_post('fecha_caducidad');

$pdo = conectar();

$set = [];
$execute = [];

if (isset($cupon) && $cupon != ''){
    $set[] = 'cupon = :cupon';
    $execute[':cupon']  = $cupon;
}
if (isset($descuento) && $descuento != ''){
    $set[] = 'descuento = :descuento';
    $execute[':descuento']  = $descuento;
}
if (isset($fecha_caducidad) && $fecha_caducidad != ''){
    $set[] = 'fecha_caducidad = :fecha_caducidad';
    $execute[':fecha_caducidad'] = $fecha_caducidad;
}

$set= !empty($set) ? 'SET ' . implode(' , ', $set) : '';



$sent = $pdo->prepare("UPDATE cupones
                       $set
                       WHERE id = :id");

$execute[':id'] = $id;

$sent->execute($execute);


$_SESSION['exito'] = "El cupón se ha modificado con éxito.";

volver_a('Location: /admin/cupones.php');
