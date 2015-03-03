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
    private $o_optional = array('Idioma', 'Descripcion', 'URL_OK', 'URL_NOK');

    private $environment = '';
    private $environments = array(
        'test' => 'http://tpv.ceca.es:8000/cgi-bin/tpv',
        'real' => 'https://pgw.ceca.es/cgi-bin/tpv'
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

        $options['Num_operacion'] = $this->getOrder($options['Num_operacion']);
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

        $this->setValueLength('MerchantID', 9);
        $this->setValueLength('AcquirerBIN', 10);
        $this->setValueLength('TerminalID', 8);

        $options['Firma'] = $this->getSignature();

        $this->setValue($options, 'Firma');

        $this->setHiddensFromValues();

        return $this;
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

    public function getOrder($order)
    {
        if (preg_match('/^[0-9]+$/', $order)) {
            $order = sprintf('%012d', $order);
        }

        $len = strlen($order);

        if (($len < 4) || ($len > 12)) {
            throw new Exception('Order code must have more than 4 digits and less than 12');
        } elseif (!preg_match('/^[0-9]{4}[0-9a-zA-Z]{0,8}$/', $order)) {
            throw new Exception('First four order digits must be numbers and then only are allowed numbers and letters');
        }

        return $order;
    }

    public function getAmount($amount)
    {
        if (empty($amount)) {
            return '000';
        } elseif (preg_match('/[\.,]/', $amount)) {
            return str_replace(array('.', ','), '', $amount);
        } else {
            return ($amount * 100);
        }
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

        return sha1($this->options['ClaveCifrado'].$key);
    }

    public function checkTransaction(array $post)
    {
        if (empty($post) || empty($post['Firma'])) {
            throw new Exception('_POST data is empty');
        }

        $fields = array('MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'Referencia');
        $key = '';

        foreach ($fields as $field) {
            if (empty($post[$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to verify transaction'));
            }

            $key .= $post[$field];
        }

        $signature = sha1($this->options['ClaveCifrado'].$key);

        if ($signature !== $post['Firma']) {
            throw new Exception(sprintf('Signature not valid (%s != %s)', $signature, $post['Firma']));
        }

        return $post['Firma'];
    }

    public function successCode()
    {
        return $this->success;
    }
}
