<?php

declare(strict_types=1);

namespace Cliniva\Models;

use Cliniva\Core\Database;
use PDO;

abstract class BaseModel
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }
}
