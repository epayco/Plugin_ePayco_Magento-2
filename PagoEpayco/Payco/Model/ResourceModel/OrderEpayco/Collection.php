<?php
namespace PagoEpayco\Payco\Model\ResourceModel\OrderEpayco;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PagoEpayco\Payco\Model\OrderEpayco as Model;
use PagoEpayco\Payco\Model\ResourceModel\OrderEpayco as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
