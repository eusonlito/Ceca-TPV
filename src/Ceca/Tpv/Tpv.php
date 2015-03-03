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

    private $o_required = array('Environment', 'MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'URL_OK', 'URL_NOK', 'Cifrado', 'Pago_soportado');
    private $o_optional = array('Idioma', 'Descripcion');

    private $environment = '';
    private $environments = array(
        'test' => 'http://tpv.ceca.es:8000/cgi-bin/tpv',
        'real' => 'https://pgw.ceca.es/cgi-bin/tpv'
    );

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

        $o_required = array('', '', 'Cifrado', 'Pago_soportado');
        $o_optional = array('Idioma', 'Descripcion');

        $options['Num_operacion'] = $this->getOrder($options['Num_operacion']);
        $options['Importe'] = $this->getAmount($options['Importe']);

        $options['MerchantID'] = $this->setLength($options['MerchantID'], 9);
        $options['AcquirerBIN'] = $this->setLength($options['AcquirerBIN'], 10);
        $options['TerminalID'] = $this->setLength($options['TerminalID'], 8);

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

        $options['Firma'] = $this->getSignature();

        $this->setValue($options, 'Firma');

        $this->setHiddensFromValues();

        return $this;
    }

    private function setLength($value, $length)
    {
        return str_pad($value, $length, '0', STR_PAD_LEFT);
    }

    private function setHiddensFromValues()
    {
        return $this->hidden = $this->values;
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
        $code = $this->option_prefix.$option;

        if (isset($options[$option])) {
            $this->values[$code] = $options[$option];
        } elseif (isset($this->options[$option])) {
            $this->values[$code] = $this->options[$option];
        }

        return $this;
    }

    private function setValue(array $options, $option)
    {
        if (isset($options[$option])) {
            $this->values[$this->option_prefix.$option] = $options[$option];
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
        $prefix = $this->option_prefix;
        $fields = array('ClaveCifrado', 'MerchantID', 'AcquirerBIN', 'TerminalID', 'Num_operacion', 'Importe', 'TipoMoneda', 'Exponente', 'Cifrado', 'URL_OK', 'URL_NOK');
        $key = '';

        foreach ($fields as $field) {
            if (!isset($this->values[$prefix.$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to create signature key', $field));
            }

            $key .= $this->values[$prefix.$field];
        }

        return sha1($key);
    }
}
