<?php
// src/Config/Disponibilite_enums.php
namespace App\Config;

enum Disponibilite_enums: string
{
    case Disponible = 'Disponible';
    case Maintenance = 'Maintenance';
    case Indisponible = 'Indisponible';
    public static function default(): self
    {
        return self::Disponible;  // Valeur par défaut
    }
}