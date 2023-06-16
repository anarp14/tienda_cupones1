<?php
session_start();

require '../../src/auxiliar.php';

$id = obtener_post('id');

// if (!comprobar_csrf()) {
//     return volver_admin();
// }

if (!isset($id)) {
    return volver_admin();
}

// TODO: Validar id
// Comprobar si el departamento tiene empleados

$pdo = conectar();
$sent = $pdo->prepare("DELETE FROM cupones WHERE id = :id");
$sent->execute([':id' => $id]);

$_SESSION['exito'] = 'El cupón se ha borrado correctamente.';

volver_a('Location: /admin/cupones.php');
