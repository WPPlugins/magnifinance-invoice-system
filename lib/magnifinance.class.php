<?php

/**
 * WHMCS Addon to help manage calls to MagniFinance
 * MagniFinance API: https://app.magnifinance.com
 *
 * PHP version 5
 *
 * @package    WHMCS-MagniFinance
 * @author     WebDS <info@webds.pt>
 * @copyright  WebDS
 * @license     CC Attribution-NonCommercial-NoDerivs
 * @version    $1.0$
 * @link       http://www.webds.pt/
 */
class MAGNIFINANCE_WEB {

    function __construct() {
        require_once('nusoap/nusoap.php');

        $this->loginEmail = get_option('wc_mf_loginemail');
        $this->loginToken = get_option('wc_mf_api_logintoken');
        $this->vat = get_option('wc_mf_vat');

        $this->api_url = 'https://app.magnifinance.com/Magni_API/Magni_API.asmx?WSDL';

        $this->service = new nusoap_client($this->api_url, TRUE);
    }

    function Invoice_Create($params) {
        //pre($params);
        $return = (object) $this->service->call("Invoice_Create", $params);

        //pre($this->service);
        //exit();

        return $return;
    }

    function prepInvoice($order, $order_id, $close = false) {

        $invoiceID = get_post_meta($order_id, 'wc_mf_inv_num', true);

        if (empty($invoiceID))
            $invoiceID = 0;



        $client_name = $order->billing_first_name . " " . $order->billing_last_name;

        $billing_address = $order->billing_address_1;

        $client_vat = $order->billing_nif;
        $customer_id = '';
        if (!empty($order->customer_user)) {
            $customer_id = $order->customer_user;
        }


        if (isset($order->billing_address_2))
            $billing_address = $billing_address . "\n" . $order->billing_address_2 . "\n";

        if ($order->billing_company == '') {
            $name_go = $client_name . " (" . $order->billing_email . ")";
        } else {
            $name_go = $order->billing_company;
        }

        // Lets get the user's MagniFinance data

        if (empty($client_vat)) {
            $client_vat = '999999990';
        }

        $client = array(
            'ClientNIF' => $client_vat,
            'ClientName' => $name_go,
            'ClientAddress' => $billing_address,
            'ClientCity' => $order->billing_city,
            'ClientEmail' => $order->billing_email,
            'ClientCountryCode' => $order->billing_country,
            'ClientZipCode' => $order->billing_postcode,
            'ClientPhoneNumber' => $order->billing_phone,
            'External_Id' => $customer_id
        );


        $InvoiceType = 'I';
        if (get_option('wc_mf_create_simplified_invoice') == 1)
            $InvoiceType = 'S';


        $tax = new WC_Tax(); //looking for appropriate vat for specific product
        $apply_tax = $tax->find_rates(array('country' => $order->billing_country));

        $invoice = array(
            'InvoiceId' => $invoiceID,
            'InvoiceDate' => date('Y-m-d', strtotime($order->order_date)),
            'InvoiceDueDate' => date('Y-m-d', strtotime("+1 month", strtotime($order->order_date))),
            'InvoiceType' => $InvoiceType,
            'Products' => array(
                'InvoiceProduct' => $this->prepItems($order, $apply_tax)
            )
        );

        $params = array(
            'Authentication' => array(
                'LoginEmail' => $this->loginEmail,
                'LoginToken' => $this->loginToken
            ),
            'Client' => $client,
            'Invoice' => $invoice,
            'IsToClose' => $close
        );


        if (get_option('wc_mf_send_invoice') == 1 && $invoiceID > 0)
            $params['SendByEmailToAddress'] = $order->billing_email;

        return $params;
    }

