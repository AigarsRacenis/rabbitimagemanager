<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\Queue\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use RabbitMQ\ImageManager\Model\LogFactory;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;

class ImageData
{
    const TOPIC_NAME = 'image.manager.queue';

    /**
     * @var PublisherInterface
     */
    private PublisherInterface $publisher;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var LogFactory
     */
    private LogFactory $logFactory;

    /**
     * @var LogResource
     */
    private LogResource $logResource;

    /**
     * @param PublisherInterface $publisher
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param LogFactory $logFactory
     * @param LogResource $logResource
     */
    public function __construct(
        PublisherInterface $publisher,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        LogFactory $logFactory,
        LogResource $logResource
    ) {
        $this->publisher = $publisher;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->logFactory = $logFactory;
        $this->logResource = $logResource;
    }

    /**
     * Publish image data to RabbitMQ
     *
     * @param array $imageData
     * @return array
     */
    public function execute(array $imageData): array
    {
        try {
            $this->publisher->publish(self::TOPIC_NAME, $this->serializer->serialize($imageData));
            $result = 1;
            $msg = 'Image data successfully published to RabbitMQ';
            $this->addLog('Publish', 'Success', $imageData['image_path'] ?? '');
        } catch (\Exception $exception) {
            $result = 0;
            $msg = $exception->getMessage();
            $this->logger->error('RabbitMQ Image Manager data export to Rabbit: ' . $msg);
            $this->addLog('Publish', 'Error', $imageData['image_path'] ?? '');
        }

        return [
            'result' => $result,
            'msg' => $msg
        ];
    }

    /**
     * Add log to database
     *
     * @param string $messageType
     * @param string $status
     */
    private function addLog(string $messageType, string $status, string $imagePath): void
    {
        try {
            $log = $this->logFactory->create();
            $log->setMessageType($messageType);
            $log->setStatus($status);
            $log->setImagePath($imagePath);
            $this->logResource->save($log);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add log: ' . $e->getMessage());
        }
    }
}
