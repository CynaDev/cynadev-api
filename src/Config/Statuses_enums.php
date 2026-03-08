<?php
// src/Config/Statuses_enums.php
namespace App\Config;

enum Statuses_enums: string
{
    case En_Attente = 'En_Attente';
    case Payee = 'Payee';
    case Expediee = 'Expediee';
    case Annulee = 'Annulee';
    public static function default(): self
    {
        return self::En_Attente;  // Valeur par défaut
    }
}