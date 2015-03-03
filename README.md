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
