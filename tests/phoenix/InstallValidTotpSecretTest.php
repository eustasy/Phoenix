<?php

declare(strict_types=1);

namespace Phoenix\Tests;

use PHPUnit\Framework\TestCase;

class InstallValidTotpSecretTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__.'/../../src/functions/install.valid.totp.secret.php';
    }

    public function testAcceptsBase32SecretOfSufficientLength(): void
    {
        $this->assertTrue(install_valid_totp_secret('JBSWY3DPEHPK3PXP'));
        // A freshly minted secret must validate.
        $this->assertTrue(install_valid_totp_secret(\eustasy\Authenticatron::makeSecret()));
    }

    public function testRejectsTooShortSecret(): void
    {
        // The verifier requires at least 16 chars; shorter is rejected.
        $this->assertFalse(install_valid_totp_secret('JBSWY3DP'));
    }

    public function testRejectsNonBase32Characters(): void
    {
        // 0, 1, 8, 9 and lowercase are outside the base32 alphabet.
        $this->assertFalse(install_valid_totp_secret('jbswy3dpehpk3pxp'));
        $this->assertFalse(install_valid_totp_secret('01890189018901890'));
        $this->assertFalse(install_valid_totp_secret('JBSWY3DPEHPK3PX='));
    }

    public function testRejectsEmptyAndNonStrings(): void
    {
        $this->assertFalse(install_valid_totp_secret(''));
        $this->assertFalse(install_valid_totp_secret(null));
        $this->assertFalse(install_valid_totp_secret(12345));
        $this->assertFalse(install_valid_totp_secret(['JBSWY3DPEHPK3PXP']));
    }

}
