<?php

namespace App\Dto;

class SafetyAlertDTO
{
    public int $id;
    public int $userId;
    public string $username;
    public ?string $userAvatar = null;
    public int $groupId;
    public string $groupName;
    public string $type;
    public ?string $message = null;
    public float $latitude;
    public float $longitude;
    public ?int $batteryLevel = null;
    public bool $resolved;
    public ?string $resolvedAt = null;
    public string $createdAt;
    public string $updatedAt;
}
