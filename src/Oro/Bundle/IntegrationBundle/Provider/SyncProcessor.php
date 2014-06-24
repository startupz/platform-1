<?php

namespace Oro\Bundle\IntegrationBundle\Provider;

use Doctrine\ORM\EntityManager;

use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Entity\Status;
use Oro\Bundle\IntegrationBundle\Logger\LoggerStrategy;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\ImportExport\Job\Executor;

class SyncProcessor
{
    /** @var EntityManager */
    protected $em;

    /** @var ProcessorRegistry */
    protected $processorRegistry;

    /** @var Executor */
    protected $jobExecutor;

    /** @var TypesRegistry */
    protected $registry;

    /** @var LoggerStrategy */
    protected $logger;

    /**
     * @param EntityManager     $em
     * @param ProcessorRegistry $processorRegistry
     * @param Executor          $jobExecutor
     * @param TypesRegistry     $registry
     * @param LoggerStrategy    $logger
     */
    public function __construct(
        EntityManager $em,
        ProcessorRegistry $processorRegistry,
        Executor $jobExecutor,
        TypesRegistry $registry,
        LoggerStrategy $logger
    ) {
        $this->em                = $em;
        $this->processorRegistry = $processorRegistry;
        $this->jobExecutor       = $jobExecutor;
        $this->registry          = $registry;
        $this->logger            = $logger;
    }

    /**
     * Process integration synchronization
     * By default, if $connector is empty, will process all connectors of given integration
     *
     * @param Integration $integration Integration object
     * @param string      $connector   Connector name
     * @param array       $parameters  Connector additional parameters
     */
    public function process(Integration $integration, $connector = '', array $parameters = [])
    {
        if ($connector) {
            $this->processChannelConnector($integration, $connector, $parameters, false);
        } else {
            $connectors = $integration->getConnectors();
            foreach ((array)$connectors as $connector) {
                $this->processChannelConnector($integration, $connector, $parameters);
            }
        }
    }

    /**
     * Get logger strategy
     *
     * @return LoggerStrategy
     */
    public function getLoggerStrategy()
    {
        return $this->logger;
    }

    /**
     * Process integration connector
     *
     * @param Integration $integration Integration object
     * @param string      $connector   Connector name
     * @param array       $parameters  Connector additional parameters
     * @param boolean     $saveStatus  Do we need to save new status to bd
     */
    protected function processChannelConnector(
        Integration $integration,
        $connector,
        array $parameters = [],
        $saveStatus = true
    ) {
        if (!$integration->getEnabled()) {
            return;
        }
        try {
            $this->logger->info(sprintf('Start processing "%s" connector', $connector));
            /**
             * Clone object here because it will be modified and changes should not be shared between
             */
            $realConnector = clone $this->registry->getConnectorType($integration->getType(), $connector);
        } catch (\Exception $e) {
            // log and continue
            $this->logger->error($e->getMessage());
            $status = new Status();
            $status->setCode(Status::STATUS_FAILED)
                ->setMessage($e->getMessage())
                ->setConnector($connector);

            $this->em->getRepository('OroIntegrationBundle:Channel')
                ->addStatus($integration, $status);
            return;
        }
        $jobName = $realConnector->getImportJobName();

        $processorAliases = $this->processorRegistry->getProcessorAliasesByEntity(
            ProcessorRegistry::TYPE_IMPORT,
            $realConnector->getImportEntityFQCN()
        );
        $configuration    = [
            ProcessorRegistry::TYPE_IMPORT =>
                array_merge(
                    [
                        'processorAlias' => reset($processorAliases),
                        'entityName'     => $realConnector->getImportEntityFQCN(),
                        'channel'        => $integration->getId()
                    ],
                    $parameters
                ),
        ];
        $this->processImport($connector, $jobName, $configuration, $integration, $saveStatus);
    }

    /**
     * @param string      $connector
     * @param string      $jobName
     * @param array       $configuration
     * @param Integration $integration
     * @param boolean     $saveStatus
     */
    protected function processImport($connector, $jobName, $configuration, Integration $integration, $saveStatus)
    {
        $jobResult = $this->jobExecutor->executeJob(ProcessorRegistry::TYPE_IMPORT, $jobName, $configuration);

        /** @var ContextInterface $contexts */
        $context = $jobResult->getContext();

        $counts = [];
        if ($context) {
            $counts['process'] = $counts['warnings'] = 0;
            $counts['read']    = $context->getReadCount();
            $counts['process'] += $counts['add'] = $context->getAddCount();
            $counts['process'] += $counts['update'] = $context->getUpdateCount();
            $counts['process'] += $counts['delete'] = $context->getDeleteCount();
        }

        $exceptions = $jobResult->getFailureExceptions();
        $isSuccess  = $jobResult->isSuccessful() && empty($exceptions);

        $status = new Status();
        $status->setConnector($connector);
        if (!$isSuccess) {
            $this->logger->error('Errors were occurred:');
            $exceptions = implode(PHP_EOL, $exceptions);
            $this->logger->error(
                $exceptions,
                ['exceptions' => $jobResult->getFailureExceptions()]
            );
            $status->setCode(Status::STATUS_FAILED)->setMessage($exceptions);
        } else {
            if ($context->getErrors()) {
                $this->logger->warning('Some entities were skipped due to warnings:');
                foreach ($context->getErrors() as $error) {
                    $this->logger->warning($error);
                }
            }

            $message = sprintf(
                "Stats: read [%d], process [%d], updated [%d], added [%d], delete [%d], invalid entities: [%d]",
                $counts['read'],
                $counts['process'],
                $counts['update'],
                $counts['add'],
                $counts['delete'],
                $context->getErrorEntriesCount()
            );
            $this->logger->info($message);

            $status->setCode(Status::STATUS_COMPLETED)->setMessage($message);
        }
        if ($saveStatus) {
            $this->em->getRepository('OroIntegrationBundle:Channel')
                ->addStatus($integration, $status);
        }
    }
}
