<?php

class Ipaymu_Icheckout_PaymentController extends Mage_Core_Controller_Front_Action {

    public function processAction() {
        $model = Mage::getModel('icheckout/icheckout');
        $order_id = $model->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $amount = round($order->getGrandTotal(), 2);

        $username = $model->getUsername();
        $apikey = $model->getApiKey();
        $apiurl = $model->getApiURL();

        $baseUrl = Mage::getBaseUrl();

        // Prepare Parameters
        $params = array(
            'key' => $apikey, // API Key Merchant / Penjual
            'action' => 'payment',
            'product' => 'Order #' . $order_id,
            'price' => $amount, // Total Harga
            'quantity' => '1', // quantity,
            'comments' => 'Pembelian dari ' . $baseUrl, // Optional           
            'ureturn' => $baseUrl . 'ipaymu/checkout/return?oid=' . $order_id,
            'unotify' => $baseUrl . 'ipaymu/checkout/notify?oid=' . $order_id,
            'ucancel' => $baseUrl . 'ipaymu/checkout/cancel?oid=' . $order_id,
            /* Parameter untuk pembayaran lain menggunakan PayPal 
             * ----------------------------------------------- 
             * 'paypal_email' => 'test@mail.com',
             * 'paypal_price' => 1, // Total harga dalam kurs USD
             * 'invoice_number' => uniqid('INV-'), // Optional
             * ----------------------------------------------- */
            'format' => 'json' // Format: xml / json. Default: xml 
        );

        $params_string = http_build_query($params);

        //open connection
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, count($params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        //execute post
        $request = curl_exec($ch);

        if ($request === false) {
            echo 'Curl Error: ' . curl_error($ch);
        } else {

            $result = json_decode($request, true);

            echo $result['url'];

            if (isset($result['url']))
            //header('location: ' . $result['url']);
                $this->_redirectUrl($result['url']);
            else {
                echo "Request Error " . $result['Status'] . ": " . $result['Keterangan'];
            }
        }

        //close connection
        curl_close($ch);
    }

    public function indexAction($id) {
        $this->_redirect('checkout/cart');
    }

    public function cartAction() {
        $session = Mage::getSingleton('checkout/session');
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            $quote->setIsActive(true)->save();
        }
        $this->_redirect('checkout/cart');
    }

    public function returnAction() {
        $status = $this->getRequest()->getParam('status');
        $order_id = Mage::getModel('icheckout/icheckout')->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        if ($status == 'berhasil') {
            $this->_redirect('checkout/onepage/success');
            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true);
            $order->save();
        } else {
            $this->_redirect('checkout/onepage/success');
            $order->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true);
            $order->save();
        }
    }

    public function cancelAction() {
        $order_id = $this->getRequest()->getParam('oid');
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $this->_redirect('checkout/onepage');
        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
        $order->save();
    }

    public function notifyAction() {        
        $status = $this->getRequest()->getPost('status');
        if ($status) {
            $order_id = $this->getRequest()->getParam('oid');
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
            $invoice = $order->prepareInvoice();
            $invoice->register();
            $order->addRelatedObject($invoice);
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $order->sendNewOrderEmail();
            $order->save();
        }
    }

}
