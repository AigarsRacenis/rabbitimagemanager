<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\Queue\Handler;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\MessageQueue\CallbackInvokerInterface;
use Magento\Framework\MessageQueue\Consumer\ConfigInterface as ConsumerConfig;
use Magento\Framework\MessageQueue\ConsumerInterface;
use Magento\Framework\MessageQueue\ConsumerConfigurationInterface;
use Magento\Framework\MessageQueue\QueueInterface;
use Psr\Log\LoggerInterface;
use RabbitMQ\ImageManager\Model\LogFactory;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;

class ImageManagerHandler implements ConsumerInterface
{
    private const DOWNLOAD_SUBDIR = 'rabbitmq_downloads';

    /**
     * @var Curl
     */
    private Curl $curl;

    /**
     * @var File
     */
    private File $file;

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
     * @var ConsumerConfigurationInterface
     */
    private ConsumerConfigurationInterface $configuration;

    /**
     * @var CallbackInvokerInterface
     */
    private CallbackInvokerInterface $invoker;

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @param Curl $curl
     * @param File $file
     * @param LoggerInterface $logger
     * @param LogFactory $logFactory
     * @param LogResource $logResource
     * @param ConsumerConfigurationInterface $configuration
     * @param CallbackInvokerInterface $invoker
     * @param Filesystem $filesystem
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Curl $curl,
        File $file,
        LoggerInterface $logger,
        LogFactory $logFactory,
        LogResource $logResource,
        ConsumerConfigurationInterface $configuration,
        CallbackInvokerInterface $invoker,
        Filesystem $filesystem,
        ProductRepositoryInterface $productRepository
    ) {
        $this->curl = $curl;
        $this->file = $file;
        $this->logger = $logger;
        $this->logFactory = $logFactory;
        $this->logResource = $logResource;
        $this->configuration = $configuration;
        $this->invoker = $invoker;
        $this->filesystem = $filesystem;
        $this->productRepository = $productRepository;
    }

    public function process($maxNumberOfMessages = null): void
    {
        $queue = $this->configuration->getQueue();
        $maxIdleTime = $this->configuration->getMaxIdleTime();
        $sleep = $this->configuration->getSleep();

        if (!isset($maxNumberOfMessages)) {
            $queue->subscribe($this->getTransactionCallback($queue));
        } else {
            $this->invoker->invoke(
                $queue,
                $maxNumberOfMessages,
                $this->getTransactionCallback($queue),
                $maxIdleTime,
                $sleep
            );
        }
    }

    private function getTransactionCallback(QueueInterface $queue): \Closure
    {
        return function ($message) use ($queue) {
            try {
                $messageBody = json_decode($message->getBody(), true);
            } catch (\Exception $exception) {
                $this->logger->log('error', "Retrieving RabbitMQ: " . $exception->getMessage());
            }
                $queue->acknowledge($message);

                if (!is_array($messageBody)) {
                    $messageBody = json_decode($messageBody, true);

                    if (!is_array($messageBody)) {
                        $queue->acknowledge($message);
                        return;
                    }
                }

            $this->processMessage($messageBody);
        };
    }

    private function processMessage(array $messageBody): void
    {
        try {
            if (!isset($messageBody['store_id'], $messageBody['entity_id'], $messageBody['image_path'])) {
                throw new \InvalidArgumentException("Missing required fields: store_id, entity_id, image_path");
            }

            $storeId = $messageBody['store_id'];
            $entityId = $messageBody['entity_id'];
            $imagePath = $messageBody['image_path'];

            $this->logger->info("Processing image for entity ID: $entityId, store ID: $storeId, image path: $imagePath");

            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $downloadDir = $mediaDirectory->getAbsolutePath(self::DOWNLOAD_SUBDIR);

            if (!is_dir($downloadDir)) {
                $this->file->mkdir($downloadDir);
            }

            $localImagePath = $downloadDir . DIRECTORY_SEPARATOR . basename($imagePath);

            // Lejupielādes daļa
            if (!file_exists($localImagePath)) {
                $this->downloadImage($imagePath, $localImagePath);

                if (!file_exists($localImagePath)) {
                    throw new \Exception("Local image file does not exist after download: $localImagePath");

                    return;
                }
            }

            // Produkta daļa
            $product = $this->productRepository->getById($entityId, false, $storeId);

            $existingMediaGallery = $product->getMediaGalleryImages();

            foreach ($existingMediaGallery as $mediaImage) {
                if (basename($mediaImage->getFile()) === basename($localImagePath)) {
                    $this->logger->info("Image already exists for productId: $entityId");
                    $this->addLog('Consume', 'Skipped', $localImagePath);

                    return;
                }
            }

            $product->setCustomAttribute('image_path', null);
            $this->productRepository->save($product);

            $product->addImageToMediaGallery($localImagePath, ['image', 'small_image', 'thumbnail'], false, false);
            $this->productRepository->save($product);

            $this->addLog('Consume', 'Success', $localImagePath);
            $this->logger->info("Successfully processed image for product $entityId");
        } catch (\Exception $e) {
            $this->addLog('Consume', 'Error', $imagePath);
            $this->logger->error("Error processing image for product $entityId: " . $e->getMessage());
        }
    }


    private function downloadImage(string $imageUrl, string $localPath): void
    {
        try {
            $this->curl->get($imageUrl);
            if ($this->curl->getStatus() !== 200) {
                throw new \Exception("Failed to download image: " . $this->curl->getStatus());
            }
            $this->file->write($localPath, $this->curl->getBody());
        } catch (\Exception $e) {
            $this->logger->error("Error downloading image: " . $e->getMessage());

            throw $e;
        }
    }

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
