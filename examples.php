<?php
include __DIR__.'/src/autoload.php';

# Carga tu fichero con la configuración personalizada en config.local.php
$config = require __DIR__.'/config.php';

# Ejemplo de pago instantáneo
# Este proceso se realiza para pagos en el momento, sin necesidad de confirmación futura (TransactionType = 0)

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
