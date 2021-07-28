<?php
declare(strict_types=1);

namespace Flowpack\Task\Domain\Repository;

use DateTime;
use Doctrine\DBAL\Types\Types;
use Flowpack\Task\Domain\Model\TaskExecution;
use Neos\Flow\Annotations as Flow;
use Flowpack\Task\Domain\Task\Task;
use Flowpack\Task\Domain\Task\TaskStatus;
use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\QueryResultInterface;

/**
 * @Flow\Scope("singleton")
 */
class TaskExecutionRepository extends Repository
{
    public function findPending(Task $task): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('taskIdentifier', $task->getIdentifier()),
                $query->logicalOr(
                    $query->equals('status', TaskStatus::PLANNED),
                    $query->equals('status', TaskStatus::RUNNING),
                )
            )
        );
        return $query->execute();
    }

    public function findByTask(Task $task): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('taskIdentifier', $task->getIdentifier()),
        );
        return $query->execute();
    }

    public function findLatest(Task $task): QueryResultInterface
    {
        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                $query->equals('taskIdentifier', $task->getIdentifier()),
                $query->in('status', [TaskStatus::ABORTED, TaskStatus::COMPLETED, TaskStatus::FAILED, TaskStatus::RUNNING])
            )
        );

        return $query->execute();
    }

    public function findNextScheduled(DateTime $runTime, array $skippedExecutions = [], Task $task = null): ?TaskExecution
    {
        $queryBuilder = $this->createQueryBuilder('taskExecution');

        $queryBuilder
            ->where(
                $queryBuilder->expr()->lte('taskExecution.scheduleTime', ':scheduleTime')
            )
            ->orderBy('taskExecution.scheduleTime', QueryInterface::ORDER_DESCENDING)
            ->setMaxResults(1)
            ->setParameter('scheduleTime', $runTime, Types::DATETIME_MUTABLE);

        if (!empty($skippedExecutions)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->not($queryBuilder->expr()->in('taskExecution.Persistence_Object_Identifier', ':skippedExecutions'))
            )->setParameter('skippedExecutions', $skippedExecutions);
        }

        if ($task !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('taskExecution.taskIdentifier', ':taskIdentifier'))
                ->setParameter('taskIdentifier', $task->getIdentifier());
        }

        return current($queryBuilder->getQuery()->getResult());
    }
}
