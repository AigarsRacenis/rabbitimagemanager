<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\Queue\Handler;

use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Filesystem\Io\File;
use RabbitMQ\ImageManager\Model\LogFactory;
use Magento\Framework\MessageQueue\QueueInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\MessageQueue\ConsumerInterface;
use Magento\Framework\MessageQueue\CallbackInvokerInterface;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;
use Magento\Framework\MessageQueue\ConsumerConfigurationInterface;
use Magento\Framework\MessageQueue\Consumer\ConfigInterface as ConsumerConfig;

class ImageManagerHandler implements ConsumerInterface
{
    private const DOWNLOAD_SUBDIR = 'rabbitmq_downloads';
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

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

            if (!$this->isValidUrl($imagePath)) {
                $this->addLog('Consume', 'Invalid URL', $imagePath, $entityId, $storeId);

                return;
            }

            if (!$this->isValidExtension($imagePath)) {
                $this->addLog('Consume', 'Invalid Extension', $imagePath, $entityId, $storeId);

                return;
            }
            $imageName = basename($imagePath);
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $downloadDir = $mediaDirectory->getAbsolutePath(self::DOWNLOAD_SUBDIR);
            $localImagePath = $downloadDir . DIRECTORY_SEPARATOR . $imageName;

            if (!is_dir($downloadDir)) {
                $this->file->mkdir($downloadDir);
            }

            // Check if the image already exists in the downloads folder
            if (!$this->imageExistsInDownloads($imageName)) {
                try {
                    $this->downloadImage($imagePath, $localImagePath);

                    if (!file_exists($localImagePath)) {
                        $this->addLog('Consume', sprintf("[%s]","Couldn't download"), $imagePath, $entityId, $storeId);
                        return;
                    }
                } catch (\Exception $e) {
                    $this->addLog('Consume', 'Error downloading image', $imagePath, $entityId, $storeId);

                    return;
                }
            } else {
                $this->logger->info("Image already exists in downloads folder: $imageName. Skipping download.");
            }

            $product = $this->productRepository->getById($entityId, false, $storeId);

            $product->setCustomAttribute('image_path', null);
            $this->productRepository->save($product);

            $product->addImageToMediaGallery($localImagePath, ['image', 'small_image', 'thumbnail'], false, false);
            $product->setCustomAttribute('image_path', $imagePath);
            $this->productRepository->save($product);

            $this->addLog('Consume', 'Success', $imagePath, $entityId, $storeId);
            $this->logger->info("Successfully processed image for product $entityId");
        } catch (\Exception $e) {
            $this->addLog('Consume', 'Error', $imagePath, $entityId, $storeId);
            $this->logger->error("Error processing image for product $entityId: " . $e->getMessage());
        }
    }

    private function downloadImage(string $imageUrl, string $localPath): void
    {
        try {
            $this->curl->get($imageUrl);
            $this->file->write($localPath, $this->curl->getBody());
        } catch (\Exception $e) {
            $this->logger->error("Error downloading image: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Add log to database
     *
     * @param string $messageType
     * @param string $status
     * @param string $imagePath
     * @param string $enityId
     * @param $storeId
     */
    private function addLog(string $messageType, string $status, string $imagePath, string $entityId, $storeId): void
    {
        try {
            $log = $this->logFactory->create();
            $log->setMessageType($messageType);
            $log->setStatus($status);
            $log->setImagePath($imagePath);
            $log->setEntityId($entityId);
            $log->setStoreId($storeId);
            $this->logResource->save($log);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add log: ' . $e->getMessage());
        }
    }

    private function imageExistsInDownloads(string $imageName): bool
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $downloadDir = $mediaDirectory->getAbsolutePath(self::DOWNLOAD_SUBDIR);
        $localImagePath = $downloadDir . DIRECTORY_SEPARATOR . $imageName;
        return file_exists($localImagePath);
    }

    private function isValidUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        return false;
    }

    private function isValidExtension(string $url): bool
    {
        $pattern = '/\.(?:'.implode('|', self::ALLOWED_EXTENSIONS). ')$/i';

        return preg_match($pattern, $url) === 1;
    }
}
