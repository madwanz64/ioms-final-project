<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Service\TransactionManagerInterface;
use PDO;

final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(callable $work): mixed
    {
        return Database::transactional($this->pdo, $work);
    }
}
