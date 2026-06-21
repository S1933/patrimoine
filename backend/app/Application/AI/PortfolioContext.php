<?php

namespace App\Application\AI;

use App\Application\Dashboard\DashboardCalculator;
use App\Models\Investment;

final readonly class PortfolioContext
{
    public function __construct(
        private DashboardCalculator $calculator,
    ) {}

    public function build(string $userId, string $baseCurrency): string
    {
        $summary = $this->calculator->summary($userId, $baseCurrency);
        $allocation = $this->calculator->allocation($userId);
        $breakdown = $this->calculator->breakdown($userId);

        $lines = [
            "Tu es un assistant financier personnel pour l'application Patrimoine.",
            "Tu réponds en français, de manière concise et factuelle.",
            "Tu ne donnes pas de conseils financiers personnalisés.",
            "Tu ne fais pas de recommandations d'achat ou de vente.",
            "Tu peux analyser les données du portefeuille ci-dessous.",
            '',
            '=== Portefeuille actuel ===',
            "Valeur totale : {$summary['total_value']} {$baseCurrency}",
            "Coût total : {$summary['total_cost']} {$baseCurrency}",
            "Plus-value latente : {$summary['pnl_absolute']} {$baseCurrency}",
            "Performance : ".($summary['pnl_percent'] !== null ? "{$summary['pnl_percent']}%" : 'N/A'),
            "Nombre d'actifs : {$summary['active_count']}",
            "Devise de référence : {$summary['currency']}",
            "Dernière mise à jour : {$summary['last_updated_at']}",
            '',
            '=== Répartition par classe d\'actifs ===',
        ];

        foreach ($allocation as $item) {
            $lines[] = "- {$item['label']} : {$item['percent']}% ({$item['value']} {$baseCurrency}, {$item['count']} actif(s))";
        }

        $lines[] = '';
        $lines[] = '=== Top positions ===';

        $top = array_slice($breakdown, 0, 5);
        foreach ($top as $pos) {
            $pnl = $pos['pnl_percent'] !== null ? "({$pos['pnl_percent']}%)" : '';
            $lines[] = "- {$pos['name']} : {$pos['current_value']} {$baseCurrency} {$pnl}";
        }

        return implode("\n", $lines);
    }
}
