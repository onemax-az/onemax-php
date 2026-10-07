<?php

declare(strict_types=1);

namespace OneMax\Tests;

use OneMax\Pkce;
use PHPUnit\Framework\TestCase;

final class PkceTest extends TestCase
{
    public function testTheRfc7636VectorPasses(): void
    {
        self::assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            Pkce::challengeFor('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
        );
    }

    public function testGenerateReturnsA43CharacterVerifierAndItsChallenge(): void
    {
        $pkce = Pkce::generate();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $pkce->verifier);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $pkce->challenge);
        self::assertSame(Pkce::challengeFor($pkce->verifier), $pkce->challenge);
    }

    public function testTwoCallsDiffer(): void
    {
        $first = Pkce::generate();
        $second = Pkce::generate();

        self::assertNotSame($first->verifier, $second->verifier);
        self::assertNotSame($first->challenge, $second->challenge);
    }
}
