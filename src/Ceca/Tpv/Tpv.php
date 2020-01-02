<?php
namespace Ceca\Tpv;

use Exception;

class Tpv
{
    private $options = array(
        'Environment' => 'test', // Puedes indicar test o real
        'TerminalID' => '1',
        'TipoMoneda' => '978',
        'Exponente' => '2',
        'Cifrado' => 'SHA1',
        'Idioma' => '1',
        'Pago_soportado' => 'SSL'
    );

    private $o_required = array('Environment', 'ClaveCifrado', 'MerchantID', 'AcquirerBIN', 'TerminalID', 'TipoMoneda', 'Exponente', 'Cifrado', 'Pago_soportado');
    private $o_optional = array('Idioma', 'Descripcion', 'URL_OK', 'URL_NOK', 'Tipo_operacion', 'Datos_operaciones', 'PAN', 'Caducidad', 'CVV2', 'Pago_elegido');

    private $environment = '';
    private $environments = array(
        'test' => 'https://tpv.ceca.es/tpvweb/tpv/compra.action',
        'real' => 'https://pgw.ceca.es/tpvweb/tpv/compra.action'
    );

    private $success = '$*$OKY$*$';

    private $values = array();
    private $hidden = array();

    public function __construct(array $options)
    {
        return $this->setOption($options);
    }

    public function setOption($option, $value = null)
    {
        if (is_array($option)) {
            $options = $option;
        } elseif ($value !== null) {
            $options = array($option => $value);
        } else {
            throw new Exception(sprintf('Option <strong>%s</strong> can not be empty', $option));
        }

        $options = array_merge($this->options, $options);

        foreach ($this->o_required as $option) {
            if (empty($options[$option])) {
                throw new Exception(sprintf('Option <strong>%s</strong> is required', $option));
            }

            $this->options[$option] = $options[$option];
        }

        foreach ($this->o_optional as $option) {
            if (array_key_exists($option, $options)) {
                $this->options[$option] = $options[$option];
            }
        }

        if (isset($options['environments'])) {
            $this->environments = array_merge($this->environments, $options['environments']);
        }

        $this->setEnvironment($options['Environment']);

        return $this;
    }

    public function getOption($key = null)
    {
        return $key ? $this->options[$key] : $this->options;
    }

    public function setEnvironment($mode)
    {

        $this->environment = $this->getEnvironments($mode);

        return $this;
    }

    public function getPath($path = '')
    {
        return $this->environment.$path;
    }

    public function getEnvironments($key = null)
    {
        if (empty($this->environments[$key])) {
            $envs = implode('|', array_keys($this->environments));
            throw new Exception(sprintf('Environment <strong>%s</strong> is not valid [%s]', $key, $envs));
        }

        return $key ? $this->environments[$key] : $this->environments;
    }

    public function setFormHiddens(array $options)
    {
        $this->hidden = $this->values = array();

        $options['Importe'] = $this->getAmount($options['Importe']);

        $this->setValueDefault($options, 'MerchantID', 9);
        $this->setValueDefault($options, 'AcquirerBIN', 10);
        $this->setValueDefault($options, 'TerminalID', 8);
        $this->setValueDefault($options, 'TipoMoneda');
        $this->setValueDefault($options, 'Exponente');
        $this->setValueDefault($options, 'Cifrado');
        $this->setValueDefault($options, 'Pago_soportado');
        $this->setValueDefault($options, 'Idioma');

        $this->setValue($options, 'Num_operacion');
        $this->setValue($options, 'Importe');
        $this->setValue($options, 'URL_OK');
        $this->setValue($options, 'URL_NOK');
        $this->setValue($options, 'Descripcion');
        $this->setValue($options, 'Tipo_operacion');
        $this->setValue($options, 'Datos_operaciones');

        if (!empty($options['PAN'])) {
            $this->setCreditCardInputs($options);
        }

        $this->setValueLength('MerchantID', 9);
        $this->setValueLength('AcquirerBIN', 10);
        $this->setValueLength('TerminalID', 8);

        $options['Firma'] = $this->getSignature();

        $this->setValue($options, 'Firma');

        $this->setHiddensFromValues();

        return $this;
    }

