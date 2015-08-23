<?php/** * @package       ICEPAY Payment Module for Prestashop * @author        Ricardo Jacobs <ricardo.jacobs@icepay.com> * @copyright     (c) 2015 ICEPAY. All rights reserved. * @version       2.0.6, August 2015 * @license       BSD 2 License, see https://github.com/icepay/Prestashop/blob/master/LICENSE.md */class IC_Postback{    private $byIcepayStatus;    private $byPrestaStatus;    public function init()    {        define('_PS_ADMIN_DIR_', getcwd());        require_once(realpath(dirname(__FILE__) . '/../../config/config.inc.php'));        require_once(realpath(dirname(__FILE__) . '/api/api/icepay_api_base.php'));        Configuration::loadConfiguration();        Context::getContext()->link = new Link();        $this->byIcepayStatus = array(            "OPEN"       => Configuration::get('PS_OS_ICEPAY_OPEN'),            "AUTHORIZED" => Configuration::get('PS_OS_ICEPAY_AUTH'),            "OK"         => Configuration::get('PS_OS_PAYMENT'),            "ERR"        => Configuration::get('PS_OS_ERROR'),            "REFUND"     => Configuration::get('PS_OS_REFUND')        );        $oldOrderState = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS("SELECT * FROM `" . _DB_PREFIX_ . "order_state_lang` WHERE name = '[ICEPAY] OPEN' LIMIT 0,1");        $this->byPrestaStatus = array(            Configuration::get('PS_OS_ICEPAY_OPEN') => 'OPEN',            Configuration::get('PS_OS_ICEPAY_AUTH') => 'AUTHORIZED',            Configuration::get('PS_OS_PAYMENT')     => 'OK',            Configuration::get('PS_OS_ERROR')       => 'ERR',            Configuration::get('PS_OS_REFUND')      => 'REFUND',            $oldOrderState[0]['id_order_state']     => 'OPEN',        );        $this->handlePOST();    }    private function handlePOST()    {        $icepay = new Icepay_Postback();        try        {            $icepay->setMerchantID(Configuration::get('ICEPAY_MERCHANTID'))->setSecretCode(Configuration::get('ICEPAY_SECRETCODE'));        }        catch (Exception $e)        {            echo($e->getMessage());            exit();        }        try        {            if ($icepay->validate())            {                $order = new Order($icepay->getOrderID());                $msg = new Message();                $message = strip_tags($message, '<br>');                $msg->message = "ICEPAY: Order has been updated ---------------------------------------: {$icepay->getTransactionString()} old state: {$order->order_state}; ";                $msg->id_order = $icepay->getOrderID();                $msg->private = 1;                $msg->add();                if ($icepay->canUpdateStatus($this->byPrestaStatus[$order->current_state]) || $order->current_state == 'OPEN')                {                    switch ($icepay->getStatus())                    {                        case Icepay_StatusCode::REFUND:                            $order->setCurrentState($this->byIcepayStatus[$icepay->getStatus()]);                            break;                        case Icepay_StatusCode::SUCCESS:                            $order->addOrderPayment(floatval($icepay->getPostback()->amount / 100), $icepay->getPostback()->paymentMethod, $icepay->getPostback()->paymentID);                            $order->setInvoice(true);                            $order->setCurrentState($this->byIcepayStatus[$icepay->getStatus()]);                            break;                        default:                            $order->setCurrentState($this->byIcepayStatus[$icepay->getStatus()]);                    }                }            }        }        catch (Exception $e)        {            echo($e->getMessage());        }        exit('Postback URL installed correctly');    }}$notify = new IC_Postback();$notify->init();