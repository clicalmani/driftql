<?php

declare(strict_types=1);

namespace Tonka\DriftQL\Tests\Fixtures\Entities;

use Clicalmani\Database\DataTypes\Integer;
use Clicalmani\Database\DataTypes\VarChar;
use Clicalmani\Database\Factory\Entity;
use Clicalmani\Database\Factory\PrimaryKey;
use Clicalmani\Database\Factory\Property;

final class UserEntity extends Entity
{
    #[Property(length: 10, unsigned: true, autoIncrement: true), PrimaryKey]
    public Integer $id;

    #[Property(length: 255)]
    public VarChar|string $name = '';

    #[Property(length: 255)]
    public VarChar|string $email = '';
}