    private function setCreditCardInputs(array $options)
    {
        $options['Pago_elegido'] = 'SSL';

        $this->setValue($options, 'PAN');
        $this->setValue($options, 'Caducidad');
        $this->setValue($options, 'CVV2');
        $this->setValue($options, 'Pago_elegido');
    }

    private function setValueLength($key, $length)
    {
        $this->values[$key] = str_pad($this->values[$key], $length, '0', STR_PAD_LEFT);

        return $this;
    }

    private function setHiddensFromValues()
    {
        $this->hidden = $this->values;

        return $this;
    }

    public function getFormHiddens()
    {
        if (empty($this->hidden)) {
            throw new Exception('Form fields must be initialized previously');
        }

        $html = '';

        foreach ($this->hidden as $field => $value) {
            $html .= "\n".'<input type="hidden" name="'.$field.'" value="'.$value.'" />';
        }

        return trim($html);
    }

    private function setValueDefault(array $options, $option)
    {
        if (isset($options[$option])) {
            $this->values[$option] = $options[$option];
        } elseif (isset($this->options[$option])) {
            $this->values[$option] = $this->options[$option];
        }

        return $this;
    }

    private function setValue(array $options, $option)
    {
        if (isset($options[$option])) {
            $this->values[$option] = $options[$option];
        }

        return $this;
    }

    public function getAmount($amount)
    {
        if (empty($amount)) {
            return '000';
        }
        $amount = preg_replace('/[^0-9,\.]/', '', $amount);
        // Remove pretty number format: 1.234,56 > 1234,56
        if (preg_match('/[\d]+\.[\d]+,[\d]+/', $amount)) {
            $amount = str_replace('.', '', $amount);
        }
        // Remove pretty number format: 1,234.56 > 1234.56
        if (preg_match('/[\d]+,[\d]+\.[\d]+/', $amount)) {
            $amount = str_replace(',', '', $amount);
        }
        // Remove comma as decimal separator: 1234,56 > 1234.56
        if (strpos($amount, ',') !== false) {
            $amount = str_replace(',', '.', $amount);
        }
        $amount = floatval($amount);
        // Truncate float from second decimal (not rounded): 1.119 > 1.11
        if (($point = strpos($amount, '.')) !== false) {
            $amount = substr($amount, 0, $point + 1 + 2);
        }
        // Set as Ceca valid amount value: 12.34 = 1234
        // Avoid to use intval, round or sprintf without remove decimals before
        // because this functions applies a round.
        return sprintf('%03d', preg_replace('/\.[0-9]+$/', '', $amount * 100));
    }

    public function getSignature()
    {
        $fields = array('MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'Cifrado', 'URL_OK', 'URL_NOK');
        $key = '';

        foreach ($fields as $field) {
            if (!isset($this->values[$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to create signature key', $field));
            }

            $key .= $this->values[$field];
        }

        return $this->makeHash($key);
    }

    public function checkTransaction(array $post)
    {
        if (empty($post) || empty($post['Firma'])) {
            throw new Exception('POST data is empty');
        }

        $fields = array('MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'Referencia');
        $key = '';

        foreach ($fields as $field) {
            if (empty($post[$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to verify transaction', $field));
            }

            $key .= $post[$field];
        }

        $signature = $this->makeHash($key);

        if ($signature !== $post['Firma']) {
            throw new Exception(sprintf('Signature not valid (%s != %s)', $signature, $post['Firma']));
        }

        return $post['Firma'];
    }

    private function makeHash($message)
    {
        $message = $this->options['ClaveCifrado'].$message;

        if ($this->options['Cifrado'] === 'SHA2') {
            return hash('sha256', $message);
        }

        return sha1($message);
    }

    public function successCode()
    {
        return $this->success;
    }
}

