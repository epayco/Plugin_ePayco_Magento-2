<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);
namespace PagoEpayco\Payco\Model;

use Magento\Framework\Model\AbstractModel;


class OrderEpayco extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\PagoEpayco\Payco\Model\ResourceModel\OrderEpayco::class);
    }
}
