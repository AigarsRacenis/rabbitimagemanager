<?php
namespace RabbitMQ\ImageManager\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class ProductName extends Column
{
    protected $productRepository;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ProductRepository $productRepository,
        array $components = [],
        array $data = []
    ) {
        $this->productRepository = $productRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['entity_id'])) {
                    try {
                        $product = $this->productRepository->getById($item['entity_id']);
                        $item[$this->getData('name')] = $product->getName();
                    } catch (\Exception $e) {
                        $item[$this->getData('name')] = __('N/A');
                    }
                }
            }
        }

        return $dataSource;
    }
}
