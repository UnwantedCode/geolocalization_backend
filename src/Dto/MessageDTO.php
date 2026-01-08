<?php

namespace App\Dto;

class MessageDTO
{
    public int $id;
    public int $userId;
    public string $username;
    public ?string $userAvatar = null;
    public int $groupId;
    public string $content;
    public string $createdAt;
    public string $updatedAt;
}
