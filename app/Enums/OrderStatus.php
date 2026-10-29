<?php

namespace App\Enums;

enum OrderStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRMEE = 'confirmee';
    case EN_COURS_DE_LIVRAISON = 'en_cours_de_livraison';
    case LIVRE = 'livre';
    case CANCELLED = 'cancelled';

    public function label(string $locale = 'en'): string
    {
        return match ($this) {
            self::EN_ATTENTE => $locale === 'fr' ? 'En attente' : 'Pending',
            self::CONFIRMEE => $locale === 'fr' ? 'Confirmée' : 'Confirmed',
            self::EN_COURS_DE_LIVRAISON => $locale === 'fr' ? 'En cours de livraison' : 'Out for delivery',
            self::CANCELLED => $locale === 'fr' ? 'Annulée' : 'Cancelled',
            self::LIVRE => $locale === 'fr' ? 'Livrée' : 'Delivered',
        };
    }

    public static function order(): array
    {
        return [
            self::EN_ATTENTE->value => 1,
            self::CONFIRMEE->value => 2,
            self::EN_COURS_DE_LIVRAISON->value => 3,
            self::LIVRE->value => 4,
        ];
    }

    public function canTransitionTo(self|string $next): bool
    {
        $nextValue = $next instanceof self ? $next->value : $next;
        if ($this === self::CANCELLED) return false;
        if ($nextValue === self::CANCELLED->value) return $this === self::EN_ATTENTE;
        $order = self::order();

        $currentRank = $order[$this->value] ?? 0;
        $nextRank = $order[$nextValue] ?? 0;

        return $nextRank > $currentRank;
    }
}
