<?php
/**
 * Copyright © 2015 Sendbox . All rights reserved.
 */
namespace Sendbox\Shipment\Helper;
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
     protected $scopeConfig;
     protected $_context;
     protected $_orders;
      protected $_region;
      protected $_storeManager;
      protected $_convertOrder;
      protected $_shipmentNotifier;
    protected $_trackFactory;
     

  /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
  public function __construct(\Magento\Framework\App\Helper\Context $context,
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Sales\Model\Order $orders,
    \Magento\Directory\Model\Region $region,
     \Magento\Directory\Model\CountryFactory $countryFactory,
      \Magento\Store\Model\StoreManagerInterface $storeManager,
      \Magento\Sales\Model\Convert\Order $convertorder,
     \Magento\Shipping\Model\ShipmentNotifier $shipmentNotifier,
   \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory

  ) {
  
      $this->scopeConfig = $scopeConfig;
      $this->_context=$context;
        $this->_orders=$orders;
         $this->_region=$region;
        $this->_countryFactory = $countryFactory;
        $this->_storeManager = $storeManager;
        $this->_convertOrder=$convertorder;
        $this->_shipmentNotifier=$shipmentNotifier;
    $this->_trackFactory=$trackFactory;
          parent::__construct($context);

  }


   public function getSendboxConfig($param) {
           $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

           return $this->scopeConfig->getValue("shipment/parameters/".$param, $storeScope);


        }
        public function getWebsiteUrl()
        {
          return $this->_storeManager->getStore()->getBaseUrl();
        }

        public function getOrder($id)
        {
          return $this->_orders->load($id);
        }



public function getAuthHeader()
{
if($this->getSendboxConfig('test_mode'))
  return $this->getSendboxConfig('sendbox_auth_header_test');
  else
    return $this->getSendboxConfig('sendbox_auth_header');

}
    public function getShipmentPostUrl()
    {
          $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
       $url= $objectManager->create('Magento\Backend\Helper\Data')->getUrl("shipment/postshipmentadmin/index");

        return $url;
    }
public function isAuthHeaderSet()
{
if($this->getSendboxConfig('sendbox_auth_header_test')!=null && $this->getSendboxConfig('sendbox_auth_header')!=null)
  return  false;
  else
     return  true;
  
}
public function getBaseUrl()
{
if($this->getSendboxConfig('test_mode'))
  return "http://api.sendbox.com.ng";
  else
     return "https://api.sendbox.ng";

}
public function getBaseTrackUrl()
{
if($this->getSendboxConfig('test_mode'))
  return "http://sendbox.com.ng";
  else
     return "https://sendbox.ng";

}
 public function make_shipment($order,$tracking_code,$carrier_code)
  {

    if($order->canShip())
{
  
// Initialize the order shipment object

$shipment = $this->_convertOrder->toShipment($order);

// Loop through order items
foreach ($order->getAllItems() AS $orderItem) {
    // Check if order item has qty to ship or is virtual
    if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
        continue;
    }

 $qtyShipped = $orderItem->getQtyToShip();

    // Create shipment item with qty
    $shipmentItem = $this->_convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

    // Add shipment item to shipment
    $shipment->addItem($shipmentItem);
}

// Register shipment
$shipment->register();

$shipment->getOrder()->setIsInProcess(true);

try {
    // Save created shipment and order
    $shipment->save();
    $shipment->getOrder()->save();

  
 
   $order->addStatusHistoryComment(
        __('Sendbox Tracking Code : <strong>'.$tracking_code.'<strong>')
    )->save();  


    // Send email
    $this->_shipmentNotifier->notify($shipment);

    $shipment->save();
} catch (\Exception $e) {
    throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
}


         return true;
        
}else{
  return false;

}
  }
 
 

 public function build_payload($order,$selected_courier_id,$fee)
  {
      //billing street
        $street=$this->getSendboxConfig('origin_street');
        
        //shipping street
        $shipping_street="";
        $i=0;
        $shipping_str_length=count($order->getShippingAddress()->getStreet());
        foreach ($order->getShippingAddress()->getStreet() as $str) {
          if($i != ($shipping_str_length-1))
          $shipping_street.=$str.",";
          else
           $shipping_street.=$str;
          $i++;
        }
        //items
        $items_string='[';
        $item_length=count($order->getAllItems());
        $j=0;
      
        foreach ($order->getAllItems() as $item) 
         {
           
            $output='{"name": "'.$item->getName().'",
            "weight": '.$item->getWeight().',
            "package_size_code": "medium",
            "quantity": '.$item->getQtyOrdered().',
            "value": '.$item->getPrice().',
             "reference_code": "'.$item->getSku().'",
            "amount_to_receive": '.$item->getPrice().'
           }';
             if($j != ($item_length-1))
              $output.=',';
            $j++;
           $items_string.=$output;
         }//end for each item
           $items_string=$items_string.',{"name": "delivery fee",
            "weight": 0,
            "package_size_code": "medium",
            "quantity": 1,
            "value": '.$order->getShippingAmount().',
             "reference_code": "'.$order->getIncrementId().'",
            "amount_to_receive": '.$order->getShippingAmount().'
           }';
         $items_string.=']';
  $origin_country = $this->_countryFactory->create()->loadByCode($this->getSendboxConfig('country_id'))->getName();

$shippingCountry = $this->_countryFactory->create()->loadByCode($order->getShippingAddress()->getData()['country_id'])->getName();

