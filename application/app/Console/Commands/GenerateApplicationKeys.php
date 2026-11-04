<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

class GenerateApplicationKeys extends Command
{
    protected $signature = 'app:generate-app-keys';

    protected $description = 'Generate application keys';

    public function handle()
    {
        $this->fulfill();
    }

    public function fulfill()
    {
        $appKey = $this->generateRandomKey();

        $appPrivateKey = base64_encode(
            random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        );

        $appIndexKey = base64_encode(
            random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)
        );

        $this->info('Copy these values in your .env file');

        $this->line(sprintf(
            '<comment>APP_KEY</comment>=%s',
            $appKey
        ));

        $this->line(sprintf(
            '<comment>APP_PRIVATE_KEY</comment>=%s',
            $appPrivateKey
        ));

        $this->line(sprintf(
            'For blind index key: <comment>APP_INDEX_KEY</comment>=%s',
            $appIndexKey
        ));

        Log::info(sprintf(
            '[%s] New Application Keys for .env [%s][%s][%s] have been created.',
            $this->name,
            'APP_KEY',
            'APP_PRIVATE_KEY',
            'APP_INDEX_KEY'
        ));
    }

    /**
     * Copy of Illuminate\Foundation\Console\KeyGenerateCommand@generateRandomKey()
     *
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return 'base64:'.base64_encode(
            Encrypter::generateKey($this->laravel['config']['app.cipher'])
        );
    }
}
