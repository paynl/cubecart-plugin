<?php

require_once 'includes/classes/Autoload.php'; 

class PaynlBase
{
  protected $merchantId = null;
  protected $moduleName = null;
  
  private $_config;
  private $_module;
  private $_basket;
  private $_result_message;
  
  protected $setup_exception = false;
  protected $objStartApi;

  public function __construct($module = false, $basket = false)
  {
    $this->_module = $module;
    $this->_basket = & $GLOBALS['cart']->basket;
  }
  
  public function repeatVariables()
  {
    return false;
  }
  
  public function fixedVariables()
  {
    return false;
  }
  
  public function form()
  {
    return false;
  }

  
  public function transfer()
  {
    $transfer = array(
      'action' => 'index.php?_g=rm&type=gateway&cmd=process&module='. $this->moduleName .'&payWithMethod=true',
      'method' => 'post',
      'target' => '_self',
      'submit' => 'auto',
    );
    return $transfer;
  }
  
  public function process()
  { 
    try
    {
      // create transaction
      if(isset($_GET['payWithMethod']) && $_GET['payWithMethod'] == 'true')
      {
        // send the full order to the pay.nl api that will turn it into a transaction
        $arrTransaction = $this->createTransaction( $this->merchantId );

        // transaction created, lets continue to the payment page
        httpredir( $arrTransaction['transaction']['paymentURL'] );
        exit;
      }
     
      //else if user returns from paying (cancelled or sucessful)
      if(isset($_GET['orderId']))
      {
        // user gets redirected from pay.nl; payment done
        $this->completePayment( $_GET['orderId'] );
      }
    }
    catch(Pay_Api_Exception $e)
    {
      $GLOBALS['gui']->setError("De Pay.nl API gaf de volgende fout: " . $e->getMessage());
      httpredir(currentPage(array('_g', 'type', 'cmd', 'module'), array('_a' => 'gateway')));
    }
    catch(Pay_Exception $e)
    {
      $GLOBALS['gui']->setError("Er is een fout opgetreden: " . $e->getMessage());
    }
    
    // if there was an error, or not a valid get var supplied, redirect to gateway choice
    httpredir(currentPage(array('_g', 'type', 'cmd', 'module'), array('_a' => 'gateway')));
  }
  
   /* Handle IPN exchange callback
   * 
   * @return void
   */
  public function call()
  {
    try{
      // ignore pending and refund
      if($_REQUEST['action'] == 'pending'){
        echo "TRUE|Ignoring pending";
        exit;
      }
      if(substr($_REQUEST['action'], 0, 6) == 'refund'){
        echo "TRUE|Ignoring refund";
        exit;
      }

      //the transaction identifier from pay.nl
      $strPaynlOrderId = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : null;
      
      // Voor de veiligheid negeren we de rest van de data die is meegegeven in de url, 
      // we halen zelf de transactie op bij pay en we kijken wat daar de status van is
      $objApiInfo = new Pay_Api_Info();
      $objApiInfo->setApiToken( $this->_module['apitoken'] );
      $objApiInfo->setServiceId( $this->_module['service_id'] );
      $objApiInfo->setTransactionId( $strPaynlOrderId );
      
      $arrApiInfoResult = $objApiInfo->doRequest();
      
      // cubecart orderId has been saved in extra1
      $strCartOrderId = $arrApiInfoResult['statsDetails']['extra1'];

      //update the status of the cubecart order
      $this->updateOrderStatus($strCartOrderId, $strPaynlOrderId, $arrApiInfoResult);
      
      $strState     = $arrApiInfoResult['paymentDetails']['state'];
      $strStateText = Pay_Helper::getStateText($strState);

    } catch(Pay_Api_Exception $e)
    {
        $error = "De Pay.nl API gaf de volgende fout:<br />" . $e->getMessage();
    } catch(Pay_Exception $e)
    {
        $error =  "Er is een fout opgetreden:<br />" . $e->getMessage();
    }

    if(empty($error)){
        echo "TRUE|Statusupdate ontvangen. Status is: " . $strState . ' (' . $strStateText . ')';
    } else {
        echo "TRUE|".$error;
    }
    
    exit;
  }
  
  
  /* Helper method to turn multiple names into initials.
   * 
   * Used for afterpay. Note: Afterpay does not allow spaces in the initials field
   *  ex: marie jamie Adriana -> MCA
   *      Arie-Jan -> A
   * 
   * @param  string  $str  first names
   */
  public function toInitials($str)
  {
    $arrNames    = explode(' ', $str);
    $strInitials = '';
    foreach($arrNames as $strName)
    {
      $strInitials .= substr($strName, 0, 1);
    }
    return strtoupper($strInitials);
  }
  
