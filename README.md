CECA TPV
=====

Este script te permitirá generar los formularios para la integración de la pasarela de pago de sistemas CECA

## Ejemplo de pago instantáneo

```php
# Sólo incluimos el autoload si la instalación no se realiza a través de Composer

include (__DIR__.'/src/autoload.php');

# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Ceca\Tpv\Tpv($config);

# Indicamos los campos para el pedido

$TPV->setFormHiddens(array(
    'Num_operacion' => '012121323',
    'Descripcion' => 'Televisor de 50 pulgadas',
    'Importe' => '568,25',
    'URL_OK' => 'http://dominio.com/direccion-todo-correcto/',
    'URL_NOK' => 'http://dominio.com/direccion-error'
));

# Imprimimos el pedido el formulario y redirigimos a la TPV

echo '<form action="'.$TPV->getPath().'" method="post">'.$TPV->getFormHiddens().'</form>';

die('<script>document.forms[0].submit();</script>');
```
Si tenemos habilitada la opción de "Comunicación on-line OK" el TPV se comunicará con nuestra web a través de la URL indicada en "URL online OK" que permitirá verificar y validar el pago.

Este script no debería ser accesible a través de links y sólo responderá el código utilizado por CECA para la "Respuesta requerida OK".

El banco siempre sólo se comunicará con nosotros a través de esta url si ha validado la tarjeta, y estará pendiente de nuestra respuesta para autorizar el cargo.

Podemos realizar un script (Lo que en el ejemplo sería http://dominio.com/direccion-control-pago) que valide los pagos de la siguiente manera:

```php
include (__DIR__.'/src/autoload.php');

# Incluye tu arquivo de configuración (copia config.php para config.local.php)

$config = require (__DIR__.'/config.local.php');

# Cargamos la clase con los parámetros base

$TPV = new Ceca\Tpv\Tpv($config);

# Realizamos la comprobación de la transacción

try {
    $TPV->checkTransaction($_POST);
} catch (\Exception $e) {
    file_put_contents(__DIR__.'/logs/errores-tpv.log', $e->getMessage(), FILE_APPEND);
    die();
}

# Actualización del registro en caso de pago

$order = Orders::where('referencia', $_POST['Num_operacion'])->firstOrFail();

$order->referencia = $_POST['Referencia'];
$order->fecha_pago = date('Y-m-d H:i:s');

$order->save();

# Finalizamos con la respuesta del código de todo correcto

die($TPV->successCode());
```