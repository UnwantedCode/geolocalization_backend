<?php

namespace App\Dto;

use App\Dto\LocationDTO;
use App\Dto\GroupDTO;
class UserDTO
{
    public int $id;
    public string $email;
    public string $username;
    public string $avatar;
    public array $groups = [];
    public ?LocationDTO $locationCurrent = null;

    public array $locationHistories = [];
}