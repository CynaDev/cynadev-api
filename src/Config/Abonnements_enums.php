<?php
// src/Config/Abonnements_enums.php
namespace App\Config;

enum Abonnements_enums: string
{
    case Actif = 'Actif';
    case Resilie = 'Resilié';
    case Annule = 'Annulé';
    public static function default(): self
    {
        return self::Actif;  // Valeur par défaut
    }
}