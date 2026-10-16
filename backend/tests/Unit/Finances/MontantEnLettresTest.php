<?php

namespace Tests\Unit\Finances;

use App\Services\Finances\MontantEnLettres;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MontantEnLettresTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function nombres(): array
    {
        return [
            'zéro' => [0, 'zéro'],
            'seize' => [16, 'seize'],
            'dix-sept' => [17, 'dix-sept'],
            'vingt et un' => [21, 'vingt et un'],
            'trente-deux' => [32, 'trente-deux'],
            'soixante et onze' => [71, 'soixante et onze'],
            'soixante-dix-neuf' => [79, 'soixante-dix-neuf'],
            'quatre-vingts' => [80, 'quatre-vingts'],
            'quatre-vingt-un' => [81, 'quatre-vingt-un'],
            'quatre-vingt-onze' => [91, 'quatre-vingt-onze'],
            'cent' => [100, 'cent'],
            'cent un' => [101, 'cent un'],
            'deux cents' => [200, 'deux cents'],
            'deux cent cinquante' => [250, 'deux cent cinquante'],
            'mille' => [1000, 'mille'],
            'mille deux cent cinquante' => [1250, 'mille deux cent cinquante'],
            'deux mille' => [2000, 'deux mille'],
            'vingt et un mille' => [21_000, 'vingt et un mille'],
            'quatre-vingt mille' => [80_000, 'quatre-vingt mille'],
            'deux cent mille' => [200_000, 'deux cent mille'],
            'un million' => [1_000_000, 'un million'],
            'deux millions trois cent mille quatre-vingts' => [2_300_080, 'deux millions trois cent mille quatre-vingts'],
        ];
    }

    #[Test]
    #[DataProvider('nombres')]
    public function les_nombres_suivent_l_orthographe_traditionnelle(int $nombre, string $attendu): void
    {
        $this->assertSame($attendu, MontantEnLettres::nombre($nombre));
    }

    #[Test]
    public function les_montants_en_euros_et_centimes(): void
    {
        $this->assertSame('un euro', MontantEnLettres::euros(1));
        $this->assertSame('zéro euro', MontantEnLettres::euros(0));
        $this->assertSame('mille deux cent cinquante euros', MontantEnLettres::euros('1250.00'));
        $this->assertSame('quatre cents euros et cinquante centimes', MontantEnLettres::euros(400.5));
        $this->assertSame('un centime', MontantEnLettres::euros(0.01));
        $this->assertSame('vingt-deux euros et un centime', MontantEnLettres::euros('22.01'));
        $this->assertSame('un million d\'euros', MontantEnLettres::euros(1_000_000));
        $this->assertSame('un million deux cents euros', MontantEnLettres::euros(1_000_200));
    }
}
