<?php
/**
 *
 * Copyright © 2015 Sendboxcommerce. All rights reserved.
 */
namespace Sendbox\Shipment\Controller\Adminhtml\PostshipmentAdmin;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Sendbox\Shipment\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\Result\RedirectFactory;
class Index extends \Magento\Backend\App\Action
{

    
    protected $_helper;
    protected $_messageManager;
     protected $_orders;
     protected $_resultRedirectFactory;
   
    public function __construct(
        Context $context,
        Data $helper,
        ManagerInterface $messageManager,
        Order $orders,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->_helper=$helper;
        $this->_messageManager=$messageManager;
        $this->_orders=$orders;
        $this->_resultRedirectFactory=$resultRedirectFactory;

    }
    /**
     * Check the permission to run it
     *
     * @return bool
     */
   /*  protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Cms::page');
    } */

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
public function execute()
{
$auth=$this->_helper->getAuthHeader();


//get order object details and populate payload
$order_id=$this->getRequest()->getParam('order_id');
$selected_courier_id=$this->getRequest()->getParam('selected_courier_id');
$fee=$this->getRequest()->getParam('fee');


$order=null;
$response=null;
if(isset($order_id))
{

$order=$this->_orders->load($order_id);
$post= $this->_helper->build_payload($order,$selected_courier_id,$fee);


$url=$this->_helper->getBaseUrl().'/v1/merchant/shipments';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json',
'Authorization: '.$auth)
);

$response= json_decode(curl_exec($ch));
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// close the connection, release resources used
curl_close($ch);


$tracking_code;
$status_code;
$output;

 if($httpcode >= 400 && $httpcode<=500){
    
$output=$response;

$this->messageManager->addErrorMessage($output);

 }
else if($httpcode>=200 && $httpcode<209){

$tracking_code=$response->{'code'};
$status_code=$response->{'status_code'};
$carrier=$response->{'courier'};
$carrier_name=$carrier->{'name'};
$output= "Tracking Number:".$tracking_code."; Status:". $status_code;
$this->_helper->make_shipment($order,$tracking_code,$carrier_name);
$this->messageManager->addSuccessMessage('Shipment Created. '.$output);


}else{
$output='Sorry shipment could not be created.Try again later';
$this->messageManager->addErrorMessage('Sorry shipment could not be created.Try again later');
}


$resultRedirect = $this->resultRedirectFactory->create();
    $resultRedirect->setPath('sales/order/view',array('order_id' =>$order_id));
    return $resultRedirect;



}

    }
}