  /* Helmer method to convert a cubecart amount to paynl amount and sets these to a transaction.
   * 
   * (convert basket total (float: 1234.56) to paynl total (int: 123456))
   * 
   * @param  float  $amount  amount to transform
   */
  public function setAmount( $amount ) 
  {
    $total = (float) $amount;
    return $this->objStartApi->setAmount( (int) ($total * 100) );
  }
  
    
  /* convert cubecart prices (in float) to paynl prices (in cents)
   * 
   * @param  float  $price 
   * @return int 
   */
  public function toCents( $price )
  {
    return (int) ( 100 * (float) $price );
  }
  
  
  /**
   * 
   * @param type $apitoken
   * @param type $service_id
   * @return booleanCheck wether a connection to the API server can be established.
   */
  public function checkConnection($apitoken, $service_id)
  {
    if( is_null($apitoken) )   $apitoken   = $this->_module['apitoken'];
    if( is_null($service_id) ) $service_id = $this->_module['service_id'];
    try
    {
      $objServiceApi = new Pay_Api_Getservice();
      $objServiceApi->setApiToken($apitoken);
      $objServiceApi->setServiceId($service_id);
      $objServiceApi->doRequest();
    } catch(Exception $e)
    {
      return false;
    }
    return true;
  }
  
  /* request all payment methods associated with apitoken/service id supplied from the admin
   * 
   * @return  array  sorted array of methods, ready to be sent to smarty
   */
  public function grabPaymentMethods()
  {
    if( ! isset($this->_module['apitoken']) )   $this->_module['apitoken']   = '';
    if( ! isset($this->_module['service_id']) ) $this->_module['service_id'] = '';
    
    if(isset($apitoken)) $this->_module['apitoken'];
    
    
    $objServiceApi = new Pay_Api_Getservice();
    $objServiceApi->setApiToken( $this->_module['apitoken']   );
    $objServiceApi->setServiceId( $this->_module['service_id']);

    $arrServiceResult = $objServiceApi->doRequest();
    
    //cleanup result for export to smarty
    $strBasePath       = $arrServiceResult['service']['basePath'];
    $arrPaymentOptions = array();
    foreach($arrServiceResult['paymentOptions'] as $paymentOption)
    {
      $tmpPaymentOption = array();
      $tmpPaymentOption['id']    = $paymentOption['id'];
      $tmpPaymentOption['name']  = $paymentOption['visibleName'];
      $tmpPaymentOption['image'] = $strBasePath . $paymentOption['path'] . $paymentOption['img'];

      if(isset($_POST['optionId']) && $paymentOption['id'] === $_POST['optionId'])
      {
        $tmpPaymentOption['checked'] = ' checked="checked"';
        $GLOBALS['smarty']->assign('optionId', (int) $paymentOption['id']); 
      }

      $arrPaymentOptions[$tmpPaymentOption['id']] = $tmpPaymentOption;
    }

    return Pay_Helper::sortPaymentOptions($arrPaymentOptions);
  }
 
  
  
