<?php session_start() ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/css/output.css" rel="stylesheet">
    <title>Comprar</title>
</head>

<body>
    <?php require '../vendor/autoload.php';

    if (!\App\Tablas\Usuario::esta_logueado()) {
        return redirigir_login();
    }

    $carrito = unserialize(carrito());

    $cupon = obtener_get('cupon');

    if (isset($cupon)) {
        $pdo = conectar();
        $sent = $pdo->prepare('SELECT * FROM cupones WHERE (unaccent(cupon)) = upper(unaccent(:cupon))');
        $sent->execute([':cupon' => $cupon]);
        $cupon_encontrado = $sent->fetch();
        if ($cupon_encontrado) {   //Si el cupon se encuentra en la bd, la variable devuelve un array asociativo, en caso contrario, devuelve false.
            $cupon_id = $cupon_encontrado['id'];
        }
    }

    if (obtener_post('_testigo') !== null) {
        $ids = implode(', ', $carrito->getIds());
        $where = "WHERE id IN ($ids)";
        $pdo = conectar();
        $sent = $pdo->query("SELECT *
                                 FROM articulos
                                $where");
        foreach ($sent->fetchAll(PDO::FETCH_ASSOC) as $cupon_encontrado) {
            if ($cupon_encontrado['stock'] < $carrito->getLinea($cupon_encontrado['id'])->getCantidad()) {
                $_SESSION['error'] = 'No hay existencias suficientes para crear la factura.';
                return volver();
            }
        }
        // Crear factura
        $usuario = \App\Tablas\Usuario::logueado();
        $usuario_id = $usuario->id;



        $pdo->beginTransaction();
        $sent = $pdo->prepare('INSERT INTO facturas (usuario_id, cupon_id)
                               VALUES (:usuario_id, :cupon_id)
                               RETURNING id');
        $sent->execute([':usuario_id' => $usuario_id, ':cupon_id' => $cupon_id]);
        $factura_id = $sent->fetchColumn();
        $lineas = $carrito->getLineas();
        $values = [];
        $execute = [':f' => $factura_id];
        $i = 1;

        foreach ($lineas as $id => $linea) {
            $values[] = "(:a$i, :f, :c$i)";
            $execute[":a$i"] = $id;
            $execute[":c$i"] = $linea->getCantidad();
            $i++;
        }

        $values = implode(', ', $values);
        $sent = $pdo->prepare("INSERT INTO articulos_facturas (articulo_id, factura_id, cantidad)
                               VALUES $values");
        $sent->execute($execute);
        foreach ($lineas as $id => $linea) {
            $cantidad = $linea->getCantidad();
            $sent = $pdo->prepare('UPDATE articulos
                                      SET stock = stock - :cantidad
                                    WHERE id = :id');
            $sent->execute([':id' => $id, ':cantidad' => $cantidad]);
        }
        $pdo->commit();
        $_SESSION['exito'] = 'La factura se ha creado correctamente.';
        unset($_SESSION['carrito']);
        return volver();
    }


    $errores = ['cupon' => []];

    if (isset($cupon)) {

        $encontrado = false;

        $hoy = date('Y-m-d');

        if ($cupon_encontrado['cupon'] === strtoupper($cupon)) {
            $encontrado = true;
            if ($cupon_encontrado['fecha_caducidad'] < $hoy) {
                $errores['cupon'][] = 'El cupón ha caducado.';
            }
        }
        if (!$encontrado) {
            $errores['cupon'][] = 'No existe ese cupón.';
        }
    }

    $vacio = empty($errores['cupon']);

    ?>

    <div class="container mx-auto">
        <?php require '../src/_menu.php';
        require '../src/_alerts.php'; ?>

        <div class="overflow-y-auto py-4 px-3 bg-gray-50 rounded dark:bg-gray-800">
            <table class="mx-auto text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <th scope="col" class="py-3 px-6">Código</th>
                    <th scope="col" class="py-3 px-6">Descripción</th>
                    <th scope="col" class="py-3 px-6">Cantidad</th>
                    <th scope="col" class="py-3 px-6">Precio</th>
                    <th scope="col" class="py-3 px-6">Importe</th>
                </thead>
                <tbody>
                    <?php $total = 0 ?>
                    <?php foreach ($carrito->getLineas() as $id => $linea) : ?>
                        <?php
                        $articulo = $linea->getArticulo();
                        $codigo = $articulo->getCodigo();
                        $cantidad = $linea->getCantidad();
                        $precio = $articulo->getPrecio();
                        $importe = $cantidad * $precio;
                        $total += $importe;

                        if ($vacio && isset($cupon)) {
                            $descuento = hh($cupon_encontrado['descuento']);
                            $total_con_descuento = $total - ($total * ($descuento / 100));
                        }
                        ?>

                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                            <td class="py-4 px-6"><?= $articulo->getCodigo() ?></td>
                            <td class="py-4 px-6"><?= $articulo->getDescripcion() ?></td>
                            <td class="py-4 px-6 text-center"><?= $cantidad ?></td>
                            <td class="py-4 px-6 text-center">
                                <?= dinero($precio) ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?= dinero($importe) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"></td>
                        <td class="text-center font-semibold">TOTAL:</td>
                        <td class="text-center font-semibold"><?= dinero($total) ?></td>
                    <tr>
                        <?php if ($vacio && isset($cupon)) : ?>
                    <tr>
                        <td colspan="3"></td>
                        <td class="text-center font-semibold">TOTAL con descuento:</td>
                        <td class="text-center font-semibold"><?= dinero($total_con_descuento) ?></td>
                        <td class="text-center font-semibold"><?= $cupon_encontrado['cupon'] ?></td>
                    </tr>

                <?php endif ?>

                </tfoot>
                <!-- Cuestionario cupones -->
                <div>
                    <p>¿Tienes un cupón de descuento?</p>
                    <form action="" method="GET" class="mx-auto flex mt-4">
                        <input type="text" name="cupon" value="<?= isset($cupon_encontrado['cupon']) ? $cupon_encontrado['cupon'] : '' ?>" class="border text-sm rounded-lg p-2.5">
                        <?php foreach ($errores['cupon'] as $err) : ?>
                            <p class="mt-2 text-sm text-red-600 dark:text-red-500"><span class="font-bold">¡Error!</span> <?= $err ?></p>
                        <?php endforeach ?>
                        <button type="submit" class=" focus:outline-none text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-900">Comprobar</button>
                    </form>
                </div>
            </table>

            <form action="" method="POST" class="mx-auto flex mt-4">
                <input type="hidden" name="_testigo" value="1">
                <button type="submit" href="" class="mx-auto focus:outline-none text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-green-600 dark:hover:bg-green-700 dark:focus:ring-green-900">Realizar pedido</button>
            </form>
        </div>
    </div>
    <script src="/js/flowbite/flowbite.js"></script>
</body>

</html>