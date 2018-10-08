<?php

class RetailcrmTestHelper
{
    public static function createOrderPayment($order_reference)
    {
        $orderPayment = new OrderPayment();
        $orderPayment->order_reference = $order_reference;
        $orderPayment->id_currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $orderPayment->conversion_rate = 1.000000;
        $orderPayment->amount = 100;
        $orderPayment->payment_method = 'Bank wire';
        $orderPayment->date_add = date('Y-m-d H:i:s');

        $orderPayment->save();

        return $orderPayment;
    }

    public static function deleteOrderPayment($id)
    {
        $orderPayment = new OrderPayment($id);

        return $orderPayment->delete();
    }

    public static function getMaxOrderId()
    {
        return Db::getInstance()->getValue(
            'SELECT MAX(id_order) FROM `' . _DB_PREFIX_ . 'orders`'
        );
    }
}