  /* Update a cubecart order with the latest status from pay.nl
   * 
   * @param  string  $strCartOrderId      the cubecart order identifier
   * @param  string  $strPaynlOrderId     the pay.nl order identifier
   * @param  array   $arrApiInfoResult
   * 
   * @return  boolean   whether the update succeeded
   */
  public function updateOrderStatus($strCartOrderId, $strPaynlOrderId, $arrApiInfoResult)
  {
    $arrPaymentDetails = $arrApiInfoResult['paymentDetails'];
    $objOrder          = Order::getInstance();
    $arrSummary        = $objOrder->getSummary( $strCartOrderId );
    
    // only change the status if the order has not been paid already 
    if($arrSummary['status'] != $this->_module['paidOrderstatus'])
    {
      if($arrPaymentDetails['stateName'] === 'PAID')
      {
        $objOrder->orderStatus((int)$this->_module['paidOrderstatus'], $strCartOrderId); //default was Order::ORDER_PROCESS, 2
        $objOrder->paymentStatus(Order::PAYMENT_SUCCESS, $strCartOrderId);
      }
      elseif($arrPaymentDetails['stateName'] === 'PENDING')
      {
        $objOrder->orderStatus((int)$this->_module['pendingOrderstatus'], $strCartOrderId); //default was Order::ORDER_PENDING, 1
        $objOrder->paymentStatus(Order::PAYMENT_PENDING, $strCartOrderId);
      }
      elseif($arrPaymentDetails['stateName'] === 'CANCEL')
      {
        $objOrder->orderStatus((int)$this->_module['cancelOrderstatus'], $strCartOrderId); //default was Order::ORDER_DECLINED, 4
        $objOrder->paymentStatus(Order::PAYMENT_DECLINE, $strCartOrderId);
      }
      else
      {
        // something went wrong, ignore it so we don't influence the order status negatively
      }

      // log the transaction to cubecart
      $arrTransactionData = array(
        'trans_id'    => $strPaynlOrderId,
        'customer_id' => $arrSummary["customer_id"],
        'order_id'    => $strCartOrderId,
        'amount'      => (int)$arrPaymentDetails['amount'] / 100,
        'notes'       => 'Pay.nl orderId : ' . $strPaynlOrderId,
        'gateway'     => $this->moduleName,
      );
      $arrTransactionData['status'] = $arrPaymentDetails['state'] .
                                      ' (' . $arrPaymentDetails['stateName'] . ')';

      return $objOrder->logTransaction( $arrTransactionData );
    }
  }
  
