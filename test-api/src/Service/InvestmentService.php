<?php
namespace App\Service;

use App\Entity\Investment;
use DateTimeInterface;
use DateTimeImmutable;
use InvalidArgumentException;

class InvestmentService
{
    private const MONTHLY_INTEREST_RATE = 0.0052; // 0,52% ao mês

    /**
     * Calcula o saldo esperado do investimento considerando juros compostos.
     *
     * @param Investment $investment
     * @param DateTimeInterface|null $asOfDate Data para cálculo do saldo (default hoje)
     * @return float
     */
    public function calculateExpectedBalance(Investment $investment, ?DateTimeInterface $asOfDate = null): float
    {
        $asOfDate ??= new DateTimeImmutable('now');
        $createdAt = $investment->getCreatedAt();
        // Se investimento resgatado, calcula até a data do resgate
        $endDate = $investment->getRedeemedAt() ?? $asOfDate;
        // Se a data para cálculo for anterior à criação, saldo = 0
        if ($endDate < $createdAt) {
            return 0.0;
        }
        $months = $this->calculateMonthsBetween($createdAt, $endDate);
        $initial = $investment->getInitialValue();
        // Juros compostos: saldo = valor_inicial * (1 + taxa) ^ meses
        $balance = $initial * pow(1 + self::MONTHLY_INTEREST_RATE, $months);
        return round($balance, 2);
    }

    /**
     * Calcula meses completos entre duas datas baseado no dia do mês da data inicial.
     */
    private function calculateMonthsBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $startDay = (int) $start->format('d');
        $interval = $start->diff($end);
        $months = $interval->y * 12 + $interval->m;
        // Ajusta se o dia do mês em $end ainda não chegou
        if ((int) $end->format('d') < $startDay) {
            $months--;
        }
        return max(0, $months);
    }

    /**
     * Calcula o valor do imposto baseado no lucro e tempo do investimento.
     *
     * @param Investment $investment
     * @param float $balance Valor antes do imposto
     * @return float Valor do imposto a ser aplicado
     */
    public function calculateTax(Investment $investment, float $balance): float
    {
        $initial = $investment->getInitialValue();
        $profit = $balance - $initial;
        if ($profit <= 0) {
            return 0.0;
        }
        $createdAt = $investment->getCreatedAt();
        $now = new DateTimeImmutable('now');
        $interval = $createdAt->diff($now);
        $years = $interval->y;
        if ($years < 1) {
            $taxRate = 0.225; // 22.5%
        } elseif ($years < 2) {
            $taxRate = 0.185; // 18.5%
        } else {
            $taxRate = 0.15;  // 15%
        }
        return round($profit * $taxRate, 2);
    }

    /**
     * Calcula o resgate do investimento, aplicando todas as regras e retornando valor líquido após imposto.
     *
     * @param Investment $investment
     * @param DateTimeInterface $redeemDate Data do resgate informada pelo usuário
     * @return array ['gross' => float, 'tax' => float, 'net' => float]
     */
    public function redeemInvestment(Investment &$investment, DateTimeInterface $redeemDate): array
    {
        $gross = $this->calculateExpectedBalance($investment, $redeemDate);
        $tax = $this->calculateTax($investment, $gross);
        $net = $gross - $tax;

        return [
            'gross' => round($gross, 2),
            'profit' => round($gross - $investment->getInitialValue(), 2),
            'tax' => $tax,
            'net' => round($net, 2),
        ];
    }
}