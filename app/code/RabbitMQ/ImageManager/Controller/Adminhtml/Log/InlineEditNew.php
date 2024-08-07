<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use RabbitMQ\ImageManager\Model\LogFactory;
use RabbitMQ\ImageManager\Model\ResourceModel\Log;
use Magento\Framework\Controller\Result\JsonFactory;
use RabbitMQ\ImageManager\Model\Queue\Publisher\ImageData;

class InlineEditNew extends Action
{
    protected $jsonFactory;
    protected $logResource;
    protected $logFactory;
    protected $imageDataPublisher;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Log $logResource,
        LogFactory $logFactory,
        ImageData $imageDataPublisher
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->logResource = $logResource;
        $this->logFactory = $logFactory;
        $this->imageDataPublisher = $imageDataPublisher;
    }

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resulJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $logId) {
                    $log = $this->logFactory->create();
                    $this->logResource->load($log, $logId);
                    try {
                        $newImagePath = $postItems[$logId]['image_path'];

                        // Publish to RabbitMQ
                        $imageData = [
                            'store_id' => $log->getStoreId(),
                            'entity_id' => $log->getEntityId(),
                            'image_path' => $newImagePath
                        ];
                        $publishResult = $this->imageDataPublisher->execute($imageData);

                        if (!$publishResult['result']) {
                            throw new \Exception($publishResult['msg']);
                        }

                        $messages[] = __('New image path "%1" for log ID %2 has been sent for processing.', $newImagePath, $logId);
                    } catch (\Exception $e) {
                        $messages[] = "[Error for log ID $logId:] " . $e->getMessage();
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }
}
