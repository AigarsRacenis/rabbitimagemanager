<?php

namespace RabbitMQ\ImageManager\Controller\Adminhtml\Log;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use RabbitMQ\ImageManager\Model\ResourceModel\Log\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;

class MassDelete extends \Magento\Backend\App\Action
{
    protected $filter;
    protected $collectionFactory;
    protected $scopeConfig;
    protected $resource;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleteCount = 0;
        $connection = $this->resource->getConnection();

        try {
            $table = $this->resource->getTableName('rabbitmq_imagemanager_log');

            foreach ($collection as $log) {
                $entityId = $log->getId();
                $connection->delete(
                    $table,
                    ['log_id = ?' => $entityId]
                );
                $deleteCount++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 log(s) have been deleted.', $deleteCount));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting logs.'));
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }

    protected function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('rabbitmq_imagemanager/general/enabled');
    }
}
