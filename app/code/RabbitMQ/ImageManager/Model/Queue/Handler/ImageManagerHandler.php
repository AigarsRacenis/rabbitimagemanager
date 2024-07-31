<?php
namespace RabbitMQ\ImageManager\Model\Queue\Handler;

use Psr\Log\LoggerInterface;

class ImageManagerHandler
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process($message)
    {
        // Process the message
        // This is where you'll implement the logic to download and attach the image
        // For now, we'll just log the message
        $this->logger->info("Received message: " . print_r($message, true));
    }
}
