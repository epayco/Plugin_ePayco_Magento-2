<?php
namespace PagoEpayco\Payco\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderEpayco extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('epayco_order_table', 'id'); // nombre de tabla y PK
    }
}