    function prepItems($order, $apply_tax = '') {

        $vat = $this->vat;

        $usedCoupons = $order->get_used_coupons();

        if (!empty($usedCoupons))
            $coupon = $this->getCoupons($usedCoupons, $order);

        //Divisor (for our math).
        $vatDivisor = 1 + (abs($this->vat) / 100);

        if (!empty($apply_tax) && $apply_tax['rate'] > 0) {
            $vat = $apply_tax['rate'];
            $vatDivisor = 1 + ($apply_tax['rate'] / 100);
        }

        foreach ($order->get_items() as $item) {

            $pid = $item['item_meta']['_product_id'][0];

            $ProductUnitPrice = $order->get_item_subtotal($item, false, false);
            $ProductDiscount['item'] = 0;

            if (!empty($usedCoupons) && $coupon->discount_type != 'fixed_cart' && $coupon->discount_type != 'fixed_product')
                $ProductDiscount = $this->handleDiscount($coupon, $order, $item);


            $items[] = array(
                'ProductCode' => "#" . $pid,
                'ProductDescription' => $item['qty'] . "x " . $item['name'],
                'ProductUnitPrice' => $ProductUnitPrice,
                'ProductQuantity' => $item['qty'],
                'ProductDiscount' => $ProductDiscount['item'],
                'ProductType' => 'P',
                'TaxValue' => round($order->get_item_tax($item) / $order->get_item_total($item) * 100),
            );

            if (!empty($usedCoupons) && $coupon->discount_type == 'fixed_product') {
                $ProductDiscount = $this->handleDiscount($coupon, $order, $item);

                if (is_array($ProductDiscount['item'])) {

                    $items[] = $ProductDiscount['item'];
                }
            }
        }

        if ($coupon->discount_type == 'fixed_cart') {
            $ProductDiscount = $this->handleDiscount($coupon, $order, $item);
            if (is_array($ProductDiscount['item']))
                $items[] = $discount_type['item'];
        }



        $shipping = reset($order->get_items('shipping'));

        if (isset($shipping['method_id'])) {
            $ProductUnitPrice = $shipping['cost'];

            if ($apply_tax['shipping'] == 'no') {
                $vat = 0;
            }

            $items[] = array(
                'ProductCode' => 'Envio',
                'ProductDescription' => 'Custos de Envio',
                'ProductUnitPrice' => $ProductUnitPrice,
                'ProductQuantity' => 1,
                'ProductType' => 'S',
                'TaxValue' => $this->vat,
            );
        }
        return $items;
    }

    function getCoupons($usedCoupons, $order) {

        foreach ($usedCoupons as $code) {
            $coupon = new WC_Coupon($code);
        }

        return $coupon;
    }

    function handleDiscount($coupon, $order, $item = null) {

        $return = $this->getDiscountLine($coupon, $item, $order);


        return $return;
    }

    function getDiscountLine(WC_Coupon $coupon, $item, $order) {

        $return['type'] = $coupon->discount_type;
        switch ($coupon->discount_type) {
            case 'fixed_cart':
                $return['item'] = array(
                    'ProductCode' => __('Desconto'),
                    'ProductDescription' => $coupon->code,
                    'ProductUnitPrice' => -abs($coupon->coupon_amount),
                    'ProductQuantity' => $item['qty'],
                    'ProductDiscount' => 0,
                    'ProductType' => 'P',
                    'TaxValue' => $this->vat,
                );
                break;
            case 'percent':
                $return['item'] = (float) number_format($coupon->coupon_amount, 2, '.', '');
                break;
            case 'fixed_product':
                if (in_array($item['item_meta']['_product_id'][0], $coupon->product_ids)) {
                    $return['item'] = array(
                        'ProductCode' => __('Desconto') . ' - ' . $coupon->code,
                        'ProductDescription' => $item['qty'] . "x " . $item['name'],
                        'ProductUnitPrice' => -abs($coupon->coupon_amount),
                        'ProductQuantity' => $item['qty'],
                        'ProductDiscount' => 0,
                        'ProductType' => 'P',
                        'TaxValue' => $this->vat,
                    );
                } else {
                    $return['item'] = 0;
                }
                break;
            case 'percent_product':
                if (in_array($item['item_meta']['_product_id'][0], $coupon->product_ids))
                    $return['item'] = (float) number_format($coupon->coupon_amount, 2, '.', '');
                else
                    $return['item'] = 0;
                break;
        }

        return $return;
    }

}