  /* Calls the pay.nl api with the order so it can generate a transaction for us.
   * 
   * @param  int  $intMethodId  the chosen merchant to handle the payment
   * 
   * @return   array   response from pay.nl api
   */
  public function createTransaction($intMethodId)
  {
    $strCartOrderId = $this->_basket['cart_order_id'];

    $this->objStartApi = new Pay_Api_Start();

    # Token en serviceId setten
    $this->objStartApi->setApiToken($this->_module['apitoken'] );
    $this->objStartApi->setServiceId($this->_module['service_id'] );

    # Return to this url after payment
    $this->objStartApi->setFinishUrl( $GLOBALS['storeURL']. '/index.php?_g=rm&type=gateway&cmd=process&module='. $this->moduleName);

    //IPN URL
    $this->objStartApi->setExchangeUrl( $GLOBALS['storeURL']. '/index.php?_g=rm&type=gateway&cmd=call&module='. $this->moduleName);
    
    /*
     * Add order information for AfterPay, this means customer information
     * as wel as all the products
     */

    /*
     *  Add customer info
     */
    
    $arrAddress        = $this->_basket['delivery_address'];
    $arrInvoiceAddress = $this->_basket['billing_address'];
    
    $arrStreet        = PaynlBase::splitAddress($arrAddress['line1'] .' '. $arrAddress['line2']);
    $arrInvoiceStreet = PaynlBase::splitAddress($arrInvoiceAddress['line1'] .' '. $arrInvoiceAddress['line2']);
    
    $enduser = array(
      'initals'               => $this->toInitials($arrAddress['first_name']), 
      'lastName'              => $arrAddress['last_name'],
      //'language'              => '',
      //'accessCode'            => '',
      //'gender (M or F)'       => '',
      //'dob (DD-MM-YYYY)'      => '',
      'phoneNumber'           => $arrInvoiceAddress['phone'],
      'emailAddress'          => $arrInvoiceAddress['email'],
      //'bankAccount'           => '',
      //'iban'                  => '',
      //'bic'                   => '',
      //'sendConfirmMail'       => '',
      //'confirmMailTemplate'   => '',
      'address' => array(
          'streetName'   => $arrStreet[0],
          'streetNumber' => $arrStreet[1],
          'zipCode'      => $arrAddress['postcode'],
          'city'         => $arrAddress['town'],
          'countryCode'  => $arrAddress['country_iso'],
      ),
      'invoiceAddress' => array(
          'initials'     => $this->toInitials($arrInvoiceAddress['first_name']),
          'lastname'     => $arrInvoiceAddress['last_name'],
          'streetName'   => $arrInvoiceStreet[0],
          'streetNumber' => $arrInvoiceStreet[1],
          'zipCode'      => $arrInvoiceAddress['postcode'],
          'city'         => $arrInvoiceAddress['town'],
          'countryCode'  => $arrInvoiceAddress['country_iso'],
        ),
      );
    $this->objStartApi->setEnduser($enduser);

    $arrTaxCodes = array(
      '0' => 'H',
      '1' => 'H',
      '2' => 'L',
      '3' => 'N'
    );
    
    // add products
    foreach($this->_basket['contents'] as $product)
    {      
      $this->objStartApi->addProduct(
        $product['product_code'],
        $product['name'],
        $this->toCents( $product['total_price_each'] ),
        $product['quantity'],
        $arrTaxCodes[ $product['tax_type'] ]
      );
    }

      # Add taxes
      if (isset($this->_basket['total_tax']) && (float)$this->_basket['total_tax']) {
          $this->objStartApi->addProduct(0, 'taxes', $this->toCents($this->_basket['total_tax']), '1', 'N');
      }

      # Add shiping costs
      $shipping = @$this->_basket['shipping'];
      if (isset($shipping['value']) && (float)$shipping['value']) {
          $this->objStartApi->addProduct(0, $shipping['name'], $this->toCents($shipping['value']), 1, 'N');
      }

      # Add coupons
      $couponId = 1;
      foreach ($this->_basket['coupons'] as $coupon) {
          $this->objStartApi->addProduct(++$couponId, $coupon['voucher'], $this->toCents('-' . $coupon['value_display']), 1, 'N');
      }
    
    $this->objStartApi->setPaymentOptionId( $intMethodId );
    $this->setAmount( $this->_basket['total'] );

    /* description is used by afterpay to store the order id. This is also used
     * as a description with a bank transfer. */
      $this->objStartApi->setDescription($strCartOrderId);

      $ccVersion = defined('CC_VERSION') ? CC_VERSION : '-';
      $phpVersion = substr(phpversion(), 0, 3);
      $this->objStartApi->setObject(substr('cubecart 1.0.4 | ' . $ccVersion . ' | ' . $phpVersion, 0, 64));

      $this->objStartApi->setOrderNumber($strCartOrderId);

      # Save the cubecart orderid so we can refer to this when the pay.nl exchange pings back
      $this->objStartApi->setExtra1($strCartOrderId);
    
    return $this->objStartApi->doRequest();
  }
  
  /* 
   * Complete payment and redirect user to complete page
   */
  protected function completePayment( $strPaynlOrderId )
  {
    // get information about the transaction
    $objApiInfo = new Pay_Api_Info();
    $objApiInfo->setApiToken( $this->_module['apitoken'] );
    $objApiInfo->setServiceId( $this->_module['service_id'] );

    $objApiInfo->setTransactionId( $strPaynlOrderId );
    //grab info about the pay.nl transaction
    $arrApiInfoResult = $objApiInfo->doRequest();

    //update the status or the cubecart order
    $this->updateOrderStatus($this->_basket['cart_order_id'], $strPaynlOrderId, $arrApiInfoResult);

    httpredir(currentPage(array('_g', 'mod_type', 'cmd', 'module'), array('_a' => 'complete')));
    return false;
  }
  
  /**
   * Extract the streetnumber from adress, so we can feed it to the api.
   * 
   * @param string $strAddress
   * @return array
   */
  public static function splitAddress($strAddress)
  {
    $strAddress = trim($strAddress);

    $a = preg_split('/([0-9]+)/', $strAddress, 2, PREG_SPLIT_DELIM_CAPTURE);
    $strStreetName = trim(array_shift($a));
    $strStreetNumber = trim(implode('', $a));

    if(empty($strStreetName))
    { // American address notation
      $a = preg_split('/([a-zA-Z]{2,})/', $strAddress, 2, PREG_SPLIT_DELIM_CAPTURE);

      $strStreetNumber = trim(array_shift($a));
      $strStreetName = implode(' ', $a);
    }

    return array($strStreetName, $strStreetNumber);
  }
}