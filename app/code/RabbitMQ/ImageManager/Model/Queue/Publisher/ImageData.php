<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\Queue\Publisher;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

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
     * @param PublisherInterface $publisher
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        PublisherInterface $publisher,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->serializer = $serializer;
        $this->logger = $logger;
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
            $msg = false;
        } catch (\Exception $exception) {
            $this->logger->error('RabbitMQ Image Manager data export to Rabbit: ' . $exception->getMessage());
            $result = 0;
            $msg = $exception->getMessage();
        }

        return [
            'result' => $result,
            'msg' => $msg
        ];
    }
}
