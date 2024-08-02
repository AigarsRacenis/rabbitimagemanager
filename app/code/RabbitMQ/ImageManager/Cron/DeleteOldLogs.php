<?php
declare(strict_types=1);

namespace RabbitMQ\ImageManager\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use RabbitMQ\ImageManager\Model\ResourceModel\Log\CollectionFactory;
use RabbitMQ\ImageManager\Model\ResourceModel\Log as LogResource;
use Psr\Log\LoggerInterface;

class DeleteOldLogs
{
    private const DAYS_TO_KEEP_LOGS = 0;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var LogResource
     */
    private LogResource $logResource;

    /**
     * @var TimezoneInterface
     */
    private TimezoneInterface $timezone;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * @param CollectionFactory $collectionFactory
     * @param LogResource $logResource
     * @param TimezoneInterface $timezone
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        LogResource $logResource,
        TimezoneInterface $timezone,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->logResource = $logResource;
        $this->timezone = $timezone;
        $this->logger = $logger;
    }

    /**
     * Delete old logs
     */
    public function execute()
    {
        $dateLimit = $this->timezone->date();
        $dateLimit->modify('-' . self::DAYS_TO_KEEP_LOGS . ' days');
        $formattedDateLimit = $dateLimit->format('Y-m-d H:i:s');

        $collection = $this->collectionFactory->create()
                    ->addFieldToFilter('created_at', ['lt' => $formattedDateLimit])
                    ->setOrder('created_at', 'ASC');

        foreach ($collection as $log) {
            try {
                $this->logResource->delete($log);
            } catch (\Exception $e) {
                $this->logger->error("Error deleting log with ID " . $log->getId() . ": " . $e->getMessage());
            }
        }
    }
}
