<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model;

use Magento\Framework\Model\AbstractModel;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;

class Log extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(LogResource::class);
    }

    public function getImagePath()
    {
        return $this->getData('image_path');
    }

    public function setImagePath($imagePath)
    {
        return $this->setData('image_path', $imagePath);
    }
}
