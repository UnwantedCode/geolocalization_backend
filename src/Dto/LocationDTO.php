<?php

namespace App\Dto;

class LocationDTO
{
    public int $id;
    public float $latitude;
    public float $longitude;
    public string $timestamp;
    public int $batteryLevel;
}