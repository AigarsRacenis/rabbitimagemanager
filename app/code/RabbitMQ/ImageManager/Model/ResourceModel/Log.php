<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('rabbitmq_imagemanager_log', 'log_id');
    }
}