$pickup_date= $order->getCreatedAt();   
$origin_name=$this->getSendboxConfig('origin_name');
$origin_phone=$this->getSendboxConfig('origin_phone');
$origin_street=$this->getSendboxConfig('origin_street');
$origin_city=$this->getSendboxConfig('origin_city');
$origin_email=$this->getSendboxConfig('origin_email');
$origin_state=$this->_region->load($this->getSendboxConfig('region_id'))->getName();
    $payload=' {
  "origin_name": "'.$origin_name.'",
  "origin_email": "'.$origin_email.'",
  "origin_phone": "'.$origin_phone.'",
  "origin_street":  "'.$street.'",
  "origin_city": "'.$origin_city.'",
  "origin_state":"'.$origin_state.'",
  "origin_country": "'. $origin_country.'",
  
  "destination_name": "'.$order->getShippingAddress()->getName().'",
  "destination_address": "'.$shipping_street.'",
   "destination_email": "'.$order->getShippingAddress()->getEmail().'",
  "destination_phone":  "'.$order->getShippingAddress()->getTelephone().'",
  "destination_street":  "'.$shipping_street.'",
  "destination_city": "'.$order->getShippingAddress()->getCity().'",
  "destination_state": "'.$order->getShippingAddress()->getRegion().'",
  "destination_country": "'. $shippingCountry.'",
  "delivery_priority_code": "next_day",
  "delivery_callback":"'.$this->getWebsiteUrl().'deliveryupdate" ,
  "finance_callback":"'.$this->getWebsiteUrl().'financeupdate",
  "incoming_option_code": "pickup",
  "pickup_date":"'.$pickup_date.'",
  "delivery_type_code": "last_mile",
  "reference_code": "'.$order->getIncrementId().'",
  
  "use_selected_rate": true,
  "selected_rate_id": '.$selected_courier_id.',
  "accept_value_on_delivery": true,
  "amount_to_receive": '.($order->getSubtotal()+$fee).',
  "fee_payment_channel_code": "cash",
  "channel_code": "website",
  
  "items": '.$items_string.'
}';
     return $payload;
       
  }

   public function has_weight($orderId)
  {
    $order= $this->_orders->load($orderId);
     foreach ($order->getAllItems() as $item) 
         {
           
            if($item->getWeight()=="" || $item->getWeight()==null )
              return false;
          
         }//end for each item

         return true;
  }
  public function build_rates_payload($order)
  {
     
      //billing street
        $street=$this->getSendboxConfig('origin_street');
        
        //shipping street
        $shipping_street="";
        $i=0;
        $shipping_str_length=count($order->getShippingAddress()->getStreet());
        foreach ($order->getShippingAddress()->getStreet() as $str) {
          if($i != ($shipping_str_length-1))
          $shipping_street.=$str.",";
          else
           $shipping_street.=$str;
          $i++;
        }
        //items
        $items_string='[';
        $item_length=count($order->getAllItems());
        $j=0;
      
       
        foreach ($order->getAllItems() as $item) 
         {
           
            $output='{"name": "'.$item->getName().'",
            "weight": '.$item->getWeight().',
            "package_size_code": "medium",
            "quantity": '.$item->getQtyOrdered().',
            "value": '.$item->getPrice().',
            "amount_to_receive": '.$item->getPrice().'
           }';
            if($j != ($item_length-1))
              $output.=',';
            $j++;
           $items_string.=$output;
         }//end for each item
         $items_string=$items_string.',{"name": "delivery fee",
            "weight": 0,
            "package_size_code": "medium",
            "quantity": 1,
            "value": '.$order->getShippingAmount().',
             "reference_code": "'.$order->getIncrementId().'",
            "amount_to_receive": '.$order->getShippingAmount().'
           }';
         $items_string.=']';
  $origin_country = $this->_countryFactory->create()->loadByCode($this->getSendboxConfig('country_id'))->getName();

$shippingCountry = $this->_countryFactory->create()->loadByCode($order->getShippingAddress()->getData()['country_id'])->getName();

$pickup_date= $order->getCreatedAt(); 
  $origin_name=$this->getSendboxConfig('origin_name');
$origin_phone=$this->getSendboxConfig('origin_phone');
$origin_street=$this->getSendboxConfig('origin_street');
$origin_city=$this->getSendboxConfig('origin_city');
$origin_address=$this->getSendboxConfig('origin_address');
$origin_state=$this->_region->load($this->getSendboxConfig('region_id'))->getName();
    $payload=' {
  "origin_name": "'.$origin_name.'",
  "origin_address": "'.$street.'",
  "origin_phone": "'.$origin_phone.'",
  "origin_street":  "'.$street.'",
  "origin_city": "'.$origin_city.'",
  "origin_state":"'.$origin_state.'",
  "origin_country": "'.$origin_country.'",
  
  "destination_name": "'.$order->getShippingAddress()->getName().'",
  "destination_address": "'.$shipping_street.'",
  "destination_phone":  "'.$order->getShippingAddress()->getTelephone().'",
  "destination_street":  "'.$shipping_street.'",
  "destination_city": "'.$order->getShippingAddress()->getCity().'",
  "destination_state": "'.$order->getShippingAddress()->getRegion().'",
  "destination_country": "'.$shippingCountry.'",
  "delivery_priority_code": "next_day",
  
  "incoming_option_code": "pickup",
  "pickup_date":"'.$pickup_date.'",
  "delivery_type_code": "last_mile",
  
  "accept_value_on_delivery": true,
  "amount_to_receive": '.($order->getSubtotal()).',
  "fee_payment_channel_code": "cash",
  "channel_code": "website",  
    "items": '.$items_string.'
 
}';

     return $payload;
       
  }




}