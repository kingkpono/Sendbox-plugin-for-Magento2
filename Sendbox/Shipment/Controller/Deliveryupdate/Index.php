<?php
/**
*
* Copyright © 2015 Sendboxcommerce. All rights reserved.
*/
namespace Sendbox\Shipment\Controller\Deliveryupdate;
class Index extends \Magento\Framework\App\Action\Action
{
    /**
* @var \Magento\Framework\App\Cache\TypeListInterface
*/
protected $_cacheTypeList;
/**
* @var \Magento\Framework\App\Cache\StateInterface
*/
protected $_cacheState;
protected $_order;
protected $_status;
/**
* @var \Magento\Framework\App\Cache\Frontend\Pool
*/
protected $_cacheFrontendPool;
/**
* @var \Magento\Framework\View\Result\PageFactory
*/
protected $resultPageFactory;
/**
* @param Action\Context $context
* @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
* @param \Magento\Framework\App\Cache\StateInterface $cacheState
* @param \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool
* @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
*/
public function __construct(
\Magento\Framework\App\Action\Context $context,
\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
\Magento\Framework\App\Cache\StateInterface $cacheState,
\Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
\Magento\Framework\View\Result\PageFactory $resultPageFactory,
\Magento\Sales\Model\Order $order,
\Magento\Sales\Model\Order\Status $status
) {
parent::__construct($context);
$this->_cacheTypeList = $cacheTypeList;
$this->_cacheState = $cacheState;
$this->_cacheFrontendPool = $cacheFrontendPool;
$this->resultPageFactory = $resultPageFactory;
$this->_order=$order;
$this->_status=$status;
}
    
/**
* Flush cache storage
*
*/
public function execute()
{

$data = json_decode(file_get_contents('php://input'), true);
$response=[];
$response=array("status"=>300);
$status=null;

if(isset($data["reference_code"]))
{
$order = $this->_order->loadByIncrementId($data["reference_code"]);


$status_code=$data['status_code'];
$status_from_sendbox=$data['status']['name'];

$status = $this->_status->load($status_code);

if(!$status->getStatus()){//status deos not  exist

$status->setData('status',  $status_code)->setData('label',  $status_from_sendbox)->save();
}



$orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
$order->setState("processing")->setStatus($status_code);
$order->save();

$response=array("status"=>200);
}


header('Content-Type: application/json');
echo json_encode($response);

}
}