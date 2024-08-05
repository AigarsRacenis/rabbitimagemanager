<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use RabbitMQ\ImageManager\Model\Log;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct()
    {
        $this->_init(Log::class, LogResource::class);
    }
}
