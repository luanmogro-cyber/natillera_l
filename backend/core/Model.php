<?php
declare(strict_types=1);

namespace Natillera\Core;

use PDO;

abstract class Model
{
    public function __construct(protected PDO $db)
    {
    }
}
