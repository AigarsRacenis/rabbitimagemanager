<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Model\ResourceModel\Log\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Psr\Log\LoggerInterface as Logger;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    /**
     * @var EntityFactory
     */
    private $entityFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var FetchStrategy
     */
    private $fetchStrategy;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable = 'rabbitmq_imagemanager_log',
        $resourceModel = 'RabbitMQ\ImageManager\Model\ResourceModel\Log'
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel
        );
    }
}
