<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use RabbitMQ\ImageManager\Model\Queue\Publisher\ImageData as PublisherImageData;

class ProductSaveAfter implements ObserverInterface
{
    /**
     * @var PublisherImageData
     */
    private PublisherImageData $publisher;

    /**
     * @var array
     */
    private array $publishedAlready = [];

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @param PublisherImageData $publisherImageData
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        PublisherImageData $publisherImageData,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->publisher = $publisherImageData;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();

        if (!$product instanceof Product) {
            return $this;
        }

        if (!$this->isEnabled()) {
            return $this;
        }

        $entityId = $product->getId();
        $storeId = $product->getStoreId();

        if (isset($this->publishedAlready[$entityId . '_' . $storeId])) {
            return $this;
        }

        $this->publishedAlready[$entityId . '_' . $storeId] = 1;

        $imagePath = $product->getData('image_path');

        if ($imagePath) {
            $messageBody = [
                'store_id' => $storeId,
                'entity_id' => $entityId,
                'image_path' => $imagePath
            ];

            $this->publisher->execute($messageBody);
        }

        return $this;
    }

    /**
     * Check if RabbitMQ Image Manager is enabled in the admin configuration
     *
     * @return bool
     */
    protected function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('rabbitmq_imagemanager/general/enabled');
    }
}
