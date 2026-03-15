<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Credentials;

use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Core\Credentials\ServiceAccountCredentials;
use PHPUnit\Framework\TestCase;

final class FileCredentialsTest extends TestCase
{
    public function testResolveReadsAndDecodesJsonFile(): void
    {
        $data = ['type' => 'service_account', 'client_email' => 'test@example.com'];
        $file = tempnam(sys_get_temp_dir(), 'skipper_test_');
        file_put_contents($file, json_encode($data));

        try {
            $credentials = new FileCredentials($file);
            self::assertSame($data, $credentials->resolve());
        } finally {
            unlink($file);
        }
    }

    public function testResolveThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $credentials = new FileCredentials('/nonexistent/file.json');
        $credentials->resolve();
    }
}

final class Base64CredentialsTest extends TestCase
{
    public function testResolveDecodesBase64(): void
    {
        $data = ['type' => 'service_account', 'client_email' => 'test@example.com'];
        $encoded = base64_encode(json_encode($data));

        $credentials = new Base64Credentials($encoded);
        self::assertSame($data, $credentials->resolve());
    }

    public function testResolveThrowsForInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/base64/');

        $credentials = new Base64Credentials('!!!not-valid-base64!!!');
        $credentials->resolve();
    }
}

final class ServiceAccountCredentialsTest extends TestCase
{
    public function testFromArrayAndResolve(): void
    {
        $data = [
            'type' => 'service_account',
            'project_id' => 'my-project',
            'private_key_id' => 'key-id',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'client_email' => 'test@my-project.iam.gserviceaccount.com',
            'client_id' => '12345',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];

        $credentials = ServiceAccountCredentials::fromArray($data);
        $resolved = $credentials->resolve();

        self::assertSame($data['client_email'], $resolved['client_email']);
        self::assertSame($data['private_key'], $resolved['private_key']);
        self::assertSame($data['project_id'], $resolved['project_id']);
    }
}
