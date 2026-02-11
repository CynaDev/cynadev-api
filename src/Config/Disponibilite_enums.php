<?php
// src/Config/Disponibilite_enums.php
namespace App\Config;

enum Disponibilite_enums: string
{
    case Dipsonible = 'Dipsonible';
    case Maintenance = 'Maintenance';
    case Indisponible = 'Indisponible';
    public static function default(): self
    {
        return self::Dipsonible;  // Valeur par défaut
    }
}