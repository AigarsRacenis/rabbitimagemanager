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
        $this->logger->info("Received message: " . print_r($message, true));
    }
}
